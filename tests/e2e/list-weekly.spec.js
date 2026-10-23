import { expect, test } from '@playwright/test';

// Weekly shopping end to end: copying the list as plain text and resetting
// the purchases with an undo.

async function createList(request, name = 'Compra semanal') {
    const res = await request.post('/api/lists', { data: { name } });
    expect(res.ok()).toBeTruthy();

    return (await res.json()).slug;
}

async function addItem(request, slug, data) {
    const res = await request.post(`/api/lists/${slug}/items`, { data });
    expect(res.ok()).toBeTruthy();

    return res.json();
}

async function openList(page, slug) {
    await page.goto(`/l/${slug}`);
    await expect(page.locator('#client-item-list')).toBeVisible();
}

async function chooseCopyAsText(page) {
    await page.click('#list-menu-button');
    await page.click('#list-menu >> text=Copiar como texto');
}

const readClipboard = (page) => page.evaluate(() => navigator.clipboard.readText());

test.describe('exportar como texto', () => {
    test('copia el texto exacto con pendientes antes que comprados y avisa', async ({ page, request, context }) => {
        await context.grantPermissions(['clipboard-read', 'clipboard-write']);
        const slug = await createList(request);
        const bread = await addItem(request, slug, { name: 'Pan', price: 3 });
        await request.patch(`/api/lists/${slug}/items/${bread.id}`, { data: { is_purchased: true } });
        await addItem(request, slug, { name: 'Leche', quantity: '2', price: 12.5 });
        await addItem(request, slug, { name: 'Huevos' });
        await openList(page, slug);

        await chooseCopyAsText(page);

        await expect(page.locator('#export-notice')).toContainText('Lista copiada');
        expect(await readClipboard(page)).toBe([
            'Compra semanal',
            '- [ ] Leche (2) — 12,50',
            '- [ ] Huevos',
            '- [x] Pan — 3,00',
            'Total pendiente: Bs 12,50',
        ].join('\n'));
    });

    test('un ítem sin cantidad ni precio no deja marcadores vacíos', async ({ page, request, context }) => {
        await context.grantPermissions(['clipboard-read', 'clipboard-write']);
        const slug = await createList(request);
        await addItem(request, slug, { name: 'Huevos' });
        await openList(page, slug);

        await chooseCopyAsText(page);

        await expect(page.locator('#export-notice')).toBeVisible();
        expect(await readClipboard(page)).toBe('Compra semanal\n- [ ] Huevos');
    });

    test('un ítem borrado no aparece en el texto', async ({ page, request, context }) => {
        await context.grantPermissions(['clipboard-read', 'clipboard-write']);
        const slug = await createList(request);
        await addItem(request, slug, { name: 'Leche' });
        const gone = await addItem(request, slug, { name: 'Huevos' });
        await openList(page, slug);
        expect((await request.delete(`/api/lists/${slug}/items/${gone.id}`)).ok()).toBeTruthy();
        await expect(page.locator('#client-item-list li[data-item-id]', { hasText: 'Huevos' })).toHaveCount(0, { timeout: 10000 });

        await chooseCopyAsText(page);

        await expect(page.locator('#export-notice')).toBeVisible();
        expect(await readClipboard(page)).toBe('Compra semanal\n- [ ] Leche');
    });

    test('una lista vacía copia solo el nombre', async ({ page, request, context }) => {
        await context.grantPermissions(['clipboard-read', 'clipboard-write']);
        const slug = await createList(request);
        await openList(page, slug);

        await chooseCopyAsText(page);

        await expect(page.locator('#export-notice')).toBeVisible();
        expect(await readClipboard(page)).toBe('Compra semanal');
    });

    test('el importe del texto coincide con el total de la interfaz (1234,50)', async ({ page, request, context }) => {
        await context.grantPermissions(['clipboard-read', 'clipboard-write']);
        const slug = await createList(request);
        await addItem(request, slug, { name: 'Televisor', price: 1234.5 });
        await openList(page, slug);

        const shown = (await page.locator('#live-total').textContent()).replace(/^\s*Total\s*/, '').trim();
        await chooseCopyAsText(page);

        await expect(page.locator('#export-notice')).toBeVisible();
        const text = await readClipboard(page);
        expect(shown).toBe('Bs 1234,50');
        expect(text).toContain('- [ ] Televisor — 1234,50');
        expect(text.split('\n').at(-1)).toBe(`Total pendiente: ${shown}`);
    });

    test('sin permiso de portapapeles muestra el texto seleccionado para copiar a mano', async ({ page, request }) => {
        const slug = await createList(request);
        await addItem(request, slug, { name: 'Leche', quantity: '2', price: 12.5 });
        await openList(page, slug);
        await page.evaluate(() => {
            navigator.clipboard.writeText = () => Promise.reject(new Error('denied'));
        });

        await chooseCopyAsText(page);

        const field = page.locator('#export-fallback');
        await expect(field).toBeVisible();
        await expect(field).toHaveValue('Compra semanal\n- [ ] Leche (2) — 12,50\nTotal pendiente: Bs 12,50');
        await expect(page.locator('#export-notice')).toBeHidden();
        const allSelected = await field.evaluate((el) => el.selectionStart === 0 && el.selectionEnd === el.value.length);
        expect(allSelected).toBe(true);
    });
});

