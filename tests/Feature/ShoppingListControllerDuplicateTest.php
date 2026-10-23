<?php

use App\Models\Item;
use App\Models\ShoppingList;

it('duplicates a list and returns slug, name and absolute url like creating one', function () {
    config(['app.url' => 'https://compras.example']);
    $original = ShoppingList::factory()->create(['name' => 'Compra semanal']);

    $response = $this->postJson("/api/lists/{$original->slug}/duplicate")->assertCreated();

    $slug = $response->json('slug');
    expect($slug)->toMatch('/^[A-Za-z0-9_-]{22}$/')
        ->and($slug)->not->toBe($original->slug)
        ->and($response->json('name'))->toBe('Copia de Compra semanal')
        ->and($response->json('url'))->toBe("https://compras.example/l/{$slug}");

    $this->assertDatabaseHas('shopping_lists', ['slug' => $slug, 'name' => 'Copia de Compra semanal']);
});

it('points Location at the created list resource', function () {
    $original = ShoppingList::factory()->create();

    $response = $this->postJson("/api/lists/{$original->slug}/duplicate")->assertCreated();

    $response->assertHeader('Location', url('/api/lists/'.$response->json('slug')));
});

it('exposes the copied items and currency through the new slug', function () {
    $original = ShoppingList::factory()->create(['currency' => 'USD']);
    Item::factory()->for($original, 'shoppingList')->create(['name' => 'Leche', 'price' => '12.50', 'is_purchased' => true]);

    $slug = $this->postJson("/api/lists/{$original->slug}/duplicate")->json('slug');

    $this->getJson("/api/lists/{$slug}")
        ->assertOk()
        ->assertJsonPath('currency', 'USD')
        ->assertJsonPath('items.0.name', 'Leche')
        ->assertJsonPath('items.0.price', '12.50')
        ->assertJsonPath('items.0.is_purchased', false);
});

it('leaves the original list untouched', function () {
    $original = ShoppingList::factory()->create(['name' => 'Original']);
    $item = Item::factory()->for($original, 'shoppingList')->create(['is_purchased' => true]);

    $this->postJson("/api/lists/{$original->slug}/duplicate")->assertCreated();

    expect($original->fresh()->name)->toBe('Original')
        ->and($original->fresh()->version)->toBe(0)
        ->and($original->items()->count())->toBe(1)
        ->and($item->fresh()->is_purchased)->toBeTrue();
});

it('creates one more list per request, so a double submit makes two copies', function () {
    $original = ShoppingList::factory()->create();

    $first = $this->postJson("/api/lists/{$original->slug}/duplicate")->json('slug');
    $second = $this->postJson("/api/lists/{$original->slug}/duplicate")->json('slug');

    expect($first)->not->toBe($second)
        ->and(ShoppingList::count())->toBe(3);
});

it('answers the uniform 404 for a slug that does not exist', function () {
    $this->postJson('/api/lists/does-not-exist-000000/duplicate')
        ->assertNotFound()
        ->assertExactJson(['message' => 'Not Found']);

    expect(ShoppingList::count())->toBe(0);
});
