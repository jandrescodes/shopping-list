<?php

use App\Http\Resources\ShoppingListResource;
use App\Models\ShoppingList;

it('returns exactly the expected 4 keys via ShoppingListResource', function () {
    $list = ShoppingList::create(['name' => 'Feria']);
    $list->update(['currency' => 'USD']);

    $result = ShoppingListResource::make($list->fresh())->toArray(request());

    expect(array_keys($result))
        ->toBe(['slug', 'name', 'currency', 'version'])
        ->and($result['slug'])->toBe($list->slug)
        ->and($result['name'])->toBe('Feria')
        ->and($result['currency'])->toBe('USD')
        ->and($result['version'])->toBe(0)
        ->and($result)->not->toHaveKey('id')
        ->and($result)->not->toHaveKey('created_at');
});
