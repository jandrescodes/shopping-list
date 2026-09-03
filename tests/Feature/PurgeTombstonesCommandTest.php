<?php

use App\Models\Item;
use App\Models\ShoppingList;

it('purges only tombstones older than --before and reports the count', function () {
    $list = ShoppingList::factory()->create();

    $old = Item::factory()->for($list, 'shoppingList')->create();
    $recent = Item::factory()->for($list, 'shoppingList')->create();
    $active = Item::factory()->for($list, 'shoppingList')->create();

    $old->delete();
    $recent->delete();
    Item::withTrashed()->whereKey($old->id)->update(['deleted_at' => now()->subMonths(2)]);
    Item::withTrashed()->whereKey($recent->id)->update(['deleted_at' => now()->subDay()]);

    $this->artisan('items:purge-tombstones', ['--before' => now()->subMonth()->toDateTimeString()])
        ->expectsOutputToContain('1')
        ->assertExitCode(0);

    expect(Item::withTrashed()->whereKey($old->id)->exists())->toBeFalse()
        ->and(Item::withTrashed()->whereKey($recent->id)->exists())->toBeTrue()
        ->and(Item::whereKey($active->id)->exists())->toBeTrue();
});

it('aborts when --before is missing', function () {
    $this->artisan('items:purge-tombstones')->assertExitCode(1);
});

it('aborts when --before is not a valid date', function () {
    $this->artisan('items:purge-tombstones', ['--before' => 'not-a-date'])->assertExitCode(1);
});
