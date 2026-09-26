<?php

use App\Models\Item;
use App\Models\ShoppingList;

it('renders the list page with its header, add input and list-level actions', function () {
    $list = ShoppingList::factory()->create(['name' => 'Feria del sábado']);
    Item::factory()->for($list)->create(['name' => 'Pan', 'is_purchased' => false]);
    Item::factory()->for($list)->create(['name' => 'Leche', 'is_purchased' => true]);

    $response = $this->get("/l/{$list->slug}");

    $response->assertOk();
    $response->assertSee('Feria del sábado');
    $response->assertSee('id="new-item"', false);
    $response->assertSee('id="new-quantity"', false);
    $response->assertSee('name="quantity"', false);
    $response->assertSee('maxlength="50"', false);
    $response->assertSee('Renombrar');
    $response->assertSee('Eliminar');
    $response->assertSee('Limpiar comprados');
    // Delete + purge go through an explicit confirmation panel.
    $response->assertSee('Sí, eliminar');
    $response->assertSee('Sí, limpiar');
});

it('orders items with purchased ones struck through and last (RF-18)', function () {
    $list = ShoppingList::factory()->create();
    $done = Item::factory()->for($list)->create(['name' => 'Comprado', 'is_purchased' => true]);
    $todo = Item::factory()->for($list)->create(['name' => 'Pendiente', 'is_purchased' => false]);

    $html = $this->get("/l/{$list->slug}")->assertOk()->getContent();

    expect(strpos($html, 'Pendiente'))->toBeLessThan(strpos($html, 'Comprado'));
    expect($html)->toMatch('/line-through[^>]*data-item-id="'.$done->id.'"/');
});

it('shows an empty state when the list has no items', function () {
    $list = ShoppingList::factory()->create();

    $this->get("/l/{$list->slug}")
        ->assertOk()
        ->assertSee('Esta lista está vacía. Agrega el primer ítem arriba.')
        ->assertDontSee('Limpiar comprados');
});

it('escapes user content, never rendering it as HTML (RF-32)', function () {
    $list = ShoppingList::factory()->create();
    Item::factory()->for($list)->create(['name' => '<script>alert(1)</script>']);

    $this->get("/l/{$list->slug}")
        ->assertOk()
        ->assertDontSee('<script>alert(1)</script>', false)
        ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
});

it('returns 404 for an unknown list slug', function () {
    $this->get('/l/does-not-exist')->assertNotFound();
});

it('offers an optional price input in a second row of the add form (RF-1)', function () {
    $list = ShoppingList::factory()->create();

    $this->get("/l/{$list->slug}")
        ->assertOk()
        ->assertSee('id="new-price"', false)
        ->assertSee('name="price"', false)
        ->assertSee('inputmode="decimal"', false)
        ->assertSee('Precio (opcional)');
});

it('renders the currency chip with the list currency and its inline edit input (RF-11)', function () {
    $list = ShoppingList::factory()->create(['currency' => 'US$']);

    $response = $this->get("/l/{$list->slug}")->assertOk();

    $response->assertSee('id="currency-chip"', false);
    $response->assertSee('US$');
    $response->assertSee('id="currency-input"', false);
    $response->assertSee('maxlength="5"', false);
    $response->assertSee('Moneda de la lista');
});

it('shows the price beside its item and no marker when the item has none (RF-8)', function () {
    $list = ShoppingList::factory()->create();
    Item::factory()->for($list)->create(['name' => 'Leche', 'price' => '12.50', 'is_purchased' => false]);
    Item::factory()->for($list)->create(['name' => 'Pan', 'price' => null, 'is_purchased' => false]);

    $html = $this->get("/l/{$list->slug}")->assertOk()->getContent();

    expect($html)->toContain('Leche');
    // The row renders the number only; the currency symbol belongs to the
    // header and the total (RF-14), and the price-less item renders nothing.
    expect($html)->toContain('12,50');
    // Counted over the server-rendered rows only: the Alpine row lives in a
    // <template> (a possibility, not a rendered item), so it would double
    // the count for every list regardless of its data.
    $serverHtml = substr($html, 0, strpos($html, '<template'));
    expect(substr_count($serverHtml, 'item-price'))->toBe(1);
});

it('renders the pending total with the list currency, counting only unpaid items (RF-15, RF-16)', function () {
    $list = ShoppingList::factory()->create();
    Item::factory()->for($list)->create(['name' => 'Leche', 'price' => '12.50', 'is_purchased' => false]);
    Item::factory()->for($list)->create(['name' => 'Pan', 'price' => '3.50', 'is_purchased' => true]);

    $response = $this->get("/l/{$list->slug}")->assertOk();

    $response->assertSee('pending-total', false);
    // 3,50 belongs to an already purchased item, so it stays out of the total.
    $response->assertSee('Bs 12,50');
});

it('hides the total while no pending item carries a price (RF-17)', function () {
    $list = ShoppingList::factory()->create();
    Item::factory()->for($list)->create(['name' => 'Pan', 'price' => '3.50', 'is_purchased' => true]);
    Item::factory()->for($list)->create(['name' => 'Sal', 'price' => null, 'is_purchased' => false]);

    $this->get("/l/{$list->slug}")
        ->assertOk()
        ->assertDontSee('pending-total', false)
        ->assertDontSee('Bs ');
});

it('offers a link back to the home page', function () {
    $list = ShoppingList::factory()->create();

    $this->get("/l/{$list->slug}")
        ->assertOk()
        ->assertSee('<nav', false)
        ->assertSee('href="/"', false)
        ->assertSee('Mis listas');
});

it('does not show the back link on the home page itself', function () {
    $this->withoutVite();

    $this->get('/')->assertOk()->assertDontSee('<nav', false);
});
