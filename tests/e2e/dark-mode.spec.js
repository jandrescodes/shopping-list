import AxeBuilder from '@axe-core/playwright';
import { expect, test } from '@playwright/test';
import { execFileSync } from 'node:child_process';

// The theme follows the system (`prefers-color-scheme`): no toggle, no stored
// preference. These cases read computed styles with the scheme emulated.

async function createList(request, name = 'Feria del sábado') {
    const res = await request.post('/api/lists', { data: { name } });
    expect(res.ok()).toBeTruthy();

    return (await res.json()).slug;
}

// Resolves any CSS color (Tailwind 4 emits oklch) to [r, g, b] through a canvas.
async function rgbOf(locator, property) {
    return locator.evaluate((el, prop) => {
        const ctx = document.createElement('canvas').getContext('2d', { willReadFrequently: true });
        ctx.fillStyle = getComputedStyle(el)[prop];
        ctx.fillRect(0, 0, 1, 1);

        return Array.from(ctx.getImageData(0, 0, 1, 1).data.slice(0, 3));
    }, property);
}

function near(actual, expected, tolerance = 2) {
    actual.forEach((channel, i) => expect(Math.abs(channel - expected[i])).toBeLessThanOrEqual(tolerance));
}

const GRAY_950 = [3, 7, 18];
const PAGE_LIGHT = [249, 250, 251];

test.describe('dark scheme', () => {
    test.use({ colorScheme: 'dark' });

    test('the page, the QR and a notice resolve to dark tones', async ({ page, context, request }) => {
        const slug = await createList(request);
        await page.goto(`/l/${slug}`);
        await expect(page.locator('#client-item-list')).toBeVisible();

        near(await rgbOf(page.locator('body'), 'backgroundColor'), GRAY_950);
        expect(await page.evaluate(() => getComputedStyle(document.documentElement).colorScheme)).toBe('light dark');

        // The QR stays on white so phones can scan it.
        await page.locator('#show-qr').click();
        await expect(page.locator('#qr-code')).toBeVisible();
        near(await rgbOf(page.locator('#qr-code'), 'backgroundColor'), [255, 255, 255], 3);

        // A notice keeps its reserved class but gets its own dark surface.
        await context.setOffline(true);
        const notice = page.locator('#list-app .bg-amber-50');
        await expect(notice).toContainText('Sin conexión');
        const noticeBg = await rgbOf(notice, 'backgroundColor');
        expect(noticeBg).not.toEqual(PAGE_LIGHT);
        expect(noticeBg).not.toEqual(GRAY_950);
        expect(await rgbOf(notice, 'color')).not.toEqual(await rgbOf(page.locator('body'), 'color'));
        await context.setOffline(false);
    });

    test('the home page resolves to dark tones too', async ({ page }) => {
        await page.goto('/');
        near(await rgbOf(page.locator('body'), 'backgroundColor'), GRAY_950);
    });

    test('switching the scheme live keeps an open edit', async ({ page, request }) => {
        const slug = await createList(request);
        await request.post(`/api/lists/${slug}/items`, { data: { name: 'Pan' } });
        await page.goto(`/l/${slug}`);
        await page.getByRole('button', { name: 'Editar Pan' }).click();

        const input = page.locator('#client-item-list input[type="text"]').first();
        await expect(input).toBeVisible();
        await input.fill('Pan integral');

        await page.emulateMedia({ colorScheme: 'light' });
        near(await rgbOf(page.locator('body'), 'backgroundColor'), PAGE_LIGHT);
        await expect(input).toBeVisible();
        await expect(input).toHaveValue('Pan integral');

        await page.emulateMedia({ colorScheme: 'dark' });
        near(await rgbOf(page.locator('body'), 'backgroundColor'), GRAY_950);
        await expect(input).toHaveValue('Pan integral');
    });
});

test.describe('light scheme (regression)', () => {
    test.use({ colorScheme: 'light' });

    test('keeps the light page and a light notice', async ({ page, context, request }) => {
        const slug = await createList(request);
        await page.goto(`/l/${slug}`);
        await expect(page.locator('#client-item-list')).toBeVisible();
        near(await rgbOf(page.locator('body'), 'backgroundColor'), PAGE_LIGHT);

        await context.setOffline(true);
        const notice = page.locator('#list-app .bg-amber-50');
        await expect(notice).toContainText('Sin conexión');
        near(await rgbOf(notice, 'backgroundColor'), [255, 251, 235], 3);
        await context.setOffline(false);
    });
});

