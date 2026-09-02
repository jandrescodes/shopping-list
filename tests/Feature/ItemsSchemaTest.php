<?php

use Illuminate\Support\Facades\Schema;

it('creates the items table with the expected columns', function () {
    expect(Schema::hasTable('items'))->toBeTrue();

    expect(Schema::hasColumns('items', [
        'id',
        'shopping_list_id',
        'name',
        'quantity',
        'added_by',
        'is_purchased',
        'version',
        'created_at',
        'updated_at',
        'deleted_at',
    ]))->toBeTrue();
});

it('indexes items by (shopping_list_id, version) and (shopping_list_id, is_purchased, created_at)', function () {
    $indexes = collect(Schema::getIndexes('items'))
        ->map(fn (array $index) => array_map('strtolower', $index['columns']));

    expect($indexes)->toContain(['shopping_list_id', 'version'])
        ->and($indexes)->toContain(['shopping_list_id', 'is_purchased', 'created_at']);
});
