<?php

use App\Models\ShoppingList;
use App\Support\ListVersion;

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

it('trims price and stores null when blank', function () {
    $list = ShoppingList::create(['name' => 'Feria']);

    $empty = $list->items()->create(['name' => 'Pan', 'price' => '']);
    $whitespace = $list->items()->create(['name' => 'Arroz', 'price' => '   ']);
    $absent = $list->items()->create(['name' => 'Aceite']);

    expect($empty->fresh()->price)->toBeNull()
        ->and($whitespace->fresh()->price)->toBeNull()
        ->and($absent->fresh()->price)->toBeNull();
});

it('trims price without touching the decimal separator', function () {
    $list = ShoppingList::create(['name' => 'Feria']);
    $item = $list->items()->make(['name' => 'Arroz', 'price' => ' 12,50 ']);

    expect($item->getAttributes()['price'])->toBe('12,50');
});

it('reads a persisted price as a string with two decimals', function () {
    $list = ShoppingList::create(['name' => 'Feria']);
    $item = $list->items()->create(['name' => 'Pan', 'price' => '7']);

    expect($item->price)->toBe('7.00')
        ->and($item->fresh()->price)->toBe('7.00');
});

it('keeps price untouched by the double save of ListVersion::write', function () {
    $list = ShoppingList::create(['name' => 'Feria']);
    $item = $list->items()->create(['name' => 'Pan']);

    ListVersion::write($list, function () use ($item) {
        $item->price = ' 12.50 ';
        $item->save();

        return $item;
    });

    expect($item->fresh()->price)->toBe('12.50')
        ->and($item->fresh()->version)->toBeGreaterThan(0);
});

it('soft deletes an item and excludes it from default relationship', function () {
    $list = ShoppingList::create(['name' => 'Feria']);
    $item = $list->items()->create(['name' => 'Pan']);

    $item->delete();

    expect($item->fresh()->deleted_at)->not->toBeNull()
        ->and($list->items()->count())->toBe(0)
        ->and($list->items()->withTrashed()->count())->toBe(1);
});
