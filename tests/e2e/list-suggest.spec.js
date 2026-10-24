import { expect, test } from '@playwright/test';

// Suggestions are computed from the items the list already holds: the getter
// is exercised through the component state, the panel itself comes later.

async function listWith(request, names) {
    const { slug } = await (await request.post('/api/lists', { data: { name: 'Sugerencias' } })).json();
    const ids = {};

    for (const name of names) {
        const res = await request.post(`/api/lists/${slug}/items`, { data: { name } });
        ids[name] = (await res.json()).id;
    }

    return { slug, ids };
}

async function open(page, slug) {
    await page.goto(`/l/${slug}`);
    await expect(page.locator('#client-item-list > li[data-item-id]').first()).toBeVisible();
}

const suggest = (page, typed) => page.evaluate((text) => {
    const data = window.Alpine.$data(document.querySelector('#list-app'));
    data.newName = text;

    return data.suggestions;
}, typed);

test('needs at least two characters', async ({ page, request }) => {
    const { slug } = await listWith(request, ['Leche', 'Lentejas']);
    await open(page, slug);

    expect(await suggest(page, '')).toEqual([]);
    expect(await suggest(page, 'l')).toEqual([]);
    expect(await suggest(page, ' l ')).toEqual([]);
    expect(await suggest(page, 'le')).toEqual(['Leche', 'Lentejas']);
});

test('shows at most five, names that start with the text first', async ({ page, request }) => {
    const { slug } = await listWith(request, ['Aceite de oliva', 'Aceite', 'Aceitunas', 'Maíz aceitado', 'Pan con aceite', 'Té aceitoso', 'Zumo aceitoso']);
    await open(page, slug);

    expect(await suggest(page, 'ace')).toEqual(['Aceite', 'Aceite de oliva', 'Aceitunas', 'Maíz aceitado', 'Pan con aceite']);
});

test('ignores case and accents in both directions', async ({ page, request }) => {
    const { slug } = await listWith(request, ['Café molido', 'Azúcar']);
    await open(page, slug);

    expect(await suggest(page, 'CAFE')).toEqual(['Café molido']);
    expect(await suggest(page, 'cafÉ m')).toEqual(['Café molido']);
    expect(await suggest(page, 'azuc')).toEqual(['Azúcar']);
});

test('matches by content, not only by prefix', async ({ page, request }) => {
    const { slug } = await listWith(request, ['Leche de coco', 'Arroz']);
    await open(page, slug);

    expect(await suggest(page, 'coco')).toEqual(['Leche de coco']);
});

test('one entry per name, keeping the one from the newest item', async ({ page, request }) => {
    const { slug } = await listWith(request, ['Café', 'Pan', 'cafe']);
    await open(page, slug);

    expect(await suggest(page, 'caf')).toEqual(['cafe']);
});

test('does not offer what is already typed in full', async ({ page, request }) => {
    const { slug } = await listWith(request, ['Leche', 'Leche de coco']);
    await open(page, slug);

    expect(await suggest(page, 'leche')).toEqual(['Leche de coco']);
    expect(await suggest(page, 'Leche de coco')).toEqual([]);
});

test('purchased items still suggest, deleted ones do not', async ({ page, request }) => {
    const { slug, ids } = await listWith(request, ['Leche', 'Lentejas', 'Limones']);
    await request.patch(`/api/lists/${slug}/items/${ids.Leche}`, { data: { is_purchased: true } });
    await request.delete(`/api/lists/${slug}/items/${ids.Lentejas}`);
    await open(page, slug);

    expect(await suggest(page, 'le')).toEqual(['Leche']);

    await request.delete(`/api/lists/${slug}/items/${ids.Limones}`);
    await expect.poll(() => suggest(page, 'li'), { timeout: 6000 }).toEqual([]);
});

test('no matches means no suggestions', async ({ page, request }) => {
    const { slug } = await listWith(request, ['Leche']);
    await open(page, slug);

    expect(await suggest(page, 'zz')).toEqual([]);
});

async function listWithDetails(request) {
    const { slug } = await (await request.post('/api/lists', { data: { name: 'Panel' } })).json();
    await request.post(`/api/lists/${slug}/items`, { data: { name: 'Leche', quantity: '2', price: '12.50' } });
    await request.post(`/api/lists/${slug}/items`, { data: { name: 'Lentejas' } });

    return slug;
}

const panel = (page) => page.locator('#name-suggestions');
const rows = (page) => page.locator('#name-suggestions [role="option"]');
const itemCount = (page) => page.locator('#client-item-list > li[data-item-id]');

test('the panel appears while typing and is a labelled combobox', async ({ page, request }) => {
    await open(page, await listWithDetails(request));
    const input = page.locator('#new-item');

    await input.fill('l');
    await expect(panel(page)).toBeHidden();

    await input.fill('le');
    await expect(rows(page)).toHaveText(['Leche', 'Lentejas']);
    await expect(input).toHaveAttribute('role', 'combobox');
    await expect(input).toHaveAttribute('aria-expanded', 'true');
    await expect(panel(page)).toHaveAttribute('role', 'listbox');
    for (const row of await rows(page).all()) {
        expect((await row.boundingBox()).height).toBeGreaterThanOrEqual(44);
    }
});

test('arrows and Enter pick a suggestion: only the name is filled and nothing is added', async ({ page, request }) => {
    await open(page, await listWithDetails(request));
    const input = page.locator('#new-item');
    await input.fill('le');

    await input.press('ArrowDown');
    await expect(input).toHaveAttribute('aria-activedescendant', /.+/);
    await input.press('ArrowDown');
    await input.press('ArrowUp');
    await input.press('Enter');

    await expect(input).toHaveValue('Leche');
    await expect(panel(page)).toBeHidden();
    await expect(page.locator('#new-quantity')).toHaveValue('');
    await expect(page.locator('#new-price')).toHaveValue('');
    await expect(itemCount(page)).toHaveCount(2);
});

test('clicking a suggestion picks it', async ({ page, request }) => {
    await open(page, await listWithDetails(request));
    const input = page.locator('#new-item');
    await input.fill('len');

    await rows(page).first().click();

    await expect(input).toHaveValue('Lentejas');
    await expect(panel(page)).toBeHidden();
    await expect(page.locator('#new-quantity')).toHaveValue('');
    await expect(itemCount(page)).toHaveCount(2);
});

test('Enter with no active suggestion adds what was typed, as is', async ({ page, request }) => {
    await open(page, await listWithDetails(request));
    const input = page.locator('#new-item');
    await input.fill('Lech');

    await input.press('Enter');

    await expect(itemCount(page)).toHaveCount(3);
    await expect(itemCount(page).filter({ hasText: 'Lech' }).last()).toBeVisible();
    await expect(input).toHaveValue('');
    await expect(panel(page)).toBeHidden();
});

test('Escape closes the panel and typing reopens it', async ({ page, request }) => {
    await open(page, await listWithDetails(request));
    const input = page.locator('#new-item');
    await input.fill('le');
    await expect(panel(page)).toBeVisible();

    await input.press('Escape');
    await expect(panel(page)).toBeHidden();
    await expect(input).toHaveAttribute('aria-expanded', 'false');

    await input.press('n');
    await expect(panel(page)).toBeVisible();
});

test('no panel when nothing matches', async ({ page, request }) => {
    await open(page, await listWithDetails(request));

    await page.locator('#new-item').fill('zzz');

    await expect(panel(page)).toBeHidden();
    await expect(page.locator('#new-item')).toHaveAttribute('aria-expanded', 'false');
});
