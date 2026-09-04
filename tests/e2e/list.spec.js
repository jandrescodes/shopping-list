import { expect, test } from '@playwright/test';

// list.js core: initial load via `show`, reactive rendering with x-text,
// add / mark / delete that wait for the API before touching the view, and
// each edit sending only the changed fields.

async function createList(request, name = 'Feria del sábado') {
    const res = await request.post('/api/lists', { data: { name } });
    expect(res.ok()).toBeTruthy();

    return (await res.json()).slug;
}

test('adds an item only after the API responds and renders it as text', async ({ page, request }) => {
    const slug = await createList(request);
    await page.goto(`/l/${slug}`);

    // Client list becomes visible once the initial `show` load resolves.
    const clientList = page.locator('#client-item-list');
    await expect(clientList).toBeVisible();

    let itemResponded = false;
    page.on('response', (r) => {
        if (r.url().includes(`/api/lists/${slug}/items`) && r.request().method() === 'POST') {
            itemResponded = true;
        }
    });

    await page.fill('#new-item', 'Pan integral');
    // The row must not appear before the POST has come back.
    await page.click('button[type="submit"]:has-text("Agregar")');

    const row = clientList.locator('li', { hasText: 'Pan integral' });
    await expect(row).toBeVisible();
    expect(itemResponded).toBeTruthy();
    await expect(page.locator('#new-item')).toHaveValue('');
});

test('marking an item purchased strikes it through and moves it last', async ({ page, request }) => {
    const slug = await createList(request);
    await request.post(`/api/lists/${slug}/items`, { data: { name: 'Manzanas' } });
    await request.post(`/api/lists/${slug}/items`, { data: { name: 'Zanahorias' } });

    await page.goto(`/l/${slug}`);
    const rows = page.locator('#client-item-list li[data-item-id]');
    await expect(rows).toHaveCount(2);

    await page.locator('#client-item-list li[data-item-id]', { hasText: 'Manzanas' }).getByRole('checkbox').check();

    const purchased = page.locator('#client-item-list li[data-item-id]', { hasText: 'Manzanas' });
    await expect(purchased).toHaveClass(/line-through/);
    // Purchased rows sort after the pending ones (RF-18).
    await expect(rows.last()).toContainText('Manzanas');
});

test('deleting an item removes it from the view after the API responds', async ({ page, request }) => {
    const slug = await createList(request);
    await request.post(`/api/lists/${slug}/items`, { data: { name: 'Café' } });

    await page.goto(`/l/${slug}`);
    const row = page.locator('#client-item-list li[data-item-id]', { hasText: 'Café' });
    await expect(row).toBeVisible();

    await row.getByRole('button', { name: 'Eliminar Café' }).click();
    await expect(row).toHaveCount(0);
});

test('editing only the name sends a PATCH with just {name} (RF-25)', async ({ page, request }) => {
    const slug = await createList(request);
    await request.post(`/api/lists/${slug}/items`, { data: { name: 'Te', quantity: '2' } });

    await page.goto(`/l/${slug}`);
    const row = page.locator('#client-item-list li[data-item-id]').first();
    await expect(row.locator('span').first()).toHaveText('Te');

    const patchBodies = [];
    page.on('request', (r) => {
        if (r.method() === 'PATCH' && /\/api\/lists\/.+\/items\/\d+$/.test(r.url())) {
            patchBodies.push(r.postDataJSON());
        }
    });

    await row.locator('span').first().click();
    const editor = row.getByRole('textbox');
    await editor.fill('Té verde');
    await editor.press('Enter');

    await expect(row.locator('span').first()).toHaveText('Té verde');
    expect(patchBodies).toHaveLength(1);
    expect(Object.keys(patchBodies[0])).toEqual(['name']);
});

test('renders an item name containing HTML as plain text (RF-32)', async ({ page, request }) => {
    const slug = await createList(request);
    const payload = '<img src=x onerror="window.__xss = true">';
    await request.post(`/api/lists/${slug}/items`, { data: { name: payload } });

    await page.goto(`/l/${slug}`);
    const span = page.locator('#client-item-list li span').first();
    await expect(span).toHaveText(payload);
    expect(await page.evaluate(() => window.__xss)).toBeUndefined();
    expect(await span.locator('img').count()).toBe(0);
});
