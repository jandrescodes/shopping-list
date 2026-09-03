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
    $response->assertSee('Renombrar');
    $response->assertSee('Eliminar');
    $response->assertSee('Limpiar comprados');
    // Delete + purge go through an explicit confirmation panel (RF-9, RF-19).
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