async function markPurchased(request, slug, item) {
    const res = await request.patch(`/api/lists/${slug}/items/${item.id}`, { data: { is_purchased: true } });
    expect(res.ok()).toBeTruthy();
}

async function chooseReset(page) {
    await page.click('#list-menu-button');
    await page.click('#list-menu >> text=Reiniciar compras');
}

const checkbox = (page, name, purchased) => page.getByRole('checkbox', { name: `${purchased ? 'Desmarcar' : 'Marcar'} ${name}` });

test.describe('reiniciar compras', () => {
    test('reiniciar desmarca todo, conserva los datos y recalcula el total', async ({ page, request }) => {
        const slug = await createList(request);
        const milk = await addItem(request, slug, { name: 'Leche', quantity: '2', price: 10 });
        const bread = await addItem(request, slug, { name: 'Pan', price: 5 });
        await addItem(request, slug, { name: 'Huevos', price: 1 });
        await markPurchased(request, slug, milk);
        await markPurchased(request, slug, bread);
        await openList(page, slug);
        await expect(page.locator('#live-total')).toHaveText('Total Bs 1,00');

        await chooseReset(page);

        await expect(page.locator('#reset-undo')).toContainText('Compras reiniciadas');
        await expect(page.locator('#live-total')).toHaveText('Total Bs 16,00');
        for (const name of ['Leche', 'Pan', 'Huevos']) {
            await expect(checkbox(page, name, false)).not.toBeChecked();
        }
        await expect(page.locator('#client-item-list li[data-item-id]', { hasText: 'Leche' })).toContainText('2');

        const items = (await (await request.get(`/api/lists/${slug}/items`)).json()).items;
        expect(items.map((i) => [i.name, i.quantity, i.price, i.is_purchased])).toEqual([
            ['Leche', '2', '10.00', false],
            ['Pan', null, '5.00', false],
            ['Huevos', null, '1.00', false],
        ]);
    });

    test('reiniciar no se ofrece mientras no haya ítems comprados', async ({ page, request }) => {
        const slug = await createList(request);
        await addItem(request, slug, { name: 'Leche' });
        await openList(page, slug);

        await page.click('#list-menu-button');

        await expect(page.locator('#list-menu')).toBeVisible();
        await expect(page.locator('#list-menu >> text=Reiniciar compras')).toBeHidden();
    });

    test('borrar un ítem durante la ventana reemplaza el aviso de reiniciar', async ({ page, request }) => {
        const slug = await createList(request);
        const milk = await addItem(request, slug, { name: 'Leche' });
        await addItem(request, slug, { name: 'Pan' });
        await markPurchased(request, slug, milk);
        await openList(page, slug);
        await chooseReset(page);
        await expect(page.locator('#reset-undo')).toBeVisible();

        await page.getByRole('button', { name: 'Eliminar Pan' }).click();

        await expect(page.getByText('Ítem eliminado — Pan')).toBeVisible();
        await expect(page.locator('#reset-undo')).toBeHidden();
    });

    test('otro dispositivo ve el reinicio por el polling', async ({ page, request, browser }) => {
        const slug = await createList(request);
        const milk = await addItem(request, slug, { name: 'Leche' });
        await markPurchased(request, slug, milk);
        await openList(page, slug);
        const other = await (await browser.newContext()).newPage();
        await openList(other, slug);
        await expect(checkbox(other, 'Leche', true)).toBeChecked();

        await chooseReset(page);

        await expect(checkbox(other, 'Leche', false)).not.toBeChecked({ timeout: 10000 });
        await other.context().close();
    });
});

