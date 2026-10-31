import AxeBuilder from '@axe-core/playwright';
import { expect, test } from '@playwright/test';

// list.js QR: the code of the list's public URL, generated in the client with
// a <canvas> via the `qrcode` library (no API call). Another phone scans it
// instead of typing the slug.

async function createList(request, name = 'Feria del sábado') {
    const res = await request.post('/api/lists', { data: { name } });
    expect(res.ok()).toBeTruthy();

    return (await res.json()).slug;
}

test('shows a centered scannable QR of the absolute URL', async ({ page, request }) => {
    const slug = await createList(request);
    await page.goto(`/l/${slug}`);
    await expect(page.locator('#client-item-list')).toBeVisible();

    // The QR button sits next to the existing "Compartir enlace" in the header.
    const button = page.locator('#show-qr');
    await expect(button).toBeVisible();
    await expect(page.getByRole('button', { name: 'Compartir enlace' })).toBeVisible();

    // Hidden until the button is pressed.
    await expect(page.locator('#qr-code')).toBeHidden();

    await button.click();

    const qr = page.locator('#qr-code');
    await expect(qr).toBeVisible();
    await expect(qr.locator('xpath=..')).toHaveClass(/justify-center/);

    // The canvas encodes the absolute URL of the list.
    const canvas = qr.locator('canvas');
    await expect(canvas).toBeVisible();
    const encoded = await canvas.getAttribute('data-qr-encoded');
    expect(encoded).toBe(`${page.url().split('/l/')[0]}/l/${slug}`);

    // Wait for the async `toCanvas` to paint before reading pixels.
    await page.waitForFunction(() => {
        const el = document.querySelector('#qr-code canvas');

        return el && el.width > 0;
    });

    // The canvas actually has content: both dark and light pixels.
    const hasInk = await canvas.evaluate((el) => {
        const ctx = el.getContext('2d');
        const { data } = ctx.getImageData(0, 0, el.width, el.height);
        let dark = 0;
        let light = 0;

        for (let i = 0; i < data.length; i += 4) {
            if (data[i] < 128) dark++;
            else light++;
        }

        return dark > 0 && light > 0;
    });
    expect(hasInk).toBe(true);

    await expect(button).toHaveText('Ocultar QR');
    await expect(button).toHaveAttribute('aria-expanded', 'true');
    await expect(qr).not.toContainText(`/l/${slug}`);
});

test('closing the QR returns to the list without a reload (RF-3)', async ({ page, request }) => {
    const slug = await createList(request);
    await page.goto(`/l/${slug}`);
    await expect(page.locator('#client-item-list')).toBeVisible();

    const button = page.locator('#show-qr');
    const qr = page.locator('#qr-code');

    await button.click();
    await expect(qr).toBeVisible();

    // A reload swaps the window object, so this marker only survives if the
    // view was closed in place instead of navigating.
    await page.evaluate(() => {
        window.__qrNoReloadMarker = true;
    });

    await button.click();
    await expect(qr).toBeHidden();
    await expect(button).toHaveText('Mostrar QR');
    await expect(button).toHaveAttribute('aria-expanded', 'false');

    // Back on the list: same URL, same page, no navigation.
    await expect(page.locator('#client-item-list')).toBeVisible();
    expect(page.url()).toContain(`/l/${slug}`);
    expect(await page.evaluate(() => window.__qrNoReloadMarker === true)).toBe(true);
});

test('a rename from another device regenerates the open QR (RF-5)', async ({ page, request }) => {
    const slug = await createList(request, 'Feria');
    await page.goto(`/l/${slug}`);
    await expect(page.locator('#client-item-list')).toBeVisible();

    const qr = page.locator('#qr-code');
    const canvas = qr.locator('canvas');

    await page.locator('#show-qr').click();
    await expect(qr).toBeVisible();

    const renders = async () => Number(await canvas.getAttribute('data-qr-renders')) || 0;
    await expect.poll(renders).toBe(1);

    // Another device renames the list; it arrives by polling, not reload.
    await request.patch(`/api/lists/${slug}`, { data: { name: 'Feria grande' } });

    await expect(page.locator('#list-app h1')).toHaveText('Feria grande', { timeout: 6000 });

    // The open code was drawn again instead of being left as it was.
    await expect.poll(renders).toBe(2);
    await expect(qr).toBeVisible();
    expect(page.url()).toContain(`/l/${slug}`);
});

test('the share button and its notices still work (no regression)', async ({ page, request }) => {
    const slug = await createList(request);
    await page.addInitScript(() => {
        Object.defineProperty(navigator, 'share', { configurable: true, value: undefined });
        Object.defineProperty(navigator, 'clipboard', {
            configurable: true,
            value: { writeText: () => Promise.resolve() },
        });
    });
    await page.goto(`/l/${slug}`);
    await expect(page.locator('#client-item-list')).toBeVisible();

    await page.getByRole('button', { name: 'Compartir enlace' }).click();

    // The pre-existing green notice still resolves to exactly one element.
    await expect(page.locator('#list-app .bg-green-50')).toContainText('Enlace copiado');
});

for (const viewport of [
    { width: 375, height: 667 },
    { width: 1280, height: 800 },
]) {
    test(`sharing controls are accessible at ${viewport.width}px and keyboard operable`, async ({ page, request }) => {
        await page.setViewportSize(viewport);
        const slug = await createList(request);

        await page.goto(`/l/${slug}`);
        await expect(page.locator('#client-item-list')).toBeVisible();

        const shareButton = page.getByRole('button', { name: 'Compartir enlace' });
        const qrButton = page.locator('#show-qr');

        await qrButton.focus();
        await expect(qrButton).toBeFocused();
        await expect(qrButton).toHaveAttribute('aria-expanded', 'false');

        const qrButtonBox = await qrButton.boundingBox();
        expect(qrButtonBox?.width).toBeGreaterThanOrEqual(44);
        expect(qrButtonBox?.height).toBeGreaterThanOrEqual(44);

        await page.keyboard.press('Enter');
        await expect(qrButton).toHaveText('Ocultar QR');
        await expect(qrButton).toBeFocused();

        const results = await new AxeBuilder({ page })
            .include('#show-qr')
            .include('#qr-code')
            .analyze();
        expect(results.violations).toEqual([]);

        await page.keyboard.press('Enter');
        await expect(qrButton).toHaveText('Mostrar QR');
        await expect(qrButton).toHaveAttribute('aria-expanded', 'false');
        await expect(qrButton).toBeFocused();
    });
}
