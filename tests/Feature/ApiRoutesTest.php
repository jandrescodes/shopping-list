<?php

use App\Models\Item;
use App\Models\ShoppingList;
use Illuminate\Support\Facades\Route;

it('exposes exactly the nine contract routes, none behind auth', function () {
    $apiRoutes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route) => str_starts_with($route->uri(), 'api/'));

    expect($apiRoutes)->toHaveCount(9);

    $apiRoutes->each(function ($route) {
        expect($route->gatherMiddleware())->not->toContain('auth')
            ->and($route->gatherMiddleware())->not->toContain('auth:sanctum');
    });
});

it('has no route that enumerates lists', function () {
    $indexRoute = collect(Route::getRoutes()->getRoutes())
        ->first(fn ($route) => $route->uri() === 'api/lists' && in_array('GET', $route->methods()));

    expect($indexRoute)->toBeNull();
});

it('serves the same slug repeatedly without a session', function () {
    $list = ShoppingList::factory()->create();

    foreach (range(1, 3) as $_) {
        $this->getJson("/api/lists/{$list->slug}")->assertOk();
    }
});

it('scopes nested item routes to their list', function () {
    $listA = ShoppingList::factory()->create();
    $listB = ShoppingList::factory()->create();
    $itemB = Item::factory()->for($listB)->create();

    $this->patchJson("/api/lists/{$listA->slug}/items/{$itemB->id}", ['is_purchased' => true])
        ->assertNotFound();
});