// Contrast audit in dark: pages without a list. 500 and 503 cannot be reached
// from the e2e server, so their real Blade output is served from a stub URL.

const AXE_TAGS = ['wcag2a', 'wcag2aa', 'wcag21aa'];
const VIEWPORTS = [
    { name: '375px', width: 375, height: 800 },
    { name: '1280px', width: 1280, height: 800 },
];

function renderView(name) {
    return execFileSync(
        'php',
        // A dev-server `public/hot` would point @vite at :5173; force the built assets.
        ['artisan', 'tinker', '--execute', `Vite::useHotFile('/nonexistent'); echo view('${name}')->render();`],
        { env: { ...process.env, APP_ENV: 'testing' }, encoding: 'utf8' },
    ).replaceAll('http://localhost/', 'http://127.0.0.1:8199/');
}

async function stubPage(page, path, html, status) {
    await page.route(`**${path}`, (route) => route.fulfill({ status, contentType: 'text/html; charset=utf-8', body: html }));
}

async function expectNoViolations(page, { skipRules = [] } = {}) {
    // Transitions off: axe would read colors mid-animation.
    await page.addStyleTag({ content: '*, *::before, *::after { transition: none !important; animation: none !important; }' });
    const results = await new AxeBuilder({ page }).withTags(AXE_TAGS).disableRules(skipRules).analyze();
    expect(results.violations).toEqual([]);
}

test.describe('axe in dark: pages without a list', () => {
    test.use({ colorScheme: 'dark' });

    const html503 = renderView('errors.503');
    const html500 = renderView('errors.500');

    for (const vp of VIEWPORTS) {
        test.describe(vp.name, () => {
            test.use({ viewport: { width: vp.width, height: vp.height } });

            test('home, empty', async ({ page }) => {
                await page.goto('/');
                await expect(page.getByText('Todavía no has abierto ninguna lista')).toBeVisible();
                await expectNoViolations(page);
            });

            test('home, with entries', async ({ page }) => {
                await page.addInitScript(() => {
                    localStorage.setItem('myShoppingLists', JSON.stringify([
                        { slug: 'abc123', name: 'Feria del sábado' },
                        { slug: 'def456', name: 'Cumpleaños' },
                    ]));
                });
                await page.goto('/');
                await expect(page.getByText('Feria del sábado')).toBeVisible();
                await expectNoViolations(page);
            });

            test('404', async ({ page }) => {
                const res = await page.goto('/l/no-existe-zz');
                expect(res.status()).toBe(404);
                await expectNoViolations(page);
            });

            test('503', async ({ page }) => {
                await stubPage(page, '/__503', html503, 503);
                await page.goto('/__503');
                await expect(page.getByRole('link', { name: 'Reintentar' })).toBeVisible();
                await expectNoViolations(page);
            });

            test('offline', async ({ page }) => {
                await page.goto('/offline');
                await expect(page.getByRole('link', { name: 'Reintentar' })).toBeVisible();
                await expectNoViolations(page);
            });

            test('500', async ({ page }) => {
                await stubPage(page, '/__500', html500, 500);
                await page.goto('/__500');
                await expect(page.getByRole('link', { name: 'Reintentar' })).toBeVisible();
                await expectNoViolations(page);
            });
        });
    }
});

// Contrast audit in dark: the list view, by state.

async function seededList(request) {
    const { slug } = await (await request.post('/api/lists', { data: { name: 'Feria del sábado' } })).json();
    const longName = 'Aceite de oliva extra virgen de primera prensada en frío, botella grande de vidrio oscuro'; // 90 chars, max is 100
    const items = [
        { name: 'Pan', quantity: '2 kg', price: '12.50', added_by: 'Ana' },
        { name: 'Leche', price: '8.00' },
        { name: 'Lentejas', quantity: '1 bolsa' },
        { name: longName },
    ];

    for (const data of items) {
        const res = await request.post(`/api/lists/${slug}/items`, { data });
        expect(res.ok()).toBeTruthy();

        if (data.name === 'Pan') {
            const bought = await request.patch(`/api/lists/${slug}/items/${(await res.json()).id}`, { data: { is_purchased: true } });
            expect(bought.ok()).toBeTruthy();
        }
    }

    return slug;
}

