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
        ->assertExactJsonStructure(['id', 'name', 'quantity', 'price', 'added_by', 'is_purchased', 'position', 'version'])
        ->assertJson([
            'name' => 'Leche',
            'quantity' => '2 L',
            'price' => null,
            'added_by' => 'Ana',
            'is_purchased' => false,
            'version' => 1,
        ]);

    expect($list->fresh()->version)->toBe(1);
});

it('returns the price as a canonical decimal string', function () {
    $list = ShoppingList::factory()->create();

    $response = $this->postJson("/api/lists/{$list->slug}/items", [
        'name' => 'Leche',
        'price' => '12,50',
    ]);

    $response->assertCreated()
        ->assertJsonPath('price', '12.50');

    $this->assertIsString($response->json('price'));
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

it('places the new item right before an active before_id neighbour', function () {
    $list = ShoppingList::factory()->create();
    $a = $list->items()->create(['name' => 'a']);
    $b = $list->items()->create(['name' => 'b']);

    $this->postJson("/api/lists/{$list->slug}/items", ['name' => 'nuevo', 'before_id' => $b->id])
        ->assertCreated()
        ->assertExactJsonStructure(['id', 'name', 'quantity', 'price', 'added_by', 'is_purchased', 'position', 'version'])
        ->assertJsonPath('position', ($a->position + $b->position) >> 1);

    expect($list->items()->orderBy('position')->pluck('name')->all())->toBe(['a', 'nuevo', 'b'])
        ->and($list->fresh()->version)->toBe(1);
});

it('puts the new item at the end when before_id is null, absent, unknown or deleted', function () {
    $list = ShoppingList::factory()->create();
    $a = $list->items()->create(['name' => 'a']);
    $gone = $list->items()->create(['name' => 'gone']);
    $gone->delete();

    foreach ([['before_id' => null], [], ['before_id' => 999999], ['before_id' => $gone->id]] as $n => $extra) {
        $this->postJson("/api/lists/{$list->slug}/items", ['name' => "n{$n}"] + $extra)->assertCreated();
    }

    expect($list->items()->orderBy('position')->pluck('name')->all())->toBe(['a', 'n0', 'n1', 'n2', 'n3']);
});

it('rejects an invalid before_id with 422 and does not store it as a column', function () {
    $list = ShoppingList::factory()->create();

    $this->postJson("/api/lists/{$list->slug}/items", ['name' => 'x', 'before_id' => 0])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('before_id');
    $this->postJson("/api/lists/{$list->slug}/items", ['name' => 'x', 'before_id' => 'abc'])
        ->assertUnprocessable();
    expect($list->items()->count())->toBe(0);
});
