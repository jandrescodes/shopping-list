<?php

use App\Models\Item;
use App\Models\ShoppingList;

it('soft deletes an item, stamps the version and hides it from show', function () {
    $list = ShoppingList::factory()->create();
    $item = Item::factory()->for($list, 'shoppingList')->create();

    $this->deleteJson("/api/lists/{$list->slug}/items/{$item->id}")->assertNoContent();

    $trashed = Item::withTrashed()->find($item->id);
    expect($trashed->deleted_at)->not->toBeNull()
        ->and($trashed->version)->toBe(1)
        ->and($list->fresh()->version)->toBe(1);

    $this->getJson("/api/lists/{$list->slug}")
        ->assertOk()
        ->assertJsonCount(0, 'items');
});

it('returns 404 when the item belongs to another list', function () {
    $listA = ShoppingList::factory()->create();
    $listB = ShoppingList::factory()->create();
    $itemB = Item::factory()->for($listB, 'shoppingList')->create();

    $this->deleteJson("/api/lists/{$listA->slug}/items/{$itemB->id}")->assertNotFound();

    expect($itemB->fresh()->deleted_at)->toBeNull();
});

it('returns 404 when the list does not exist', function () {
    $this->deleteJson('/api/lists/does-not-exist-000000/items/1')->assertNotFound();
});
