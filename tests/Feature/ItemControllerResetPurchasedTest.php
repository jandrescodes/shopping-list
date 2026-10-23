<?php

use App\Models\Item;
use App\Models\ShoppingList;

it('unmarks only the purchased items and returns their ids', function () {
    $list = ShoppingList::factory()->create();
    $bought = Item::factory()->count(2)->for($list, 'shoppingList')->create(['is_purchased' => true]);
    $pending = Item::factory()->count(3)->for($list, 'shoppingList')->create(['is_purchased' => false]);

    $response = $this->postJson("/api/lists/{$list->slug}/items/reset-purchased")->assertOk();

    expect($response->json('reset_ids'))->toEqualCanonicalizing($bought->pluck('id')->all());

    foreach ($bought as $item) {
        expect($item->fresh()->is_purchased)->toBeFalse();
    }
    foreach ($pending as $item) {
        expect($item->fresh()->is_purchased)->toBeFalse()
            ->and($item->fresh()->version)->toBe(0);
    }
});

it('keeps name, quantity and price of the reset items', function () {
    $list = ShoppingList::factory()->create();
    $item = Item::factory()->for($list, 'shoppingList')->create([
        'name' => 'Leche',
        'quantity' => '2',
        'price' => '12.50',
        'is_purchased' => true,
    ]);

    $this->postJson("/api/lists/{$list->slug}/items/reset-purchased")->assertOk();

    $fresh = $item->fresh();
    expect($fresh->name)->toBe('Leche')
        ->and($fresh->quantity)->toBe('2')
        ->and($fresh->price)->toBe('12.50')
        ->and($fresh->is_purchased)->toBeFalse();
});

it('bumps the list version once and stamps it on every reset item', function () {
    $list = ShoppingList::factory()->create();
    $bought = Item::factory()->count(3)->for($list, 'shoppingList')->create(['is_purchased' => true]);

    $this->postJson("/api/lists/{$list->slug}/items/reset-purchased")->assertOk();

    expect($list->fresh()->version)->toBe(1);

    foreach ($bought as $item) {
        expect($item->fresh()->version)->toBe(1);
    }
});

it('evaluates purchased against the database, not the client view', function () {
    $list = ShoppingList::factory()->create();
    $item = Item::factory()->for($list, 'shoppingList')->create(['is_purchased' => true]);

    $this->postJson("/api/lists/{$list->slug}/items/reset-purchased")
        ->assertOk()
        ->assertJsonPath('reset_ids', [$item->id]);
});

it('changes nothing and keeps the version when nothing is purchased', function () {
    $list = ShoppingList::factory()->create();
    Item::factory()->count(2)->for($list, 'shoppingList')->create(['is_purchased' => false]);

    $this->postJson("/api/lists/{$list->slug}/items/reset-purchased")
        ->assertOk()
        ->assertExactJson(['reset_ids' => []]);

    expect($list->fresh()->version)->toBe(0);
});

it('does nothing on a second reset right after the first', function () {
    $list = ShoppingList::factory()->create();
    Item::factory()->count(2)->for($list, 'shoppingList')->create(['is_purchased' => true]);

    $this->postJson("/api/lists/{$list->slug}/items/reset-purchased")->assertOk();
    $this->postJson("/api/lists/{$list->slug}/items/reset-purchased")
        ->assertOk()
        ->assertExactJson(['reset_ids' => []]);

    expect($list->fresh()->version)->toBe(1);
});

it('leaves deleted items alone', function () {
    $list = ShoppingList::factory()->create();
    $deleted = Item::factory()->for($list, 'shoppingList')->create(['is_purchased' => true]);
    $deleted->delete();
    $active = Item::factory()->for($list, 'shoppingList')->create(['is_purchased' => true]);

    $this->postJson("/api/lists/{$list->slug}/items/reset-purchased")
        ->assertOk()
        ->assertJsonPath('reset_ids', [$active->id]);

    expect(Item::withTrashed()->find($deleted->id)->is_purchased)->toBeTrue();
});

it('returns the same 404 as any missing list', function () {
    $this->postJson('/api/lists/does-not-exist-000000/items/reset-purchased')
        ->assertNotFound()
        ->assertExactJson(['message' => 'Not Found']);
});

it('brings the reset items back in the next sync delta', function () {
    $list = ShoppingList::factory()->create();
    $bought = Item::factory()->for($list, 'shoppingList')->create(['is_purchased' => true]);
    Item::factory()->for($list, 'shoppingList')->create(['is_purchased' => false]);

    $cursor = $this->getJson("/api/lists/{$list->slug}/items")->json('cursor');

    $this->postJson("/api/lists/{$list->slug}/items/reset-purchased")->assertOk();

    $response = $this->getJson("/api/lists/{$list->slug}/items?cursor={$cursor}")->assertOk();

    expect($response->json('items'))->toHaveCount(1)
        ->and($response->json('items.0.id'))->toBe($bought->id)
        ->and($response->json('items.0.is_purchased'))->toBeFalse()
        ->and($response->json('cursor'))->toBe($cursor + 1);
});