test.describe('deshacer reinicio', () => {
    test('deshacer reinicio restaura solo lo que sigue desmarcado', async ({ page, request }) => {
        const slug = await createList(request);
        const a = await addItem(request, slug, { name: 'Aceite', price: 10 });
        const b = await addItem(request, slug, { name: 'Bebida', price: 20 });
        const c = await addItem(request, slug, { name: 'Cafe', price: 1 });
        for (const item of [a, b, c]) {
            await markPurchased(request, slug, item);
        }
        await openList(page, slug);
        await chooseReset(page);
        await expect(page.locator('#live-total')).toHaveText('Total Bs 31,00');
        // Meanwhile another device deletes one and marks another again.
        expect((await request.delete(`/api/lists/${slug}/items/${b.id}`)).ok()).toBeTruthy();
        await markPurchased(request, slug, c);

        await page.click('#reset-undo >> text=Deshacer');

        await expect(page.locator('#reset-undo')).toBeHidden();
        await expect(checkbox(page, 'Aceite', true)).toBeChecked();
        const items = (await (await request.get(`/api/lists/${slug}/items`)).json()).items;
        expect(items.map((i) => [i.name, i.is_purchased])).toEqual([['Aceite', true], ['Cafe', true]]);
    });

    test('deshacer reinicio: el aviso vence a los 5 s', async ({ page, request }) => {
        const slug = await createList(request);
        const milk = await addItem(request, slug, { name: 'Leche' });
        await markPurchased(request, slug, milk);
        await openList(page, slug);
        await chooseReset(page);
        await expect(page.locator('#reset-undo')).toBeVisible();

        await expect(page.locator('#reset-undo')).toBeHidden({ timeout: 7000 });
        await expect(checkbox(page, 'Leche', false)).not.toBeChecked();
    });
});

async function chooseDuplicate(page) {
    await page.click('#list-menu-button');
    await page.click('#list-menu >> text=Duplicar lista');
}

const slugOf = (page) => new URL(page.url()).pathname.split('/').pop();

