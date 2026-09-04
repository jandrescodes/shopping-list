import { expect, test } from '@playwright/test';

// list.js local memory: remembers the opened list and the "who is adding"
// name in localStorage, offers "quitar de mis listas", prunes on a 404 from
// the authoritative load, refreshes the stored name after a rename, and
// keeps at most 20 lists.

async function createList(request, name = 'Feria del sábado') {
    const res = await request.post('/api/lists', { data: { name } });
    expect(res.ok()).toBeTruthy();

    return (await res.json()).slug;
}

const readEntries = (page) =>
    page.evaluate(() => JSON.parse(localStorage.getItem('myShoppingLists')) || []);

test('opening a list stores it in "my lists"', async ({ page, request }) => {
    const slug = await createList(request, 'Cumpleaños');
    await page.goto(`/l/${slug}`);
    await expect(page.locator('#client-item-list')).toBeVisible();

    const entries = await readEntries(page);
    expect(entries[0]).toEqual({ slug, name: 'Cumpleaños' });
});

test('"quitar de mis listas" removes the entry locally', async ({ page, request }) => {
    const slug = await createList(request);
    await page.goto(`/l/${slug}`);
    await expect(page.locator('#client-item-list')).toBeVisible();
    expect((await readEntries(page))).toHaveLength(1);

    await page.getByRole('button', { name: 'Quitar de mis listas' }).click();

    expect(await readEntries(page)).toEqual([]);
    await expect(page.getByText('Quitada de tus listas')).toBeVisible();
});

test('a 404 from the authoritative load prunes the stored entry (RF-6)', async ({ page, request }) => {
    const slug = await createList(request);
    await page.goto(`/l/${slug}`);
    await expect(page.locator('#client-item-list')).toBeVisible();
    expect((await readEntries(page))).toHaveLength(1);

    await page.route(`**/api/lists/${slug}`, (route) =>
        route.fulfill({ status: 404, contentType: 'application/json', body: '{"message":"Not Found"}' }));
    await page.reload();

    await expect(page.locator('#list-app p[role="alert"]')).toContainText('ya no existe');
    expect(await readEntries(page)).toEqual([]);
});

test('renaming the list refreshes the stored name', async ({ page, request }) => {
    const slug = await createList(request, 'Feria');
    await page.goto(`/l/${slug}`);
    await expect(page.locator('#client-item-list')).toBeVisible();

    await page.getByRole('button', { name: 'Renombrar' }).click();
    await page.fill('#rename-input', 'Feria grande');
    await page.click('button[type="submit"]:has-text("Guardar")');

    await expect(page.locator('#list-app h1')).toHaveText('Feria grande');
    expect((await readEntries(page))[0]).toEqual({ slug, name: 'Feria grande' });
});

test('keeps at most 20 lists, dropping the oldest', async ({ page, request }) => {
    const slug = await createList(request, 'La número 21');

    await page.goto('/');
    await page.evaluate(() => {
        const entries = Array.from({ length: 20 }, (_, i) => ({ slug: `seed-${i}`, name: `Lista ${i}` }));
        localStorage.setItem('myShoppingLists', JSON.stringify(entries));
    });

    await page.goto(`/l/${slug}`);
    await expect(page.locator('#client-item-list')).toBeVisible();

    const entries = await readEntries(page);
    expect(entries).toHaveLength(20);
    expect(entries[0]).toEqual({ slug, name: 'La número 21' });
    expect(entries.some((entry) => entry.slug === 'seed-19')).toBe(false);
});

test('remembers "who is adding" and proposes it, editable, next visit (RF-21)', async ({ page, request }) => {
    const slug = await createList(request);
    await page.goto(`/l/${slug}`);
    await expect(page.locator('#client-item-list')).toBeVisible();

    await page.fill('#added-by', 'Andrés');
    await page.fill('#new-item', 'Leche');
    await page.click('button[type="submit"]:has-text("Agregar")');

    const row = page.locator('#client-item-list li[data-item-id]', { hasText: 'Leche' });
    await expect(row).toContainText('Andrés');

    await page.reload();
    await expect(page.locator('#client-item-list')).toBeVisible();
    await expect(page.locator('#added-by')).toHaveValue('Andrés');

    await page.fill('#added-by', 'María');
    await expect(page.locator('#added-by')).toHaveValue('María');
});