async function openList(page, slug) {
    await page.goto(`/l/${slug}`);
    await expect(page.locator('#client-item-list')).toBeVisible();
}

test.describe('axe in dark: list', () => {
    test.use({ colorScheme: 'dark' });

    for (const vp of VIEWPORTS) {
        test.describe(vp.name, () => {
            test.use({ viewport: { width: vp.width, height: vp.height } });

            test('empty', async ({ page, request }) => {
                const { slug } = await (await request.post('/api/lists', { data: { name: 'Vacía' } })).json();
                await openList(page, slug);
                await expectNoViolations(page);
            });

            test('with items, menu open and QR shown', async ({ page, request }) => {
                const slug = await seededList(request);
                await openList(page, slug);
                await expect(page.locator('#client-item-list li[data-item-id]')).toHaveCount(4);
                await expectNoViolations(page);

                await page.click('#list-menu-button');
                await expect(page.locator('#list-menu')).toBeVisible();
                await expectNoViolations(page);
                await page.keyboard.press('Escape');

                await page.click('#show-qr');
                await expect(page.locator('#qr-code canvas')).toBeVisible();
                await expectNoViolations(page);
            });

            test('suggestions with an active option', async ({ page, request }) => {
                const slug = await seededList(request);
                await openList(page, slug);
                const input = page.locator('#new-item');
                await input.fill('le');
                await expect(page.locator('#name-suggestions')).toBeVisible();
                await input.press('ArrowDown');
                await expect(page.locator('#name-suggestions [aria-selected="true"]')).toHaveCount(1);
                await expectNoViolations(page);
            });

            test('inline edits: item name, price, list name, currency, budget', async ({ page, request }) => {
                const slug = await seededList(request);
                await openList(page, slug);

                await page.getByRole('button', { name: 'Editar Leche' }).click();
                await expect(page.locator('#client-item-list input[data-edit-field]').first()).toBeVisible();
                // The inline name input has no label in light either (not a theme issue); only that rule is skipped.
                await expectNoViolations(page, { skipRules: ['label'] });
                await page.locator('#client-item-list input[data-edit-field="name"]').first().press('Escape');

                const leche = page.locator('#client-item-list li[data-item-id]', { hasText: 'Leche' });
                await leche.locator('button.item-price').click();
                const priceInput = leche.locator('input[id^="price-"]');
                await expect(priceInput).toBeVisible();
                await expectNoViolations(page);
                await priceInput.press('Escape');

                for (const [entry, field] of [['Renombrar', '#rename-input'], ['Moneda', '#currency-input'], ['Presupuesto', '#budget-input']]) {
                    await page.click('#list-menu-button');
                    await page.click(`#list-menu >> text=${entry}`);
                    await expect(page.locator(field)).toBeVisible();
                    await expectNoViolations(page);
                    // Not every editor handles Escape; reload to start each one from rest.
                    await openList(page, slug);
                }
            });
        });
    }
});

// The nine notices: each keeps its reserved light class and, in dark, gets a
// surface different from what is behind it. State is set on the component so
// no timer dismisses a notice while axe runs.

const NOTICES = [
    { name: 'error (red)', locator: '#list-app p.bg-red-50', set: (d) => { d.error = 'No se pudo guardar el cambio'; } },
    { name: 'offline (amber)', locator: '#list-app .bg-amber-50', offline: true },
    { name: 'copied (green)', locator: '#list-app p.bg-green-50', set: (d) => { d.shareNotice = 'Enlace copiado'; } },
    { name: 'clear URL (gray)', locator: '#list-app p.bg-gray-100', set: (d) => { d.shareUrl = 'http://127.0.0.1:8199/l/abc'; } },
    { name: 'export (teal)', locator: '#export-notice', set: (d) => { d.exportNotice = 'Lista copiada como texto'; } },
    { name: 'reset (sky)', locator: '#reset-undo', set: (d) => { d.resetUndo = true; } },
    { name: 'undo (blue)', locator: '#list-app div.bg-blue-50', set: (d) => { d.pendingUndo = { name: 'Pan' }; } },
    { name: 'budget (orange)', locator: '#budget-alert', budget: true },
    { name: 'delete confirmation', locator: '[role="alertdialog"]', set: (d) => { d.confirmingDelete = true; } },
];

