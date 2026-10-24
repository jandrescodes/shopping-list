<?php

use App\Models\Item;
use App\Models\ShoppingList;

it('returns the list with version and server-ordered items', function () {
    $list = ShoppingList::factory()->create(['name' => 'Feria']);
    $list->forceFill(['version' => 7])->save();

    // Insert in a deliberately mixed order; position decides, not creation.
    Item::factory()->for($list)->create(['name' => 'B', 'is_purchased' => false, 'position' => 2048]);
    Item::factory()->for($list)->create(['name' => 'D', 'is_purchased' => true, 'position' => 4096]);
    Item::factory()->for($list)->create(['name' => 'A', 'is_purchased' => false, 'position' => 1024]);
    Item::factory()->for($list)->create(['name' => 'C', 'is_purchased' => true, 'position' => 3072]);

    $response = $this->getJson("/api/lists/{$list->slug}");

    $response->assertOk()
        ->assertJsonPath('slug', $list->slug)
        ->assertJsonPath('name', 'Feria')
        ->assertJsonPath('currency', 'Bs')
        ->assertJsonPath('budget', null)
        ->assertJsonPath('version', 7);

    expect(array_column($response->json('items'), 'name'))->toBe(['A', 'B', 'C', 'D']);
    expect(array_keys($response->json('items.0')))
        ->toBe(['id', 'name', 'quantity', 'price', 'added_by', 'is_purchased', 'position', 'version']);
});

it('returns the budget as a two-decimal string, zero included', function () {
    $list = ShoppingList::factory()->create(['budget' => 150]);

    $this->getJson("/api/lists/{$list->slug}")->assertOk()->assertJsonPath('budget', '150.00');

    $list->update(['budget' => 0]);

    $this->getJson("/api/lists/{$list->slug}")->assertOk()->assertJsonPath('budget', '0.00');
});

it('returns 404 for an unknown slug', function () {
    $this->getJson('/api/lists/does-not-exist-000000')->assertNotFound();
});
