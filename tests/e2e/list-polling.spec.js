import { expect, test } from '@playwright/test';

// list.js polling: every 3-4 s the client asks `sync` for changes since the
// last cursor, merges items by id, drops the tombstoned ones, re-sorts by the
// server order, pauses while the tab is hidden and catches up immediately on
// return, and shows a notice when `sync` 404s.

async function createList(request, name = 'Feria del sábado') {
    const res = await request.post('/api/lists', { data: { name } });
    expect(res.ok()).toBeTruthy();

    return (await res.json()).slug;
}

const SYNC_RE = /\/api\/lists\/[^/]+\/items\?cursor=/;

const setHidden = (page, hidden) =>
    page.evaluate((h) => {
        Object.defineProperty(document, 'visibilityState', { configurable: true, get: () => (h ? 'hidden' : 'visible') });
        Object.defineProperty(document, 'hidden', { configurable: true, get: () => h });
        document.dispatchEvent(new Event('visibilitychange'));
    }, hidden);

test('a change made by another client shows up within 5 s', async ({ page, request }) => {
    const slug = await createList(request);
    await page.goto(`/l/${slug}`);
    await expect(page.locator('#client-item-list')).toBeVisible();

    await request.post(`/api/lists/${slug}/items`, { data: { name: 'Aguacate' } });

    await expect(page.locator('#client-item-list li[data-item-id]', { hasText: 'Aguacate' }))
        .toBeVisible({ timeout: 5000 });
});

test('an item deleted elsewhere disappears from the open list', async ({ page, request }) => {
    const slug = await createList(request);
    const created = await request.post(`/api/lists/${slug}/items`, { data: { name: 'Servilletas' } });
    const itemId = (await created.json()).id;

    await page.goto(`/l/${slug}`);
    await expect(page.locator('#client-item-list li[data-item-id]', { hasText: 'Servilletas' })).toBeVisible();

    await request.delete(`/api/lists/${slug}/items/${itemId}`);

    await expect(page.locator('#client-item-list li[data-item-id]', { hasText: 'Servilletas' }))
        .toHaveCount(0, { timeout: 5000 });
});

test('hiding the tab pauses polling and returning triggers an immediate sync', async ({ page, request }) => {
    const slug = await createList(request);
    await page.goto(`/l/${slug}`);
    await expect(page.locator('#client-item-list')).toBeVisible();

    let syncs = 0;
    page.on('request', (r) => {
        if (r.method() === 'GET' && SYNC_RE.test(r.url())) {
            syncs += 1;
        }
    });

    await setHidden(page, true);

    await request.post(`/api/lists/${slug}/items`, { data: { name: 'Oculto' } });
    await page.waitForTimeout(5000);

    expect(syncs).toBe(0); // no polling while hidden
    await expect(page.locator('#client-item-list li[data-item-id]', { hasText: 'Oculto' })).toHaveCount(0);

    await setHidden(page, false);

    // Under 3 s: proves the sync was immediate, not the next scheduled tick.
    await expect(page.locator('#client-item-list li[data-item-id]', { hasText: 'Oculto' }))
        .toBeVisible({ timeout: 2500 });
    expect(syncs).toBeGreaterThan(0);
});

test('a list deleted from another device shows the "no longer exists" notice', async ({ page, request }) => {
    const slug = await createList(request);
    await page.goto(`/l/${slug}`);
    await expect(page.locator('#client-item-list')).toBeVisible();

    await request.delete(`/api/lists/${slug}`);

    await expect(page.locator('#list-app p[role="alert"]')).toContainText('ya no existe', { timeout: 6000 });
});
