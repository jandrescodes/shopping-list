<?php

use App\Models\Item;
use App\Models\ShoppingList;

/**
 * T20 — the five sync edge cases (RF-24). The cursor is the list version
 * counter and nothing else: no clock, client or database, takes part.
 */
it('falls back to the full active state for a missing, non-integer or too-large cursor', function (?string $cursor) {
    $list = ShoppingList::factory()->create();
    Item::factory()->count(2)->for($list, 'shoppingList')->create();
    $version = $list->fresh()->version;

    $query = $cursor === null ? '' : "?cursor={$cursor}";

    $this->getJson("/api/lists/{$list->slug}/items{$query}")
        ->assertOk()
        ->assertJsonCount(2, 'items')
        ->assertJsonPath('deleted_ids', [])
        ->assertJsonPath('cursor', $version);
})->with([
    'missing' => [null],
    'non-integer' => ['abc'],
    'negative' => ['-1'],
    'greater than version' => ['9999'],
]);

it('treats a cursor from another list as its own version: full load or harmless delta', function () {
    $listA = ShoppingList::factory()->create();
    $listB = ShoppingList::factory()->create();
    Item::factory()->count(2)->for($listA, 'shoppingList')->create();
    // Push list B's counter well past list A's.
    Item::factory()->count(5)->for($listB, 'shoppingList')->create()
        ->each(fn ($item) => $this->patchJson("/api/lists/{$listB->slug}/items/{$item->id}", ['name' => 'x']));

    $foreignCursor = $listB->fresh()->version; // larger than list A's version

    $this->getJson("/api/lists/{$listA->slug}/items?cursor={$foreignCursor}")
        ->assertOk()
        ->assertJsonCount(2, 'items')
        ->assertJsonPath('deleted_ids', [])
        ->assertJsonPath('cursor', $listA->fresh()->version);

    // A small in-range cursor from "another list" just yields a harmless delta
    // the client merges by id.
    $this->getJson("/api/lists/{$listA->slug}/items?cursor=1")
        ->assertOk()
        ->assertJsonPath('cursor', $listA->fresh()->version);
});

it('reports an item created and deleted between two cursors only in deleted_ids', function () {
    $list = ShoppingList::factory()->create();
    $base = $this->getJson("/api/lists/{$list->slug}/items")->json('cursor');

    $id = $this->postJson("/api/lists/{$list->slug}/items", ['name' => 'Fugaz'])->json('id');
    $this->deleteJson("/api/lists/{$list->slug}/items/{$id}")->assertNoContent();

    $response = $this->getJson("/api/lists/{$list->slug}/items?cursor={$base}")->assertOk();

    expect($response->json('items'))->toBe([])
        ->and($response->json('deleted_ids'))->toBe([$id]);
});

it('cuts by the version counter alone, with no clock involved', function () {
    $list = ShoppingList::factory()->create();
    $old = Item::factory()->for($list, 'shoppingList')->create();
    $base = $this->getJson("/api/lists/{$list->slug}/items")->json('cursor');

    // Jump a year forward: a time-based cut would drift, a counter-based one
    // does not.
    $this->travel(1)->years();
    $new = $this->postJson("/api/lists/{$list->slug}/items", ['name' => 'Nuevo'])->json('id');

    $response = $this->getJson("/api/lists/{$list->slug}/items?cursor={$base}")->assertOk();

    expect($response->json('items'))->toHaveCount(1)
        ->and($response->json('items.0.id'))->toBe($new)
        ->and($response->json('cursor'))->toBe($list->fresh()->version);

    $this->travelBack();
});
