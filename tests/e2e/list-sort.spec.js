import { expect, test } from '@playwright/test';

// Smoke test of the drag-and-drop dependency: the list page bundle registers
// the x-sort directive, which attaches a Sortable instance to the element.

test('registers the x-sort plugin on the list page', async ({ page, request }) => {
    const res = await request.post('/api/lists', { data: { name: 'Orden' } });
    const { slug } = await res.json();
    await page.goto(`/l/${slug}`);
    await expect(page.locator('#client-item-list')).toBeVisible();

    const attached = await page.evaluate(() => {
        const ul = document.createElement('ul');
        ul.setAttribute('x-data', '');
        ul.setAttribute('x-sort', '');
        ul.innerHTML = '<li x-sort:item="1">a</li><li x-sort:item="2">b</li>';
        document.body.appendChild(ul);
        window.Alpine.initTree(ul);

        return Object.keys(ul).some((key) => key.startsWith('Sortable'));
    });

    expect(attached).toBe(true);
});
