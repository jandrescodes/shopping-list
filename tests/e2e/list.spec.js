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
    const editor = row.locator('[data-edit-field="name"]');
    await editor.fill('Té verde');
    await editor.press('Enter');

    await expect(row.locator('span').first()).toHaveText('Té verde');
    expect(patchBodies).toHaveLength(1);
    expect(Object.keys(patchBodies[0])).toEqual(['name']);
});

test('adds an optional quantity and omits the quantity chip when blank (RF-26, RF-28)', async ({ page, request }) => {
    const slug = await createList(request);
    await page.goto(`/l/${slug}`);
    await expect(page.locator('#client-item-list')).toBeVisible();

    await page.fill('#new-item', 'Pan');
    await page.fill('#new-quantity', ' 2 paquetes ');
    await page.click('button[type="submit"]:has-text("Agregar")');

    const withQuantity = page.locator('#client-item-list li[data-item-id]', { hasText: 'Pan' });
    await expect(withQuantity).toBeVisible();
    await expect(withQuantity.locator('.item-quantity')).toHaveText('2 paquetes');
    await expect(page.locator('#new-quantity')).toHaveValue('');

    await page.fill('#new-item', 'Sal');
    await page.click('button[type="submit"]:has-text("Agregar")');

    const withoutQuantity = page.locator('#client-item-list li[data-item-id]', { hasText: 'Sal' });
    await expect(withoutQuantity).toBeVisible();
    await expect(withoutQuantity.locator('.item-quantity')).toBeHidden();
});

test('editing only the quantity sends a PATCH with just {quantity}', async ({ page, request }) => {
    const slug = await createList(request);
    await request.post(`/api/lists/${slug}/items`, {
        data: { name: 'Te', quantity: '2', is_purchased: false },
    });

    await page.goto(`/l/${slug}`);
    const row = page.locator('#client-item-list li[data-item-id]').first();
    await expect(row.locator('span').first()).toHaveText('Te');

    const patchBodies = [];
    page.on('request', (r) => {
        if (r.method() === 'PATCH' && /\/api\/lists\/.+\/items\/\d+$/.test(r.url())) {
            patchBodies.push(r.postDataJSON());
        }
    });

    await row.locator('.item-quantity').click();
    const quantityEditor = row.locator('[data-edit-field="quantity"]');
    await quantityEditor.fill('4');
    await quantityEditor.press('Enter');

    await expect(row.locator('.item-quantity')).toHaveText('4');
    await expect(row.locator('span').first()).toHaveText('Te');
    await expect(row.getByRole('checkbox')).not.toBeChecked();
    expect(patchBodies).toHaveLength(1);
    expect(Object.keys(patchBodies[0])).toEqual(['quantity']);
});

test('moving focus from name to quantity does not send a PATCH', async ({ page, request }) => {
    const slug = await createList(request);
    await request.post(`/api/lists/${slug}/items`, { data: { name: 'Te', quantity: '2' } });

    await page.goto(`/l/${slug}`);
    const row = page.locator('#client-item-list li[data-item-id]').first();
    await row.locator('span').first().click();

    const patchBodies = [];
    page.on('request', (r) => {
        if (r.method() === 'PATCH' && /\/api\/lists\/.+\/items\/\d+$/.test(r.url())) {
            patchBodies.push(r.postDataJSON());
        }
    });

    const nameEditor = row.locator('[data-edit-field="name"]');
    const quantityEditor = row.locator('[data-edit-field="quantity"]');
    await expect(quantityEditor).toBeVisible();
    await nameEditor.focus();
    await quantityEditor.focus();
    await expect(quantityEditor).toBeFocused();
    expect(patchBodies).toHaveLength(0);

    await quantityEditor.press('Escape');
});

test('preserves a quantity draft while polling receives another device update', async ({ browser, page, request }) => {
    const slug = await createList(request);
    await request.post(`/api/lists/${slug}/items`, { data: { name: 'Te', quantity: '2' } });

    const otherDevice = await browser.newContext();
    const deviceA = await otherDevice.newPage();

    await page.goto(`/l/${slug}`);
    await expect(page.locator('#client-item-list')).toBeVisible();
    await deviceA.goto(`/l/${slug}`);
    await expect(deviceA.locator('#client-item-list')).toBeVisible();

    const row = page.locator('#client-item-list li[data-item-id]').first();
    await row.locator('.item-quantity').click();
    const quantityEditor = row.locator('[data-edit-field="quantity"]');
    await quantityEditor.fill('9');

    await deviceA.locator('.item-quantity').click();
    const otherQuantityEditor = deviceA.locator('[data-edit-field="quantity"]');
    await otherQuantityEditor.fill('3');
    await otherQuantityEditor.press('Enter');

    await page.waitForTimeout(4500);
    await expect(quantityEditor).toHaveValue('9');

    await otherDevice.close();
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

test('changing the currency updates the header chip without reloading (RF-11)', async ({ page, request }) => {
    const slug = await createList(request);
    await page.goto(`/l/${slug}`);
    await expect(page.locator('#client-item-list')).toBeVisible();
    await expect(page.locator('#currency-chip')).toHaveText('Bs');

    let navigated = false;
    page.on('framenavigated', () => { navigated = true; });

    await page.locator('#currency-chip').click();
    await expect(page.locator('#currency-input')).toHaveValue('Bs');
    await page.locator('#currency-input').fill('US$');
    await page.getByRole('button', { name: 'Guardar' }).click();

    await expect(page.locator('#currency-chip')).toHaveText('US$');
    await expect(page.locator('#currency-input')).toBeHidden();
    expect(navigated).toBe(false);

    // The write reached the database: a full reload still shows it.
    await page.reload();
    await expect(page.locator('#currency-chip')).toHaveText('US$');
});
