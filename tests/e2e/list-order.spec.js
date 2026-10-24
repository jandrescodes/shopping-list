import { expect, test } from '@playwright/test';

// Manual ordering: only pending rows can be dragged, always by their handle,
// and the index the drag plugin reports counts pending rows only.

async function listWith(request, rows) {
    const { slug } = await (await request.post('/api/lists', { data: { name: 'Orden' } })).json();
    const ids = {};

    for (const [name, purchased] of rows) {
        const res = await request.post(`/api/lists/${slug}/items`, { data: { name } });
        const item = await res.json();
        ids[name] = item.id;
        if (purchased) {
            await request.patch(`/api/lists/${slug}/items/${item.id}`, { data: { is_purchased: true } });
        }
    }

    return { slug, ids };
}

const handle = (page, name) => page.locator('#client-item-list').getByRole('button', { name: `Mover ${name}` });
const movesSent = (page) => {
    const sent = [];
    page.on('request', (r) => {
        if (r.method() === 'PATCH' && r.url().endsWith('/position')) {
            sent.push(r.postDataJSON());
        }
    });

    return sent;
};

test('dragging a pending row sends where it landed, announces it and survives a reload', async ({ page, request }) => {
    const { slug, ids } = await listWith(request, [['Leche', false], ['Pan', false], ['Arroz', false]]);
    await page.goto(`/l/${slug}`);
    const rows = page.locator('#client-item-list > li[data-item-id]');
    await expect(rows).toHaveCount(3);
    const sent = movesSent(page);

    await handle(page, 'Arroz').dragTo(rows.nth(0), { targetPosition: { x: 40, y: 5 } });

    await expect.poll(() => names(page)).toEqual(['Arroz', 'Leche', 'Pan']);
    await expect.poll(() => sent).toEqual([{ before_id: ids.Leche }]);
    await expect(page.locator('#move-announcement')).toHaveText('Arroz: posición 1 de 3');

    await page.reload();
    await expect.poll(() => names(page)).toEqual(['Arroz', 'Leche', 'Pan']);
});

test('dropping a row last among the pending sends a null neighbour', async ({ page, request }) => {
    const { slug } = await listWith(request, [['Leche', false], ['Pan', false], ['Arroz', true]]);
    await page.goto(`/l/${slug}`);
    const rows = page.locator('#client-item-list > li[data-item-id]');
    await expect(rows).toHaveCount(3);
    const sent = movesSent(page);

    // Dropping onto the purchased zone must clamp to the end of the pending ones.
    await handle(page, 'Leche').dragTo(rows.nth(2), { targetPosition: { x: 40, y: 40 } });

    await expect.poll(() => sent).toEqual([{ before_id: null }]);
    await expect.poll(() => names(page)).toEqual(['Pan', 'Leche', 'Arroz']);
    await expect(page.locator('#move-announcement')).toHaveText('Leche: posición 2 de 2');
    await page.reload();
    await expect.poll(() => names(page)).toEqual(['Pan', 'Leche', 'Arroz']);
});

test('a single pending row shows no move controls', async ({ page, request }) => {
    const { slug } = await listWith(request, [['Leche', false], ['Arroz', true]]);
    await page.goto(`/l/${slug}`);
    await expect(page.locator('#client-item-list > li[data-item-id]')).toHaveCount(2);

    await expect(handle(page, 'Leche')).toBeHidden();
});

test('a purchased row has no handle and dragging it sends nothing', async ({ page, request }) => {
    const { slug } = await listWith(request, [['Leche', false], ['Pan', false], ['Arroz', true]]);
    await page.goto(`/l/${slug}`);
    const rows = page.locator('#client-item-list > li[data-item-id]');
    await expect(rows).toHaveCount(3);
    const sent = movesSent(page);

    await expect(handle(page, 'Arroz')).toBeHidden();
    await rows.nth(2).dragTo(rows.nth(0), { sourcePosition: { x: 60, y: 20 } });
    await page.waitForTimeout(800);

    expect(sent).toEqual([]);
    await expect.poll(() => names(page)).toEqual(['Leche', 'Pan', 'Arroz']);
});

const names = (page) => page.locator('#client-item-list > li[data-item-id]').allInnerTexts()
    .then((texts) => texts.map((text) => ['Leche', 'Pan', 'Arroz', 'Huevos'].find((name) => text.includes(name))));

