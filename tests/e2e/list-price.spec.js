import { expect, test } from '@playwright/test';

// Prices end to end: the amount sits on its row as a plain number, the symbol
// belongs to the header and to the total, and the total follows the items as
// they are priced, edited, bought and cleared.

async function createList(request, name = 'Feria del sábado') {
    const res = await request.post('/api/lists', { data: { name } });
    expect(res.ok()).toBeTruthy();

    return (await res.json()).slug;
}

const row = (page, name) => page.locator('#client-item-list li[data-item-id]', { hasText: name });

// The client's own total. It is always in the DOM (state decides), so every
// assertion pairs visibility with the amount: a hidden total can still carry
// the text of the value it last computed.
const total = (page) => page.locator('#live-total');

async function expectTotal(page, text, options) {
    await expect(total(page)).toBeVisible(options);
    await expect(total(page)).toHaveText(text, options);
}

async function addItem(page, name, price) {
    await page.fill('#new-item', name);

    if (price !== undefined) {
        await page.fill('#new-price', price);
    }

    await page.click('button[type="submit"]:has-text("Agregar")');
    await expect(row(page, name)).toBeVisible();
}

async function openList(page, slug) {
    await page.goto(`/l/${slug}`);
    await expect(page.locator('#client-item-list')).toBeVisible();
}

async function editPrice(page, name, value) {
    const itemRow = row(page, name);
    await itemRow.locator('button.item-price').click();
    await itemRow.locator('input[id^="price-"]').fill(value);
    await itemRow.locator('input[id^="price-"]').press('Enter');
}

test('shows a price typed on the add form in the row and in the total (RF-1, RF-8, RF-15)', async ({ page, request }) => {
    const slug = await createList(request);
    await openList(page, slug);

    await addItem(page, 'Pan', '12,50');
    const chip = row(page, 'Pan').locator('button.item-price');
    await expect(chip).toHaveText('12,50');
    // The symbol stays out of the row: it lives in the header and the total.
    await expect(chip).not.toContainText('Bs');
    await expectTotal(page, 'Total Bs 12,50');

    // An item without a price shows no amount, only the way to give it one.
    await addItem(page, 'Sal');
    const withoutPrice = row(page, 'Sal');
    await expect(withoutPrice.locator('button.item-price')).toBeHidden();
    await expect(withoutPrice.getByRole('button', { name: 'Agregar precio a Sal' })).toBeVisible();
    await expectTotal(page, 'Total Bs 12,50');
});

test('prices an existing item from its own row (RF-3)', async ({ page, request }) => {
    const slug = await createList(request);
    await openList(page, slug);
    await addItem(page, 'Queso');

    const itemRow = row(page, 'Queso');
    await itemRow.getByRole('button', { name: 'Agregar precio a Queso' }).click();
    const editor = itemRow.locator('input[id^="price-"]');
    await editor.fill('3,50');
    await editor.press('Enter');

    await expect(itemRow.locator('button.item-price')).toHaveText('3,50');
    await expectTotal(page, 'Total Bs 3,50');
});

test('recalculates the total on every change and leaves purchased items out (RF-16, RF-18, RF-19)', async ({ page, request }) => {
    const slug = await createList(request);
    await openList(page, slug);

    await addItem(page, 'Pan', '12,50');
    await addItem(page, 'Leche', '7,50');
    await expectTotal(page, 'Total Bs 20,00');

    await editPrice(page, 'Pan', '20');
    await expectTotal(page, 'Total Bs 27,50');

    // A bought item keeps its own amount on the row but leaves the total.
    await row(page, 'Pan').getByRole('checkbox').check();
    await expect(row(page, 'Pan').locator('button.item-price')).toHaveText('20,00');
    await expectTotal(page, 'Total Bs 7,50');

    await row(page, 'Pan').getByRole('checkbox').uncheck();
    await expectTotal(page, 'Total Bs 27,50');
});

test('clearing a price hides its amount, and the last one hides the total (RF-4, RF-17, RF-20)', async ({ page, request }) => {
    const slug = await createList(request);
    await openList(page, slug);

    await addItem(page, 'Pan', '12,50');
    await addItem(page, 'Leche', '7,50');
    await expectTotal(page, 'Total Bs 20,00');

    const panRow = row(page, 'Pan');
    await panRow.locator('button.item-price').click();
    await panRow.getByRole('button', { name: 'Borrar' }).click();

    // x-show hides rather than unmounts, so the chip is asserted hidden.
    await expect(panRow.locator('button.item-price')).toBeHidden();
    await expect(panRow.getByRole('button', { name: 'Agregar precio a Pan' })).toBeVisible();
    await expectTotal(page, 'Total Bs 7,50');

    const lecheRow = row(page, 'Leche');
    await lecheRow.locator('button.item-price').click();
    await lecheRow.getByRole('button', { name: 'Borrar' }).click();

    await expect(lecheRow.locator('button.item-price')).toBeHidden();
    await expect(total(page)).toBeHidden();
});