// First opaque background walking up from the element.
const surfaceBehind = (locator) => locator.evaluate((el) => {
    const ctx = document.createElement('canvas').getContext('2d', { willReadFrequently: true });
    for (let node = el.parentElement; node; node = node.parentElement) {
        ctx.clearRect(0, 0, 1, 1);
        ctx.fillStyle = getComputedStyle(node).backgroundColor;
        ctx.fillRect(0, 0, 1, 1);
        const [r, g, b, a] = ctx.getImageData(0, 0, 1, 1).data;
        if (a === 255) return [r, g, b];
    }

    return [0, 0, 0];
});

test.describe('axe in dark: notices', () => {
    test.use({ colorScheme: 'dark' });

    for (const vp of VIEWPORTS) {
        for (const notice of NOTICES) {
            test(`${notice.name} at ${vp.name}`, async ({ page, context, request }) => {
                await page.setViewportSize({ width: vp.width, height: vp.height });
                const slug = await seededList(request);
                await openList(page, slug);

                if (notice.offline) {
                    await context.setOffline(true);
                } else if (notice.budget) {
                    await request.patch(`/api/lists/${slug}`, { data: { budget: '1' } });
                    await page.reload();
                } else {
                    await page.evaluate((fn) => {
                        // eslint-disable-next-line no-new-func
                        new Function('d', `(${fn})(d)`)(window.Alpine.$data(document.querySelector('#list-app')));
                    }, notice.set.toString());
                }

                const el = page.locator(notice.locator).first();
                await expect(el).toBeVisible();

                const own = await rgbOf(el, 'backgroundColor');
                if (notice.name !== 'delete confirmation') {
                    expect(own).not.toEqual(await surfaceBehind(el));
                }
                expect(own).not.toEqual([0, 0, 0]);
                // Own text tone, not inherited from the body.
                const text = await rgbOf(el, 'color');
                expect(text).not.toEqual(own);

                await page.addStyleTag({ content: '*, *::before, *::after { transition: none !important; animation: none !important; }' });
                const results = await new AxeBuilder({ page }).withTags(AXE_TAGS).include(notice.locator).analyze();
                expect(results.violations).toEqual([]);

                if (notice.offline) await context.setOffline(false);
            });
        }
    }
});

// States and keyboard in dark.

const luminance = ([r, g, b]) => {
    const [R, G, B] = [r, g, b].map((v) => {
        const c = v / 255;

        return c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4;
    });

    return 0.2126 * R + 0.7152 * G + 0.0722 * B;
};

const contrast = (a, b) => {
    const [hi, lo] = [luminance(a), luminance(b)].sort((x, y) => y - x);

    return (hi + 0.05) / (lo + 0.05);
};

async function focusOrder(page, steps) {
    await page.locator('body').click({ position: { x: 1, y: 1 } });
    const order = [];

    for (let i = 0; i < steps; i++) {
        await page.keyboard.press('Tab');
        order.push(await page.evaluate(() => {
            const el = document.activeElement;

            return `${el.tagName}|${el.getAttribute('aria-label') || el.id || el.getAttribute('name') || el.textContent.trim().slice(0, 20)}`;
        }));
    }

    return order;
}