test('the client loads the list in the server position order, not creation order', async ({ page, request }) => {
    const { slug, ids } = await listWith(request, [['Leche', false], ['Pan', false], ['Arroz', false]]);
    await request.patch(`/api/lists/${slug}/items/${ids.Arroz}/position`, { data: { before_id: ids.Leche } });

    await page.goto(`/l/${slug}`);
    await expect(page.locator('#client-item-list > li[data-item-id]')).toHaveCount(3);

    await expect.poll(() => names(page)).toEqual(['Arroz', 'Leche', 'Pan']);
});

test('a reorder made by another client shows up after polling', async ({ page, request }) => {
    const { slug, ids } = await listWith(request, [['Leche', false], ['Pan', false], ['Arroz', false]]);
    await page.goto(`/l/${slug}`);
    await expect.poll(() => names(page)).toEqual(['Leche', 'Pan', 'Arroz']);

    await request.patch(`/api/lists/${slug}/items/${ids.Arroz}/position`, { data: { before_id: ids.Leche } });

    await expect.poll(() => names(page), { timeout: 6000 }).toEqual(['Arroz', 'Leche', 'Pan']);
});

test('marking and unmarking an item keeps its position', async ({ page, request }) => {
    const { slug, ids } = await listWith(request, [['Leche', false], ['Pan', false], ['Arroz', false]]);
    await request.patch(`/api/lists/${slug}/items/${ids.Arroz}/position`, { data: { before_id: ids.Leche } });
    await page.goto(`/l/${slug}`);
    await expect.poll(() => names(page)).toEqual(['Arroz', 'Leche', 'Pan']);

    const row = page.locator('#client-item-list > li[data-item-id]', { hasText: 'Leche' });
    await row.getByRole('checkbox').click();
    await expect.poll(() => names(page)).toEqual(['Arroz', 'Pan', 'Leche']);

    await row.getByRole('checkbox').click();
    await expect.poll(() => names(page)).toEqual(['Arroz', 'Leche', 'Pan']);
});

const errorBanner = (page) => page.locator('#list-app p.bg-red-50');

test('the up and down buttons move a row from the keyboard and keep the focus', async ({ page, request }) => {
    const { slug } = await listWith(request, [['Leche', false], ['Pan', false], ['Arroz', false]]);
    await page.goto(`/l/${slug}`);
    await expect(page.locator('#client-item-list > li[data-item-id]')).toHaveCount(3);

    const up = page.getByRole('button', { name: 'Subir Arroz' });
    await up.focus();
    await page.keyboard.press('Enter');

    await expect.poll(() => names(page)).toEqual(['Leche', 'Arroz', 'Pan']);
    await expect(page.locator('#move-announcement')).toHaveText('Arroz: posición 2 de 3');
    await expect(up).toBeFocused();

    await page.getByRole('button', { name: 'Bajar Arroz' }).click();
    await expect.poll(() => names(page)).toEqual(['Leche', 'Pan', 'Arroz']);

    await expect(page.getByRole('button', { name: 'Subir Leche' })).toBeDisabled();
    await expect(page.getByRole('button', { name: 'Bajar Arroz' })).toBeDisabled();
    await page.reload();
    await expect.poll(() => names(page)).toEqual(['Leche', 'Pan', 'Arroz']);
});

test('several quick presses on one row become a single request with the final destination', async ({ page, request }) => {
    const { slug, ids } = await listWith(request, [['Leche', false], ['Pan', false], ['Arroz', false]]);
    await page.goto(`/l/${slug}`);
    await expect(page.locator('#client-item-list > li[data-item-id]')).toHaveCount(3);
    const sent = movesSent(page);

    const up = page.getByRole('button', { name: 'Subir Arroz' });
    await up.click();
    await up.click();

    await expect.poll(() => names(page)).toEqual(['Arroz', 'Leche', 'Pan']);
    await expect.poll(() => sent.length).toBe(1);
    await page.waitForTimeout(800);
    expect(sent).toEqual([{ before_id: ids.Leche }]);
});

test('moving a row another device deleted resyncs without showing an error', async ({ page, request }) => {
    const { slug, ids } = await listWith(request, [['Leche', false], ['Pan', false], ['Arroz', false]]);
    await page.goto(`/l/${slug}`);
    await expect(page.locator('#client-item-list > li[data-item-id]')).toHaveCount(3);

    await request.delete(`/api/lists/${slug}/items/${ids.Pan}`);
    await page.getByRole('button', { name: 'Bajar Pan' }).click();

    await expect.poll(() => names(page)).toEqual(['Leche', 'Arroz']);
    await expect(errorBanner(page)).toBeHidden();
});