test('reads "12,50" and "12.50" the same way and counts0 as a price (RF-7, RF-16)', async ({ page, request }) => {
    const withDot = await createList(request, 'Con punto');
    await openList(page, withDot);
    await addItem(page, 'Pan', '12.50');
    await expect(row(page, 'Pan').locator('button.item-price')).toHaveText('12,50');
    await expectTotal(page, 'Total Bs 12,50');

    const withZero = await createList(request, 'Con cero');
    await openList(page, withZero);
    await addItem(page, 'Sal', '0');
    await expect(row(page, 'Sal').locator('button.item-price')).toHaveText('0,00');
    await expectTotal(page, 'Total Bs 0,00');
});

test('rejects a price the server will not accept and keeps the stored one (RF-6)', async ({ page, request }) => {
    const slug = await createList(request);
    await openList(page, slug);

    await addItem(page, 'Pan', '12,50');
    await editPrice(page, 'Pan', '1.999');

    // Scoped by the background class: several alerts share the role on this page.
    await expect(page.locator('#list-app p.bg-red-50')).toContainText('No se pudo guardar el precio.');
    await expect(row(page, 'Pan').locator('button.item-price')).toHaveText('12,50');
});

test('moves the currency symbol into the total when the list currency changes (RF-11, RF-14)', async ({ page, request }) => {
    const slug = await createList(request);
    await openList(page, slug);

    await addItem(page, 'Pan', '12,50');
    await expectTotal(page, 'Total Bs 12,50');

    await page.locator('#currency-chip').click();
    await page.locator('#currency-input').fill('US$');
    await page.getByRole('button', { name: 'Guardar' }).click();

    await expectTotal(page, 'Total US$ 12,50');
});

test('undoing a deletion restores the price with the item (RF-25)', async ({ page, request }) => {
    const slug = await createList(request);
    await openList(page, slug);

    await addItem(page, 'Pan', '12,50');
    await expectTotal(page, 'Total Bs 12,50');

    await row(page, 'Pan').getByRole('button', { name: 'Eliminar Pan' }).click();
    await expect(row(page, 'Pan')).toHaveCount(0);
    await expect(total(page)).toBeHidden();

    // Scope by the notice's own background: several alerts share the role here.
    await page.locator('#list-app .bg-blue-50').getByRole('button', { name: 'Deshacer' }).click();

    await expect(row(page, 'Pan').locator('button.item-price')).toHaveText('12,50');
    await expectTotal(page, 'Total Bs 12,50');
});

test('a rename and a currency change from another open device reach this one (RF-22, RF-24)', async ({ browser, page, request }) => {
    const slug = await createList(request, 'Feria');
    await request.post(`/api/lists/${slug}/items`, { data: { name: 'Pan', price: '12.50' } });

    const otherDevice = await browser.newContext();
    const deviceA = await otherDevice.newPage();

    await page.goto(`/l/${slug}`);
    await expect(page.locator('#client-item-list')).toBeVisible();

    await deviceA.goto(`/l/${slug}`);
    await expect(deviceA.locator('#client-item-list')).toBeVisible();
    await expectTotal(deviceA, 'Total Bs 12,50');

    let navigated = false;
    page.on('framenavigated', () => { navigated = true; });

    await deviceA.getByRole('button', { name: 'Renombrar' }).click();
    await deviceA.locator('#rename-input').fill('Feria grande');
    await deviceA.locator('#rename-input').press('Enter');

    await deviceA.locator('#currency-chip').click();
    await deviceA.locator('#currency-input').fill('US$');
    await deviceA.getByRole('button', { name: 'Guardar' }).click();

    // Device B keeps its tab open: the changes arrive by polling, not reload.
    await expect(page.locator('#list-app h1')).toHaveText('Feria grande', { timeout: 6000 });
    await expect(page.locator('#currency-chip')).toHaveText('US$', { timeout: 6000 });
    await expectTotal(page, 'Total US$ 12,50', { timeout: 6000 });
    expect(navigated).toBe(false);

    await otherDevice.close();
});
