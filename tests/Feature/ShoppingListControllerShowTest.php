<?php

use App\Models\Item;
use App\Models\ShoppingList;

it('returns the list with version and server-ordered items', function () {
    $list = ShoppingList::factory()->create(['name' => 'Feria']);
    $list->forceFill(['version' => 7])->save();

    // Insert in a deliberately mixed order.
    $b = Item::factory()->for($list)->create(['name' => 'B', 'is_purchased' => false, 'created_at' => now()->subMinutes(5)]);
    $d = Item::factory()->for($list)->create(['name' => 'D', 'is_purchased' => true, 'created_at' => now()->subMinutes(1)]);
    $a = Item::factory()->for($list)->create(['name' => 'A', 'is_purchased' => false, 'created_at' => now()->subMinutes(10)]);
    $c = Item::factory()->for($list)->create(['name' => 'C', 'is_purchased' => true, 'created_at' => now()->subMinutes(8)]);

    $response = $this->getJson("/api/lists/{$list->slug}");

    $response->assertOk()
        ->assertJsonPath('slug', $list->slug)
        ->assertJsonPath('name', 'Feria')
        ->assertJsonPath('version', 7);

    expect(array_column($response->json('items'), 'name'))->toBe(['A', 'B', 'C', 'D']);
    expect(array_keys($response->json('items.0')))
        ->toBe(['id', 'name', 'quantity', 'added_by', 'is_purchased', 'version']);
});

it('returns 404 for an unknown slug', function () {
    $this->getJson('/api/lists/does-not-exist-000000')->assertNotFound();
});
