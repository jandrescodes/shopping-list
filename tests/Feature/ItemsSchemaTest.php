<?php

use Illuminate\Support\Facades\Schema;

it('creates the items table with the expected columns', function () {
    expect(Schema::hasTable('items'))->toBeTrue();

    expect(Schema::hasColumns('items', [
        'id',
        'shopping_list_id',
        'name',
        'quantity',
        'price',
        'added_by',
        'is_purchased',
        'version',
        'created_at',
        'updated_at',
        'deleted_at',
    ]))->toBeTrue();
});

it('adds a nullable price column typed as decimal(10,2)', function () {
    expect(Schema::hasColumn('items', 'price'))->toBeTrue()
        ->and(Schema::getColumnType('items', 'price'))->toBe('decimal');

    $price = collect(Schema::getColumns('items'))->firstWhere('name', 'price');

    expect($price)->not->toBeNull()
        ->and(preg_replace('/\s+/', '', $price['type']))->toBe('decimal(10,2)')
        ->and($price['nullable'])->toBeTrue()
        ->and($price['default'])->toBeIn([null, 'NULL']);
});

it('indexes items by (shopping_list_id, version) and (shopping_list_id, is_purchased, created_at)', function () {
    $indexes = collect(Schema::getIndexes('items'))
        ->map(fn (array $index) => array_map('strtolower', $index['columns']));

    expect($indexes)->toContain(['shopping_list_id', 'version'])
        ->and($indexes)->toContain(['shopping_list_id', 'is_purchased', 'created_at']);
});
