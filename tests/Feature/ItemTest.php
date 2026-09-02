<?php

use App\Models\ShoppingList;

it('allows two items with the same name in the same list', function () {
    $list = ShoppingList::create(['name' => 'Feria']);

    $item1 = $list->items()->create(['name' => 'Leche']);
    $item2 = $list->items()->create(['name' => 'Leche']);

    expect($list->items()->count())->toBe(2)
        ->and($item1->id)->not->toBe($item2->id);
});

it('trims quantity and stores null when blank', function () {
    $list = ShoppingList::create(['name' => 'Feria']);
    $item = $list->items()->create(['name' => 'Leche', 'quantity' => '   ']);

    expect($item->fresh()->quantity)->toBeNull();
});

it('trims added_by and stores null when blank', function () {
    $list = ShoppingList::create(['name' => 'Feria']);
    $item = $list->items()->create(['name' => 'Leche', 'added_by' => '  ']);

    expect($item->fresh()->added_by)->toBeNull();
});

it('soft deletes an item and excludes it from default relationship', function () {
    $list = ShoppingList::create(['name' => 'Feria']);
    $item = $list->items()->create(['name' => 'Pan']);

    $item->delete();

    expect($item->fresh()->deleted_at)->not->toBeNull()
        ->and($list->items()->count())->toBe(0)
        ->and($list->items()->withTrashed()->count())->toBe(1);
});
