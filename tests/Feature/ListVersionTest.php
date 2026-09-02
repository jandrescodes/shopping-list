<?php

use App\Models\ShoppingList;
use App\Support\ListVersion;

it('bumps the list version on each sequential write and stamps touched items', function () {
    $list = ShoppingList::create(['name' => 'Feria']);

    $itemA = ListVersion::write($list, fn (ShoppingList $l) => $l->items()->create(['name' => 'Leche']));

    expect($list->fresh()->version)->toBe(1)
        ->and($itemA->fresh()->version)->toBe(1);

    $itemB = ListVersion::write($list, fn (ShoppingList $l) => $l->items()->create(['name' => 'Pan']));

    expect($list->fresh()->version)->toBe(2)
        ->and($itemB->fresh()->version)->toBe(2)
        ->and($itemA->fresh()->version)->toBe(1);
});

it('passes the new version number to the callback', function () {
    $list = ShoppingList::create(['name' => 'Feria']);

    $seen = ListVersion::write($list, fn (ShoppingList $l, int $version) => $version);

    expect($seen)->toBe(1);
});

it('stamps the version on a soft-deleted item (tombstone)', function () {
    $list = ShoppingList::create(['name' => 'Feria']);
    $item = $list->items()->create(['name' => 'Sal']);

    ListVersion::write($list, function () use ($item) {
        $item->delete();

        return $item;
    });

    expect($list->fresh()->version)->toBe(1)
        ->and($item->fresh()->version)->toBe(1)
        ->and($item->fresh()->trashed())->toBeTrue();
});

it('rolls back the version bump when the callback throws', function () {
    $list = ShoppingList::create(['name' => 'Feria']);

    expect(fn () => ListVersion::write($list, function () {
        throw new RuntimeException('boom');
    }))->toThrow(RuntimeException::class);

    expect($list->fresh()->version)->toBe(0);
});
