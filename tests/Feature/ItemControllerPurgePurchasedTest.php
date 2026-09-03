<?php

use App\Models\Item;
use App\Models\ShoppingList;

it('soft deletes only the purchased items and returns their ids', function () {
    $list = ShoppingList::factory()->create();
    $bought = Item::factory()->count(2)->for($list, 'shoppingList')->create(['is_purchased' => true]);
    $pending = Item::factory()->count(3)->for($list, 'shoppingList')->create(['is_purchased' => false]);

    $response = $this->postJson("/api/lists/{$list->slug}/items/purge-purchased")->assertOk();

    expect($response->json('deleted_ids'))->toEqualCanonicalizing($bought->pluck('id')->all());

    foreach ($bought as $item) {
        expect(Item::withTrashed()->find($item->id)->deleted_at)->not->toBeNull()
            ->and(Item::withTrashed()->find($item->id)->version)->toBe(1);
    }
    foreach ($pending as $item) {
        expect($item->fresh()->deleted_at)->toBeNull();
    }

    expect($list->fresh()->version)->toBe(1);
});

it('evaluates purchased against the database, not the client view', function () {
    $list = ShoppingList::factory()->create();
    $item = Item::factory()->for($list, 'shoppingList')->create(['is_purchased' => true]);

    $this->postJson("/api/lists/{$list->slug}/items/purge-purchased")
        ->assertOk()
        ->assertJsonPath('deleted_ids', [$item->id]);
});

it('changes nothing and keeps the version when nothing is purchased', function () {
    $list = ShoppingList::factory()->create();
    Item::factory()->count(2)->for($list, 'shoppingList')->create(['is_purchased' => false]);

    $this->postJson("/api/lists/{$list->slug}/items/purge-purchased")
        ->assertOk()
        ->assertExactJson(['deleted_ids' => []]);

    expect($list->fresh()->version)->toBe(0)
        ->and($list->items()->count())->toBe(2);
});

it('returns 404 when the list does not exist', function () {
    $this->postJson('/api/lists/does-not-exist-000000/items/purge-purchased')->assertNotFound();
});
