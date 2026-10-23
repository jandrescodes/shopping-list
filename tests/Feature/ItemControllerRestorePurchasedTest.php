<?php

use App\Models\Item;
use App\Models\ShoppingList;

it('marks as purchased the given items that exist and are unmarked', function () {
    $list = ShoppingList::factory()->create();
    $items = Item::factory()->count(3)->for($list, 'shoppingList')->create(['is_purchased' => false]);
    $other = Item::factory()->for($list, 'shoppingList')->create(['is_purchased' => false]);

    $response = $this->postJson("/api/lists/{$list->slug}/items/restore-purchased", [
        'ids' => $items->pluck('id')->all(),
    ])->assertOk();

    expect($response->json('restored_ids'))->toEqualCanonicalizing($items->pluck('id')->all());

    foreach ($items as $item) {
        expect($item->fresh()->is_purchased)->toBeTrue();
    }
    expect($other->fresh()->is_purchased)->toBeFalse();
});

it('bumps the list version once and stamps it on every restored item', function () {
    $list = ShoppingList::factory()->create();
    $items = Item::factory()->count(3)->for($list, 'shoppingList')->create(['is_purchased' => false]);

    $this->postJson("/api/lists/{$list->slug}/items/restore-purchased", [
        'ids' => $items->pluck('id')->all(),
    ])->assertOk();

    expect($list->fresh()->version)->toBe(1);

    foreach ($items as $item) {
        expect($item->fresh()->version)->toBe(1);
    }
});

it('skips items that were deleted, already purchased or never existed', function () {
    $list = ShoppingList::factory()->create();
    $valid = Item::factory()->for($list, 'shoppingList')->create(['is_purchased' => false]);
    $deleted = Item::factory()->for($list, 'shoppingList')->create(['is_purchased' => false]);
    $deleted->delete();
    $already = Item::factory()->for($list, 'shoppingList')->create(['is_purchased' => true]);

    $response = $this->postJson("/api/lists/{$list->slug}/items/restore-purchased", [
        'ids' => [$valid->id, $deleted->id, $already->id, 999999],
    ])->assertOk();

    expect($response->json('restored_ids'))->toBe([$valid->id])
        ->and(Item::withTrashed()->find($deleted->id)->is_purchased)->toBeFalse()
        ->and($already->fresh()->version)->toBe(0);
});

it('ignores items that belong to another list', function () {
    $list = ShoppingList::factory()->create();
    $foreign = Item::factory()->for(ShoppingList::factory()->create(), 'shoppingList')->create(['is_purchased' => false]);

    $this->postJson("/api/lists/{$list->slug}/items/restore-purchased", ['ids' => [$foreign->id]])
        ->assertOk()
        ->assertExactJson(['restored_ids' => []]);

    expect($foreign->fresh()->is_purchased)->toBeFalse()
        ->and($list->fresh()->version)->toBe(0);
});

it('keeps the version when nothing applies', function () {
    $list = ShoppingList::factory()->create();
    $already = Item::factory()->for($list, 'shoppingList')->create(['is_purchased' => true]);

    $this->postJson("/api/lists/{$list->slug}/items/restore-purchased", ['ids' => [$already->id, 999999]])
        ->assertOk()
        ->assertExactJson(['restored_ids' => []]);

    expect($list->fresh()->version)->toBe(0);
});

it('rejects invalid ids with 422', function () {
    $list = ShoppingList::factory()->create();

    $this->postJson("/api/lists/{$list->slug}/items/restore-purchased", ['ids' => []])
        ->assertStatus(422)
        ->assertJsonValidationErrors('ids');
});

it('returns the same 404 as any missing list', function () {
    $this->postJson('/api/lists/does-not-exist-000000/items/restore-purchased', ['ids' => [1]])
        ->assertNotFound()
        ->assertExactJson(['message' => 'Not Found']);
});

it('undoes a reset: reset then restore leaves the purchased state as before', function () {
    $list = ShoppingList::factory()->create();
    $bought = Item::factory()->count(2)->for($list, 'shoppingList')->create(['is_purchased' => true]);

    $ids = $this->postJson("/api/lists/{$list->slug}/items/reset-purchased")->json('reset_ids');

    // Another device marks one of them again before the undo arrives.
    $this->patchJson("/api/lists/{$list->slug}/items/{$bought[0]->id}", ['is_purchased' => true])->assertOk();

    $this->postJson("/api/lists/{$list->slug}/items/restore-purchased", ['ids' => $ids])
        ->assertOk()
        ->assertExactJson(['restored_ids' => [$bought[1]->id]]);

    expect($bought[0]->fresh()->is_purchased)->toBeTrue()
        ->and($bought[1]->fresh()->is_purchased)->toBeTrue();
});
