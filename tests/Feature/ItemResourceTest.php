<?php

use App\Http\Resources\ItemResource;
use App\Models\ShoppingList;

it('returns exactly the expected 6 keys via ItemResource', function () {
    $list = ShoppingList::create(['name' => 'Feria']);
    $item = $list->items()->create([
        'name' => 'Leche',
        'quantity' => '1 L',
        'added_by' => 'Ana',
    ]);

    $result = ItemResource::make($item)->toArray(request());

    expect($result)->toHaveKeys([
        'id',
        'name',
        'quantity',
        'added_by',
        'is_purchased',
        'version',
    ])->and($result)->not->toHaveKey('shopping_list_id')
        ->and($result)->not->toHaveKey('created_at')
        ->and($result)->not->toHaveKey('updated_at')
        ->and($result)->not->toHaveKey('deleted_at');
});
