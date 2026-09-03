<?php

use App\Models\Item;
use App\Models\ShoppingList;
use Illuminate\Support\Facades\DB;

it('physically deletes the list with all its items and tombstones', function () {
    $list = ShoppingList::factory()->create();
    Item::factory()->for($list)->count(2)->create();
    $tombstone = Item::factory()->for($list)->create();
    $tombstone->delete();

    $this->deleteJson("/api/lists/{$list->slug}")->assertNoContent();

    expect(DB::table('shopping_lists')->where('id', $list->id)->count())->toBe(0)
        ->and(DB::table('items')->where('shopping_list_id', $list->id)->count())->toBe(0);
});

it('answers a deleted slug identically to a slug that never existed', function () {
    $list = ShoppingList::factory()->create();
    $this->deleteJson("/api/lists/{$list->slug}")->assertNoContent();

    $deleted = $this->getJson("/api/lists/{$list->slug}");
    $neverExisted = $this->getJson('/api/lists/never-existed-00000000');

    $deleted->assertNotFound();
    expect($deleted->getStatusCode())->toBe($neverExisted->getStatusCode())
        ->and($deleted->getContent())->toBe($neverExisted->getContent())
        ->and($deleted->headers->get('content-type'))->toBe($neverExisted->headers->get('content-type'));
});
