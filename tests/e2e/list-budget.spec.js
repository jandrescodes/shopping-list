import { expect, test } from '@playwright/test';

// Optional per-list budget: set, change and remove it from the options menu,
// and see it reach another device through the sync.

async function newList(request) {
    const { slug } = await (await request.post('/api/lists', { data: { name: 'Presupuesto' } })).json();

    return slug;
}

async function open(page, slug) {
    await page.goto(`/l/${slug}`);
    await expect(page.locator('#client-item-list')).toBeVisible();
}

async function openBudgetForm(page) {
    await page.click('#list-menu-button');
    await page.click('#list-menu >> text=Presupuesto');
    await expect(page.locator('#budget-input')).toBeVisible();
}

async function saveBudget(page, value) {
    await openBudgetForm(page);
    await page.fill('#budget-input', value);
    await page.press('#budget-input', 'Enter');
}

const chipText = async (page) => {
    await page.click('#list-menu-button');
    const text = (await page.locator('#budget-chip').textContent()).trim();
    await page.keyboard.press('Escape');

    return text;
};

test('a list starts without a budget', async ({ page, request }) => {
    await open(page, await newList(request));

    expect(await chipText(page)).toBe('Sin definir');
});

test('sets, changes and removes the budget', async ({ page, request }) => {
    const slug = await newList(request);
    await open(page, slug);

    await saveBudget(page, '150');
    await expect(page.locator('#budget-input')).toBeHidden();
    expect(await chipText(page)).toBe('Bs 150,00');
    expect((await (await request.get(`/api/lists/${slug}`)).json()).budget).toBe('150.00');

    await saveBudget(page, '99,5');
    expect(await chipText(page)).toBe('Bs 99,50');

    await saveBudget(page, '');
    await expect(page.locator('#budget-input')).toBeHidden();
    expect(await chipText(page)).toBe('Sin definir');
    expect((await (await request.get(`/api/lists/${slug}`)).json()).budget).toBeNull();
});

test('zero is a valid budget', async ({ page, request }) => {
    const slug = await newList(request);
    await open(page, slug);

    await saveBudget(page, '0');

    expect(await chipText(page)).toBe('Bs 0,00');
    expect((await (await request.get(`/api/lists/${slug}`)).json()).budget).toBe('0.00');
});

test('an invalid amount is rejected in Spanish and keeps the current budget', async ({ page, request }) => {
    const slug = await newList(request);
    await open(page, slug);
    await saveBudget(page, '80');
    expect(await chipText(page)).toBe('Bs 80,00');

    await saveBudget(page, 'mucho');

    await expect(page.locator('#list-app p.bg-red-50')).toContainText('presupuesto');
    await expect(page.locator('#budget-input')).toBeVisible();
    expect((await (await request.get(`/api/lists/${slug}`)).json()).budget).toBe('80.00');

    await page.fill('#budget-input', '-5');
    await page.press('#budget-input', 'Enter');
    await expect(page.locator('#list-app p.bg-red-50')).toContainText('presupuesto');
    expect((await (await request.get(`/api/lists/${slug}`)).json()).budget).toBe('80.00');
});

test('a budget set on one device reaches another one by polling', async ({ page, request, browser }) => {
    const slug = await newList(request);
    await open(page, slug);
    const other = await browser.newPage();
    await open(other, slug);

    await saveBudget(other, '200');

    await expect.poll(() => chipText(page), { timeout: 8000 }).toBe('Bs 200,00');
    await other.close();
});

// Budget line and over-budget alert next to the pending total.

async function addPriced(request, slug, name, price) {
    const res = await request.post(`/api/lists/${slug}/items`, { data: { name, price } });
    expect(res.ok()).toBeTruthy();

    return (await res.json()).id;
}

async function setBudget(request, slug, budget) {
    const res = await request.patch(`/api/lists/${slug}`, { data: { budget } });
    expect(res.ok()).toBeTruthy();
}

const line = (page) => page.locator('#budget-line');
const alert = (page) => page.locator('#budget-alert');
const check = (page, name) => page.getByRole('checkbox', { name: `Marcar ${name}` });

