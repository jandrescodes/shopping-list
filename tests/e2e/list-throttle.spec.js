import { expect, test } from '@playwright/test';

// A 429 on a write names the cause and the wait instead of the generic
// failure text; the typed value is kept.

test('a throttled write says to wait, with the Retry-After seconds', async ({ page, request }) => {
    const res = await request.post('/api/lists', { data: { name: 'Feria del sábado' } });
    const { slug } = await res.json();
    await page.goto(`/l/${slug}`);
    await expect(page.locator('#new-item')).toBeVisible();

    await page.route(`**/api/lists/${slug}/items`, (route) => {
        if (route.request().method() !== 'POST') {
            return route.continue();
        }

        return route.fulfill({
            status: 429,
            headers: { 'Retry-After': '30' },
            contentType: 'application/json',
            body: JSON.stringify({ message: 'Too Many Attempts.' }),
        });
    });

    await page.fill('#new-item', 'Leche');
    await page.click('button[type="submit"]:has-text("Agregar")');

    await expect(page.locator('#list-app p[role="alert"]')).toContainText('Espera 30 s');
    await expect(page.locator('#new-item')).toHaveValue('Leche');
});

test('the home explains a throttled list creation', async ({ page }) => {
    await page.route('**/api/lists', (route) => route.fulfill({
        status: 429,
        headers: { 'Retry-After': '1800' },
        contentType: 'application/json',
        body: JSON.stringify({ message: 'Too Many Attempts.' }),
    }));

    await page.goto('/');
    await page.fill('#list-name', 'Feria del sábado');
    await page.click('button[type="submit"]:has-text("Crear")');

    await expect(page.getByText('Inténtalo de nuevo en 30 min.')).toBeVisible();
});
