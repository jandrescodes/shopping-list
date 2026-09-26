<?php

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('creates the shopping_lists table with the expected columns', function () {
    expect(Schema::hasTable('shopping_lists'))->toBeTrue();

    expect(Schema::hasColumns('shopping_lists', [
        'id',
        'slug',
        'name',
        'currency',
        'version',
        'created_at',
        'updated_at',
    ]))->toBeTrue();
});

it('adds a currency column of five characters defaulting to Bs', function () {
    expect(Schema::hasColumn('shopping_lists', 'currency'))->toBeTrue()
        ->and(Schema::getColumnType('shopping_lists', 'currency'))->toBe('varchar');

    $currency = collect(Schema::getColumns('shopping_lists'))->firstWhere('name', 'currency');

    expect($currency)->not->toBeNull()
        ->and(preg_replace('/\s+/', '', $currency['type']))->toBe('varchar(5)')
        ->and($currency['nullable'])->toBeFalse()
        ->and($currency['default'])->toBeIn(['Bs', "'Bs'"]);
});

it('defaults the version counter to zero', function () {
    $id = DB::table('shopping_lists')->insertGetId([
        'slug' => 'abcdefghijklmnopqrstuv',
        'name' => 'Feria',
    ]);

    expect(DB::table('shopping_lists')->where('id', $id)->value('version'))
        ->toBe(0);
});

it('enforces a unique index on slug', function () {
    DB::table('shopping_lists')->insert([
        'slug' => 'abcdefghijklmnopqrstuv',
        'name' => 'Feria',
    ]);

    DB::table('shopping_lists')->insert([
        'slug' => 'abcdefghijklmnopqrstuv',
        'name' => 'Otra',
    ]);
})->throws(UniqueConstraintViolationException::class);