test.describe('budget line and alert', () => {
    test('shows the budget beside the total and warns by how much it is exceeded', async ({ page, request }) => {
        const slug = await newList(request);
        await addPriced(request, slug, 'Leche', 30);
        await setBudget(request, slug, 25);
        await open(page, slug);

        await expect(line(page)).toBeVisible();
        await expect(line(page)).toContainText('Bs 25,00');
        await expect(alert(page)).toBeVisible();
        await expect(alert(page)).toHaveText('Te pasas por Bs 5,00');
        await expect(alert(page)).toHaveClass(/bg-orange-50/);
        await expect(page.locator('#live-total')).toHaveText('Total Bs 30,00');
    });

    test('the alert goes away when marking an item brings the total under the budget', async ({ page, request }) => {
        const slug = await newList(request);
        await addPriced(request, slug, 'Leche', 20);
        await addPriced(request, slug, 'Pan', 10);
        await setBudget(request, slug, 25);
        await open(page, slug);
        await expect(alert(page)).toHaveText('Te pasas por Bs 5,00');

        await check(page, 'Leche').check();

        await expect(alert(page)).toBeHidden();
        await expect(line(page)).toBeVisible();
    });

    test('a total equal to the budget is not an excess', async ({ page, request }) => {
        const slug = await newList(request);
        await addPriced(request, slug, 'Leche', 25);
        await setBudget(request, slug, 25);
        await open(page, slug);

        await expect(line(page)).toBeVisible();
        await expect(alert(page)).toBeHidden();
    });

    test('a zero budget is exceeded by any priced item', async ({ page, request }) => {
        const slug = await newList(request);
        await addPriced(request, slug, 'Leche', 1.5);
        await setBudget(request, slug, 0);
        await open(page, slug);

        await expect(alert(page)).toHaveText('Te pasas por Bs 1,50');
    });

    test('without a pending total the budget shows alone, with no alert', async ({ page, request }) => {
        const slug = await newList(request);
        await setBudget(request, slug, 40);
        await open(page, slug);

        await expect(line(page)).toBeVisible();
        await expect(line(page)).toContainText('Bs 40,00');
        await expect(page.locator('#live-total')).toBeHidden();
        await expect(alert(page)).toBeHidden();
    });

    test('a list without budget shows neither line nor alert', async ({ page, request }) => {
        const slug = await newList(request);
        await addPriced(request, slug, 'Leche', 30);
        await open(page, slug);

        await expect(page.locator('#live-total')).toBeVisible();
        await expect(line(page)).toBeHidden();
        await expect(alert(page)).toBeHidden();
    });

    test('adding an item over the budget is allowed and updates the alert', async ({ page, request }) => {
        const slug = await newList(request);
        await addPriced(request, slug, 'Leche', 20);
        await setBudget(request, slug, 25);
        await open(page, slug);
        await expect(alert(page)).toBeHidden();

        await page.fill('#new-item', 'Queso');
        await page.fill('#new-price', '10');
        await page.click('button[type="submit"]:has-text("Agregar")');

        await expect(page.locator('#client-item-list li[data-item-id]', { hasText: 'Queso' })).toBeVisible();
        await expect(alert(page)).toHaveText('Te pasas por Bs 5,00');
    });

    test('removing the budget hides line and alert', async ({ page, request }) => {
        const slug = await newList(request);
        await addPriced(request, slug, 'Leche', 30);
        await setBudget(request, slug, 25);
        await open(page, slug);
        await expect(alert(page)).toBeVisible();

        await saveBudget(page, '');

        await expect(line(page)).toBeHidden();
        await expect(alert(page)).toBeHidden();
    });

    test('resetting purchases raises the total and brings the alert back; undo lowers it again', async ({ page, request }) => {
        const slug = await newList(request);
        const milk = await addPriced(request, slug, 'Leche', 30);
        await setBudget(request, slug, 25);
        await request.patch(`/api/lists/${slug}/items/${milk}`, { data: { is_purchased: true } });
        await open(page, slug);
        await expect(alert(page)).toBeHidden();

        await page.click('#list-menu-button');
        await page.click('#list-menu >> text=Reiniciar compras');
        await expect(alert(page)).toHaveText('Te pasas por Bs 5,00');

        await page.click('#reset-undo >> text=Deshacer');
        await expect(alert(page)).toBeHidden();
    });

    test('changing the currency keeps the amount and relabels the alert', async ({ page, request }) => {
        const slug = await newList(request);
        await addPriced(request, slug, 'Leche', 30);
        await setBudget(request, slug, 25);
        await open(page, slug);

        await request.patch(`/api/lists/${slug}`, { data: { currency: 'USD' } });

        await expect(alert(page)).toHaveText('Te pasas por USD 5,00', { timeout: 8000 });
        await expect(line(page)).toContainText('USD 25,00');
    });

    test('a budget change on another device re-evaluates the alert by polling', async ({ page, request }) => {
        const slug = await newList(request);
        await addPriced(request, slug, 'Leche', 30);
        await open(page, slug);
        await expect(alert(page)).toBeHidden();

        await setBudget(request, slug, 10);

        await expect(alert(page)).toHaveText('Te pasas por Bs 20,00', { timeout: 8000 });
    });

    test('the alert does not use the colours reserved for other notices', async ({ page, request }) => {
        const slug = await newList(request);
        await addPriced(request, slug, 'Leche', 30);
        await setBudget(request, slug, 25);
        await open(page, slug);
        await expect(alert(page)).toBeVisible();

        for (const colour of ['amber', 'red', 'green', 'gray', 'blue', 'sky', 'teal']) {
            await expect(page.locator(`#budget-alert.bg-${colour}-50, #budget-alert.bg-${colour}-100`)).toHaveCount(0);
        }
        await expect(page.locator('#list-app .bg-amber-50:visible')).toHaveCount(0);
        await expect(page.locator('#list-app .bg-red-50:visible')).toHaveCount(0);
    });
});
