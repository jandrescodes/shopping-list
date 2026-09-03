<?php

use App\Models\Item;
use App\Models\ShoppingList;

it('returns the full active state ordered by RF-18 when the cursor is missing', function () {
    $list = ShoppingList::factory()->create();
    $bought = Item::factory()->for($list, 'shoppingList')->create(['is_purchased' => true, 'created_at' => now()->subMinutes(10)]);
    $first = Item::factory()->for($list, 'shoppingList')->create(['is_purchased' => false, 'created_at' => now()->subMinutes(5)]);
    $second = Item::factory()->for($list, 'shoppingList')->create(['is_purchased' => false, 'created_at' => now()->subMinutes(1)]);

    $this->getJson("/api/lists/{$list->slug}/items")
        ->assertOk()
        ->assertJsonPath('deleted_ids', [])
        ->assertJsonPath('cursor', $list->fresh()->version)
        ->assertJsonPath('items.0.id', $first->id)
        ->assertJsonPath('items.1.id', $second->id)
        ->assertJsonPath('items.2.id', $bought->id);
});

it('returns a delta with changed items and tombstoned ids for a valid cursor', function () {
    $list = ShoppingList::factory()->create();
    $keep = Item::factory()->for($list, 'shoppingList')->create();
    $changed = Item::factory()->for($list, 'shoppingList')->create();
    $removed = Item::factory()->for($list, 'shoppingList')->create(['added_by' => 'Ana']);

    $base = $this->getJson("/api/lists/{$list->slug}/items")->json('cursor');

    $this->patchJson("/api/lists/{$list->slug}/items/{$changed->id}", ['name' => 'Nuevo'])->assertOk();
    $this->deleteJson("/api/lists/{$list->slug}/items/{$removed->id}")->assertNoContent();

    $response = $this->getJson("/api/lists/{$list->slug}/items?cursor={$base}")->assertOk();

    expect($response->json('items'))->toHaveCount(1)
        ->and($response->json('items.0.id'))->toBe($changed->id)
        ->and($response->json('deleted_ids'))->toBe([$removed->id])
        ->and($response->json('cursor'))->toBe($list->fresh()->version);

    // Tombstone payload is id-only, never name/added_by/timestamps.
    expect($response->json('items.0'))->toHaveKeys(['id', 'name', 'quantity', 'added_by', 'is_purchased', 'version']);

    // A second call with the fresh cursor yields nothing new.
    $this->getJson("/api/lists/{$list->slug}/items?cursor=".$response->json('cursor'))
        ->assertOk()
        ->assertExactJson(['items' => [], 'deleted_ids' => [], 'cursor' => $list->fresh()->version]);
});

it('falls back to the full state for a non-integer or out-of-range cursor', function () {
    $list = ShoppingList::factory()->create();
    Item::factory()->count(2)->for($list, 'shoppingList')->create();

    foreach (['abc', '-1', '999'] as $cursor) {
        $this->getJson("/api/lists/{$list->slug}/items?cursor={$cursor}")
            ->assertOk()
            ->assertJsonCount(2, 'items')
            ->assertJsonPath('deleted_ids', [])
            ->assertJsonPath('cursor', $list->fresh()->version);
    }
});

it('never includes tombstones in the full-state load', function () {
    $list = ShoppingList::factory()->create();
    $item = Item::factory()->for($list, 'shoppingList')->create();
    $this->deleteJson("/api/lists/{$list->slug}/items/{$item->id}")->assertNoContent();

    $this->getJson("/api/lists/{$list->slug}/items")
        ->assertOk()
        ->assertJsonCount(0, 'items')
        ->assertJsonPath('deleted_ids', []);
});

it('returns 404 when syncing a deleted or unknown list (RF-27)', function () {
    $list = ShoppingList::factory()->create();
    $this->deleteJson("/api/lists/{$list->slug}")->assertNoContent();

    $this->getJson("/api/lists/{$list->slug}/items")->assertNotFound();
    $this->getJson('/api/lists/does-not-exist-000000/items')->assertNotFound();
});