test.describe('duplicar lista', () => {
    test('duplicar abre la copia sin marcar, con precios y moneda, y deja intacta la original', async ({ page, request }) => {
        const slug = await createList(request, 'Compra semanal');
        const currency = await request.patch(`/api/lists/${slug}`, { data: { currency: 'USD' } });
        expect(currency.ok()).toBeTruthy();
        const milk = await addItem(request, slug, { name: 'Leche', quantity: '2', price: 12.5 });
        await addItem(request, slug, { name: 'Huevos', price: 3 });
        await markPurchased(request, slug, milk);
        await openList(page, slug);

        await chooseDuplicate(page);

        await expect(page).toHaveURL(new RegExp(`/l/(?!${slug}$)[A-Za-z0-9_-]+$`));
        await expect(page.locator('h1')).toHaveText('Copia de Compra semanal');
        await expect(page.locator('#currency-chip')).toHaveText('USD');
        await expect(page.locator('#live-total')).toHaveText('Total USD 15,50');
        await expect(checkbox(page, 'Leche', false)).not.toBeChecked();
        await expect(checkbox(page, 'Huevos', false)).not.toBeChecked();
        await expect(page.locator('#client-item-list li[data-item-id]', { hasText: 'Leche' })).toContainText('2');

        const original = (await (await request.get(`/api/lists/${slug}/items`)).json()).items;
        expect(original.map((i) => [i.name, i.is_purchased])).toEqual([['Huevos', false], ['Leche', true]]);
        const copySlug = slugOf(page);
        expect(copySlug).not.toBe(slug);
    });

    test('duplicar deja la copia en mis listas', async ({ page, request }) => {
        const slug = await createList(request, 'Compra semanal');
        await openList(page, slug);

        await chooseDuplicate(page);

        await expect(page.locator('h1')).toHaveText('Copia de Compra semanal');
        const copySlug = slugOf(page);
        await expect.poll(() => page.evaluate(() => JSON.parse(localStorage.getItem('myShoppingLists'))))
            .toContainEqual({ slug: copySlug, name: 'Copia de Compra semanal' });
    });

    test('duplicar recorta el nombre a 60 caracteres', async ({ page, request }) => {
        const slug = await createList(request, 'x'.repeat(60));
        await openList(page, slug);

        await chooseDuplicate(page);

        await expect(page.locator('h1')).toHaveText(`Copia de ${'x'.repeat(51)}`);
    });

    test('duplicar con 429 muestra la espera en minutos', async ({ page, request }) => {
        const slug = await createList(request);
        await openList(page, slug);
        await page.route('**/duplicate', (route) => route.fulfill({
            status: 429,
            headers: { 'Retry-After': '1800' },
            contentType: 'application/json',
            body: JSON.stringify({ message: 'Too Many Attempts.' }),
        }));

        await chooseDuplicate(page);

        await expect(page.getByText('Creaste demasiadas listas seguidas. Inténtalo de nuevo en 30 min.')).toBeVisible();
        expect(slugOf(page)).toBe(slug);
    });

    test('duplicar sin red muestra el aviso de desconexión', async ({ page, request }) => {
        const slug = await createList(request);
        await openList(page, slug);
        await page.route('**/duplicate', (route) => route.abort('internetdisconnected'));

        await chooseDuplicate(page);

        await expect(page.locator('#list-app p[role="alert"]')).toContainText('Sin conexión');
        expect(slugOf(page)).toBe(slug);
    });
});

test.describe('menú de opciones', () => {
    test('los botones nuevos del menú miden al menos 44 px de alto', async ({ page, request }) => {
        const slug = await createList(request);
        const milk = await addItem(request, slug, { name: 'Leche' });
        await markPurchased(request, slug, milk);
        await openList(page, slug);

        await page.click('#list-menu-button');

        for (const label of ['Copiar como texto', 'Duplicar lista', 'Reiniciar compras']) {
            const box = await page.locator('#list-menu button', { hasText: label }).boundingBox();
            expect(box.height, label).toBeGreaterThanOrEqual(44);
        }
    });

    test('el menú se recorre con teclado y Escape devuelve el foco al botón', async ({ page, request }) => {
        const slug = await createList(request);
        const milk = await addItem(request, slug, { name: 'Leche' });
        await markPurchased(request, slug, milk);
        await openList(page, slug);

        await page.focus('#list-menu-button');
        await page.keyboard.press('Enter');
        await expect(page.locator('#list-menu')).toBeVisible();

        const visited = [];
        for (let i = 0; i < 6; i++) {
            await page.keyboard.press('Tab');
            visited.push((await page.evaluate(() => document.activeElement.textContent)).trim());
        }
        expect(visited.slice(0, 5)).toEqual([
            'Renombrar',
            expect.stringContaining('Moneda'),
            'Copiar como texto',
            'Duplicar lista',
            'Reiniciar compras',
        ]);

        await page.keyboard.press('Escape');

        await expect(page.locator('#list-menu')).toBeHidden();
        await expect(page.locator('#list-menu-button')).toBeFocused();
    });
});
