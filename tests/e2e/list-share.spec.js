import { expect, test } from '@playwright/test';

// list.js share action: native share sheet, else copy to the clipboard with a
// notice, else show the URL in clear. All client-side, works offline, and a
// cancelled sheet is not an error.

async function createList(request, name = 'Feria del sábado') {
    const res = await request.post('/api/lists', { data: { name } });
    expect(res.ok()).toBeTruthy();

    return (await res.json()).slug;
}

const shareButton = (page) => page.getByRole('button', { name: 'Compartir enlace' });

test('uses the native share sheet with the list URL', async ({ page, request }) => {
    const slug = await createList(request);
    await page.addInitScript(() => {
        window.__shared = [];
        navigator.share = (data) => {
            window.__shared.push(data);

            return Promise.resolve();
        };
    });
    await page.goto(`/l/${slug}`);
    await expect(page.locator('#client-item-list')).toBeVisible();

    await shareButton(page).click();

    const shared = await page.evaluate(() => window.__shared);
    expect(shared).toHaveLength(1);
    expect(shared[0].url).toContain(`/l/${slug}`);
});

test('without a share sheet, copies the URL and shows a notice', async ({ page, request }) => {
    const slug = await createList(request);
    await page.addInitScript(() => {
        window.__copied = [];
        Object.defineProperty(navigator, 'share', { configurable: true, value: undefined });
        Object.defineProperty(navigator, 'clipboard', {
            configurable: true,
            value: { writeText: (text) => { window.__copied.push(text); return Promise.resolve(); } },
        });
    });
    await page.goto(`/l/${slug}`);
    await expect(page.locator('#client-item-list')).toBeVisible();

    await shareButton(page).click();

    await expect(page.locator('#list-app .bg-green-50')).toContainText('Enlace copiado');
    const copied = await page.evaluate(() => window.__copied);
    expect(copied[0]).toContain(`/l/${slug}`);
});

test('with neither share nor clipboard, shows the URL in clear', async ({ page, request }) => {
    const slug = await createList(request);
    await page.addInitScript(() => {
        Object.defineProperty(navigator, 'share', { configurable: true, value: undefined });
        Object.defineProperty(navigator, 'clipboard', {
            configurable: true,
            value: { writeText: () => Promise.reject(new Error('no clipboard')) },
        });
    });
    await page.goto(`/l/${slug}`);
    await expect(page.locator('#client-item-list')).toBeVisible();

    await shareButton(page).click();

    await expect(page.locator('#list-app .bg-gray-100')).toContainText(`/l/${slug}`);
});

test('cancelling the share sheet is not an error', async ({ page, request }) => {
    const slug = await createList(request);
    await page.addInitScript(() => {
        navigator.share = () => Promise.reject(Object.assign(new Error('cancel'), { name: 'AbortError' }));
    });
    await page.goto(`/l/${slug}`);
    await expect(page.locator('#client-item-list')).toBeVisible();

    await shareButton(page).click();
    await page.waitForTimeout(300);

    await expect(page.locator('#list-app p[role="alert"]')).toBeHidden();
    await expect(page.locator('#list-app .bg-green-50')).toBeHidden();
});

test('works with the network down', async ({ page, context, request }) => {
    const slug = await createList(request);
    await page.addInitScript(() => {
        window.__shared = [];
        navigator.share = (data) => { window.__shared.push(data); return Promise.resolve(); };
    });
    await page.goto(`/l/${slug}`);
    await expect(page.locator('#client-item-list')).toBeVisible();

    await context.setOffline(true);
    await shareButton(page).click();

    expect(await page.evaluate(() => window.__shared)).toHaveLength(1);
});