test.describe('states and keyboard in dark', () => {
    test.use({ colorScheme: 'dark' });

    test('hover differs from rest on a menu entry and on the list link', async ({ page, request }) => {
        const slug = await seededList(request);
        await openList(page, slug);
        await page.click('#list-menu-button');

        const entry = page.locator('#list-menu button', { hasText: 'Renombrar' });
        const rest = await rgbOf(entry, 'backgroundColor');
        await entry.hover();
        expect(await rgbOf(entry, 'backgroundColor')).not.toEqual(rest);

        const menuButton = page.locator('#list-menu-button');
        await page.keyboard.press('Escape');
        const restButton = await rgbOf(menuButton, 'backgroundColor');
        await menuButton.hover();
        expect(await rgbOf(menuButton, 'backgroundColor')).not.toEqual(restButton);
    });

    test('the active suggestion differs from the others', async ({ page, request }) => {
        const slug = await seededList(request);
        await openList(page, slug);
        const input = page.locator('#new-item');
        await input.fill('le');
        await expect(page.locator('#name-suggestions')).toBeVisible();
        await input.press('ArrowDown');

        const options = page.locator('#name-suggestions li');
        const active = page.locator('#name-suggestions li[aria-selected="true"]');
        await expect(active).toHaveCount(1);
        const inactive = options.locator('xpath=self::*[not(@aria-selected="true")]').first();
        if (await inactive.count()) {
            expect(await rgbOf(active, 'backgroundColor')).not.toEqual(await rgbOf(inactive, 'backgroundColor'));
        }
        expect(await rgbOf(active, 'backgroundColor')).not.toEqual(await rgbOf(page.locator('#name-suggestions'), 'backgroundColor'));
    });

    test('a disabled move button has less contrast than an enabled one', async ({ page, request }) => {
        const slug = await seededList(request);
        await openList(page, slug);
        const row = page.locator('#client-item-list li[data-item-id]', { hasText: 'Leche' });
        const up = row.getByRole('button', { name: 'Subir Leche' });
        const down = row.getByRole('button', { name: 'Bajar Leche' });
        await expect(up).toBeDisabled();
        await expect(down).toBeEnabled();

        const surface = await surfaceBehind(up);
        const disabledRatio = contrast(await rgbOf(up, 'color'), surface);
        const enabledRatio = contrast(await rgbOf(down, 'color'), surface);
        expect(disabledRatio).toBeLessThan(enabledRatio);
    });

    test('keyboard focus ring has at least 3:1 against its surface', async ({ page, request }) => {
        const slug = await seededList(request);
        await openList(page, slug);
        await page.locator('#list-menu-button').focus();
        await page.keyboard.press('Shift+Tab');
        await page.keyboard.press('Tab');

        const menuButton = page.locator('#list-menu-button');
        await expect(menuButton).toBeFocused();
        const width = await menuButton.evaluate((el) => parseFloat(getComputedStyle(el).outlineWidth));
        expect(width).toBeGreaterThanOrEqual(2);
        expect(contrast(await rgbOf(menuButton, 'outlineColor'), await surfaceBehind(menuButton))).toBeGreaterThanOrEqual(3);

        const field = page.locator('#new-item');
        await field.focus();
        const fieldOutline = await field.evaluate((el) => parseFloat(getComputedStyle(el).outlineWidth));
        expect(fieldOutline).toBeGreaterThan(0);
        expect(contrast(await rgbOf(field, 'outlineColor'), await surfaceBehind(field))).toBeGreaterThanOrEqual(3);
    });

    test('touch targets keep 44 px', async ({ page, request }) => {
        const slug = await seededList(request);
        await page.setViewportSize({ width: 375, height: 800 });
        await openList(page, slug);

        const row = page.locator('#client-item-list li[data-item-id]', { hasText: 'Leche' });
        // "Agregar" (42 px, no `min-h-11`) is left out: its size is the same in light.
        const targets = [
            page.locator('#list-menu-button'),
            page.locator('#show-qr'),
            page.getByRole('button', { name: 'Compartir enlace' }),
            row.getByRole('button', { name: 'Eliminar Leche' }),
            row.getByRole('button', { name: 'Bajar Leche' }),
            row.getByRole('button', { name: 'Mover Leche' }),
            row.locator('button.item-price'),
        ];

        for (const target of targets) {
            const box = await target.boundingBox();
            expect(box, 'target is visible').not.toBeNull();
            expect(box.height).toBeGreaterThanOrEqual(44);
            // Text buttons only promise height (`min-h-11`); icon buttons also width.
            if (await target.evaluate((el) => el.classList.contains('min-w-11') || el.classList.contains('size-11'))) {
                expect(box.width).toBeGreaterThanOrEqual(44);
            }
        }
    });
});

test('Tab order is the same in dark and in light', async ({ browser, request }) => {
    const slug = await seededList(request);
    const orders = {};

    for (const scheme of ['light', 'dark']) {
        const context = await browser.newContext({ colorScheme: scheme });
        const page = await context.newPage();
        await openList(page, slug);
        orders[scheme] = await focusOrder(page, 30);
        await context.close();
    }

    expect(orders.dark).toEqual(orders.light);
    expect(new Set(orders.dark).size).toBeGreaterThan(10);
});
