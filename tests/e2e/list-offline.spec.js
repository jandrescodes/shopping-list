import { expect, test } from '@playwright/test';

// T31 -- list.js offline behaviour (RF-26): the last known list stays visible
// with no network, writes fail with a notice and are NOT queued, and the poll
// recovers the real state once the connection is back.

async function createList(request, name = 'Feria del sábado') {
    const res = await request.post('/api/lists', { data: { name } });
    expect(res.ok()).toBeTruthy();

    return (await res.json()).slug;
}

test('offline: the list stays visible and a write fails without being queued', async ({ page, context, request }) => {
    const slug = await createList(request);
    await request.post(`/api/lists/${slug}/items`, { data: { name: 'Pan' } });
    await page.goto(`/l/${slug}`);
    await expect(page.locator('#client-item-list li[data-item-id]', { hasText: 'Pan' })).toBeVisible();

    await context.setOffline(true);

    // Last known state stays on screen, with an offline banner.
    await expect(page.locator('#list-app .bg-amber-50')).toContainText('Sin conexión');
    await expect(page.locator('#client-item-list li[data-item-id]', { hasText: 'Pan' })).toBeVisible();

    await page.fill('#new-item', 'Leche');
    await page.click('button[type="submit"]:has-text("Agregar")');

    await expect(page.locator('#list-app p[role="alert"]')).toContainText('Sin conexión');
    await expect(page.locator('#client-item-list li[data-item-id]', { hasText: 'Leche' })).toHaveCount(0);
    // The typed text is neither lost nor silently queued.
    await expect(page.locator('#new-item')).toHaveValue('Leche');

    await context.setOffline(false);
    const check = await request.get(`/api/lists/${slug}`);
    expect((await check.json()).items.map((item) => item.name)).toEqual(['Pan']);
});

test('reconnecting resumes the poll and clears the offline notice', async ({ page, context, request }) => {
    const slug = await createList(request);
    await page.goto(`/l/${slug}`);
    await expect(page.locator('#client-item-list')).toBeVisible();

    await context.setOffline(true);
    await page.fill('#new-item', 'Sal');
    await page.click('button[type="submit"]:has-text("Agregar")');
    await expect(page.locator('#list-app p[role="alert"]')).toContainText('Sin conexión');

    // Another device adds an item while this one is offline.
    const added = await request.post(`/api/lists/${slug}/items`, { data: { name: 'Azúcar' } });
    expect(added.ok()).toBeTruthy();

    await context.setOffline(false);

    // The `online` event kicks an immediate sync that pulls the missed change.
    await expect(page.locator('#client-item-list li[data-item-id]', { hasText: 'Azúcar' }))
        .toBeVisible({ timeout: 4000 });
    await expect(page.locator('#list-app p[role="alert"]')).toBeHidden();
    await expect(page.locator('#list-app .bg-amber-50')).toBeHidden();

    // Writes work again.
    await page.fill('#new-item', 'Sal');
    await page.click('button[type="submit"]:has-text("Agregar")');
    await expect(page.locator('#client-item-list li[data-item-id]', { hasText: 'Sal' })).toBeVisible();
});