test('a throttled move goes back to the server order and shows the existing notice', async ({ page, request }) => {
    const { slug } = await listWith(request, [['Leche', false], ['Pan', false], ['Arroz', false]]);
    await page.goto(`/l/${slug}`);
    await expect(page.locator('#client-item-list > li[data-item-id]')).toHaveCount(3);
    await page.route('**/position', (route) => route.fulfill({
        status: 429,
        headers: { 'Retry-After': '30' },
        contentType: 'application/json',
        body: '{"message":"Too Many Attempts."}',
    }));

    await page.getByRole('button', { name: 'Subir Arroz' }).click();

    await expect(errorBanner(page)).toContainText('Demasiadas');
    await expect.poll(() => names(page)).toEqual(['Leche', 'Pan', 'Arroz']);
});

const rowOf = (page, name) => page.locator('#client-item-list > li[data-item-id]', { hasText: name });

test('undoing a delete puts the item back in its old place', async ({ page, request }) => {
    const { slug } = await listWith(request, [['Leche', false], ['Pan', false], ['Arroz', false]]);
    await page.goto(`/l/${slug}`);
    await expect.poll(() => names(page)).toEqual(['Leche', 'Pan', 'Arroz']);

    await rowOf(page, 'Pan').getByRole('button', { name: 'Eliminar Pan' }).click();
    await expect.poll(() => names(page)).toEqual(['Leche', 'Arroz']);
    await page.getByRole('button', { name: 'Deshacer' }).click();

    await expect.poll(() => names(page)).toEqual(['Leche', 'Pan', 'Arroz']);
    await page.reload();
    await expect.poll(() => names(page)).toEqual(['Leche', 'Pan', 'Arroz']);
});

test('undoing a delete whose next neighbour is gone leaves the item last', async ({ page, request }) => {
    const { slug, ids } = await listWith(request, [['Leche', false], ['Pan', false], ['Arroz', false], ['Huevos', false]]);
    await page.goto(`/l/${slug}`);
    await expect.poll(() => names(page)).toEqual(['Leche', 'Pan', 'Arroz', 'Huevos']);

    await rowOf(page, 'Pan').getByRole('button', { name: 'Eliminar Pan' }).click();
    await request.delete(`/api/lists/${slug}/items/${ids.Arroz}`);
    await page.getByRole('button', { name: 'Deshacer' }).click();

    await expect.poll(() => names(page)).toEqual(['Leche', 'Huevos', 'Pan']);
});

test('undoing the delete of a purchased item keeps its place among the purchased', async ({ page, request }) => {
    const { slug } = await listWith(request, [['Leche', false], ['Pan', true], ['Arroz', true]]);
    await page.goto(`/l/${slug}`);
    await expect.poll(() => names(page)).toEqual(['Leche', 'Pan', 'Arroz']);

    await rowOf(page, 'Pan').getByRole('button', { name: 'Eliminar Pan' }).click();
    await page.getByRole('button', { name: 'Deshacer' }).click();

    await expect.poll(() => names(page)).toEqual(['Leche', 'Pan', 'Arroz']);
    await expect(page.getByRole('checkbox', { name: 'Desmarcar Pan' })).toBeChecked();
});

test('resetting the purchases and undoing the reset keep the manual order', async ({ page, request }) => {
    const { slug, ids } = await listWith(request, [['Leche', false], ['Pan', false], ['Arroz', false]]);
    await request.patch(`/api/lists/${slug}/items/${ids.Arroz}/position`, { data: { before_id: ids.Leche } });
    for (const name of ['Arroz', 'Pan']) {
        await request.patch(`/api/lists/${slug}/items/${ids[name]}`, { data: { is_purchased: true } });
    }
    await page.goto(`/l/${slug}`);
    await expect.poll(() => names(page)).toEqual(['Leche', 'Arroz', 'Pan']);

    await page.click('#list-menu-button');
    await page.click('#list-menu >> text=Reiniciar compras');
    await expect.poll(() => names(page)).toEqual(['Arroz', 'Leche', 'Pan']);

    await page.click('#reset-undo >> text=Deshacer');
    await expect.poll(() => names(page)).toEqual(['Leche', 'Arroz', 'Pan']);
});

