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
        'version',
        'created_at',
        'updated_at',
    ]))->toBeTrue();
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
