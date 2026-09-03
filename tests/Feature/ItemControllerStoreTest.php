<?php

use App\Models\Item;
use App\Models\ShoppingList;

it('creates an item, not purchased, in ItemResource shape and stamps the version', function () {
    $list = ShoppingList::factory()->create();

    $response = $this->postJson("/api/lists/{$list->slug}/items", [
        'name' => '  Leche  ',
        'quantity' => ' 2 L ',
        'added_by' => ' Ana ',
    ]);

    $response->assertCreated()
        ->assertExactJsonStructure(['id', 'name', 'quantity', 'added_by', 'is_purchased', 'version'])
        ->assertJson([
            'name' => 'Leche',
            'quantity' => '2 L',
            'added_by' => 'Ana',
            'is_purchased' => false,
            'version' => 1,
        ]);

    expect($list->fresh()->version)->toBe(1);
});

it('stores an item without quantity or added_by', function () {
    $list = ShoppingList::factory()->create();

    $this->postJson("/api/lists/{$list->slug}/items", ['name' => 'Pan'])
        ->assertCreated()
        ->assertJson(['quantity' => null, 'added_by' => null]);
});

it('allows two items with the same name in one list', function () {
    $list = ShoppingList::factory()->create();

    $this->postJson("/api/lists/{$list->slug}/items", ['name' => 'Pan'])->assertCreated();
    $this->postJson("/api/lists/{$list->slug}/items", ['name' => 'Pan'])->assertCreated();

    expect($list->items()->where('name', 'Pan')->count())->toBe(2);
});

it('rejects an invalid item with 422', function () {
    $list = ShoppingList::factory()->create();

    $this->postJson("/api/lists/{$list->slug}/items", ['name' => ''])->assertStatus(422);
    $this->postJson("/api/lists/{$list->slug}/items", ['name' => str_repeat('a', 101)])->assertStatus(422);
    $this->postJson("/api/lists/{$list->slug}/items", ['name' => 'x', 'quantity' => str_repeat('a', 51)])->assertStatus(422);
    $this->postJson("/api/lists/{$list->slug}/items", ['name' => 'x', 'added_by' => str_repeat('a', 51)])->assertStatus(422);

    expect($list->items()->count())->toBe(0);
});

it('accepts the 200th item and rejects the 201st with a Spanish limit message', function () {
    $list = ShoppingList::factory()->create();
    Item::factory()->count(199)->for($list, 'shoppingList')->create();

    $this->postJson("/api/lists/{$list->slug}/items", ['name' => 'número 200'])->assertCreated();

    expect($list->items()->count())->toBe(200);

    $this->postJson("/api/lists/{$list->slug}/items", ['name' => 'número 201'])
        ->assertStatus(422)
        ->assertJsonPath('message', 'La lista alcanzó el límite de 200 ítems.');

    expect($list->items()->count())->toBe(200);
});

it('stores a name with markup literally, unescaped', function () {
    $list = ShoppingList::factory()->create();

    $this->postJson("/api/lists/{$list->slug}/items", ['name' => '<script>alert(1)</script>'])
        ->assertCreated()
        ->assertJsonPath('name', '<script>alert(1)</script>');

    $this->assertDatabaseHas('items', ['name' => '<script>alert(1)</script>']);
});

it('returns 404 when adding an item to an unknown list', function () {
    $this->postJson('/api/lists/does-not-exist-000000/items', ['name' => 'Pan'])->assertNotFound();
});
