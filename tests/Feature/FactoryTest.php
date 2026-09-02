<?php

use App\Models\Item;
use App\Models\ShoppingList;

it('creates a shopping list with items via factories', function () {
    $list = ShoppingList::factory()
        ->has(Item::factory()->count(3))
        ->create();

    expect($list->items)->toHaveCount(3)
        ->and($list->items->first())->toBeInstanceOf(Item::class);
});