test('a keyboard pass reaches the handle and both move buttons in order, each with a visible focus ring', async ({ page, request }) => {
    const { slug } = await listWith(request, [['Leche', false], ['Pan', false], ['Arroz', false]]);
    await page.goto(`/l/${slug}`);
    await expect(page.locator('#client-item-list > li[data-item-id]')).toHaveCount(3);

    await page.getByRole('checkbox', { name: 'Marcar Pan' }).focus();
    const seen = [];
    for (let i = 0; i < 8 && !seen.includes('Bajar Pan'); i++) {
        await page.keyboard.press('Tab');
        seen.push(await page.evaluate(() => document.activeElement.getAttribute('aria-label') ?? ''));
    }

    const moves = seen.filter((label) => /^(Mover|Subir|Bajar) Pan$/.test(label));
    expect(moves).toEqual(['Mover Pan', 'Subir Pan', 'Bajar Pan']);

    const outline = await page.evaluate(() => getComputedStyle(document.activeElement).outlineStyle);
    expect(outline).not.toBe('none');
});

test.describe('touch viewport', () => {
    test.use({ hasTouch: true, isMobile: true, viewport: { width: 375, height: 667 } });

    // Raw touch events: the synthesized scroll gesture of the protocol does
    // not scroll in headless Chromium, even on a plain page.
    const swipe = async (page, x, fromY, toY) => {
        const cdp = await page.context().newCDPSession(page);
        const touch = (type, y) => cdp.send('Input.dispatchTouchEvent', {
            type,
            touchPoints: type === 'touchEnd' ? [] : [{ x, y }],
        });
        const step = fromY < toY ? 20 : -20;

        await touch('touchStart', fromY);
        for (let y = fromY; step > 0 ? y < toY : y > toY; y += step) {
            await touch('touchMove', y);
        }
        await touch('touchMove', toY);
        await touch('touchEnd', toY);
    };

    test('a vertical swipe on a row scrolls the page instead of dragging; the handle never scrolls', async ({ page, request }) => {
        const rows = Array.from({ length: 14 }, (_, i) => [`Item ${String(i).padStart(2, '0')}`, false]);
        const { slug } = await listWith(request, rows);
        await page.goto(`/l/${slug}`);
        await expect(page.locator('#client-item-list > li[data-item-id]')).toHaveCount(14);
        const sent = movesSent(page);

        const box = await page.locator('#client-item-list > li[data-item-id]').nth(2).boundingBox();
        await swipe(page, box.x + 120, box.y + box.height / 2, box.y + box.height / 2 - 300);

        await expect.poll(() => page.evaluate(() => window.scrollY)).toBeGreaterThan(100);
        await page.waitForTimeout(600);
        expect(sent).toEqual([]);

        const touchAction = await page.locator('#client-item-list button[aria-label^="Mover"]').first()
            .evaluate((el) => getComputedStyle(el).touchAction);
        expect(touchAction).toBe('none');
    });

    test('dragging the handle with a finger reorders the row and does not scroll the page', async ({ page, request }) => {
        const { slug } = await listWith(request, [['Leche', false], ['Pan', false], ['Arroz', false]]);
        await page.goto(`/l/${slug}`);
        await expect.poll(() => names(page)).toEqual(['Leche', 'Pan', 'Arroz']);

        const from = await handle(page, 'Arroz').boundingBox();
        const to = await page.locator('#client-item-list > li[data-item-id]').first().boundingBox();
        await swipe(page, from.x + from.width / 2, from.y + from.height / 2, to.y + 4);

        await expect.poll(() => names(page)).toEqual(['Arroz', 'Leche', 'Pan']);
        expect(await page.evaluate(() => window.scrollY)).toBe(0);
    });

    test('the row buttons stay at least 44 px tall and wide on a phone', async ({ page, request }) => {
        const { slug } = await listWith(request, [['Leche', false], ['Pan', false], ['Arroz', false]]);
        await page.goto(`/l/${slug}`);
        await expect(page.locator('#client-item-list > li[data-item-id]')).toHaveCount(3);

        for (const label of ['Mover Pan', 'Subir Pan', 'Bajar Pan', 'Eliminar Pan']) {
            const button = page.locator('#client-item-list').getByRole('button', { name: label });
            if (await button.count() === 0) {
                continue;
            }
            const box = await button.boundingBox();
            expect(box.width, label).toBeGreaterThanOrEqual(44);
            expect(box.height, label).toBeGreaterThanOrEqual(44);
        }
        await expect(page.locator('#client-item-list').getByRole('button', { name: 'Subir Pan' })).toBeVisible();
    });
});
