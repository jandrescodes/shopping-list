<?php

use App\Models\Item;
use App\Models\ShoppingList;

it('aborts when the inactivity period is not configured', function () {
    config(['shopping_list.inactive_months' => null]);

    $this->artisan('lists:purge-inactive')
        ->expectsOutputToContain('SHOPPING_LIST_INACTIVE_MONTHS')
        ->assertExitCode(1);
});

it('reads the inactivity period from configuration and succeeds when it is set', function () {
    config(['shopping_list.inactive_months' => 12]);

    $this->artisan('lists:purge-inactive')->assertExitCode(0);
});

it('purges a list abandoned longer than the configured period and deletes its items', function () {
    config(['shopping_list.inactive_months' => 1]);

    $abandoned = ShoppingList::factory()->create();
    Item::factory()->for($abandoned, 'shoppingList')->create();
    Item::factory()->for($abandoned, 'shoppingList')->create()->delete();

    ShoppingList::whereKey($abandoned->id)->update(['updated_at' => now()->subMonths(2)]);

    $this->artisan('lists:purge-inactive')->assertExitCode(0);

    expect(ShoppingList::whereKey($abandoned->id)->exists())->toBeFalse();
    expect(Item::withTrashed()->where('shopping_list_id', $abandoned->id)->exists())->toBeFalse();
});

it('does not purge a list with recent activity', function () {
    config(['shopping_list.inactive_months' => 3]);

    $active = ShoppingList::factory()->create();

    $this->artisan('lists:purge-inactive')->assertExitCode(0);

    expect(ShoppingList::whereKey($active->id)->exists())->toBeTrue();
});

it('reports the number of purged lists', function () {
    config(['shopping_list.inactive_months' => 1]);

    $lists = ShoppingList::factory()->count(3)->create();

    ShoppingList::whereIn('id', [$lists[0]->id, $lists[1]->id])
        ->update(['updated_at' => now()->subMonths(2)]);

    $this->artisan('lists:purge-inactive')
        ->expectsOutputToContain('Listas purgadas: 2')
        ->assertExitCode(0);

    expect(ShoppingList::whereKey($lists[2]->id)->exists())->toBeTrue();
});

it('does not purge a list whose items were modified recently', function () {
    config(['shopping_list.inactive_months' => 1]);

    $list = ShoppingList::factory()->create();
    $item = Item::factory()->for($list, 'shoppingList')->create();

    ShoppingList::whereKey($list->id)->update(['updated_at' => now()->subMonths(2)]);

    $this->patchJson("/api/lists/{$list->slug}/items/{$item->id}", ['name' => 'Renovado'])
        ->assertOk();

    $this->artisan('lists:purge-inactive')->assertExitCode(0);

    expect(ShoppingList::whereKey($list->id)->exists())->toBeTrue();
});

it('reports zero and succeeds when there are no abandoned lists', function () {
    config(['shopping_list.inactive_months' => 1]);

    ShoppingList::factory()->create();

    $this->artisan('lists:purge-inactive')
        ->expectsOutputToContain('Listas purgadas: 0')
        ->assertExitCode(0);
});
