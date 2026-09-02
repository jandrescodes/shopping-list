<?php

use App\Models\ShoppingList;
use Illuminate\Support\Facades\DB;

it('generates a unique 22-character slug from the base64url alphabet', function () {
    $list = ShoppingList::create(['name' => 'Feria']);

    expect($list->slug)
        ->toHaveLength(22)
        ->toMatch('/^[A-Za-z0-9_-]+$/')
        ->not->toMatch('/^[0-9]+$/');
});

it('generates different slugs for different lists', function () {
    $list1 = ShoppingList::create(['name' => 'Feria']);
    $list2 = ShoppingList::create(['name' => 'Despensa']);

    expect($list1->slug)->not->toBe($list2->slug);
});

it('retries slug generation on collision', function () {
    $collisionSlug = 'abcdefghijklmnopqrstuv';

    DB::table('shopping_lists')->insert([
        'slug' => $collisionSlug,
        'name' => 'Existente',
    ]);

    $calls = 0;
    ShoppingList::$slugGenerator = function () use ($collisionSlug, &$calls) {
        $calls++;

        // First attempt returns the taken slug; the loop must retry.
        return $calls === 1
            ? $collisionSlug
            : rtrim(strtr(base64_encode(random_bytes(16)), '+/', '-_'), '=');
    };

    $list = ShoppingList::create(['name' => 'Nueva']);

    expect($calls)->toBe(2)
        ->and($list->slug)->not->toBe($collisionSlug)
        ->and($list->slug)->toHaveLength(22);
});

it('bumps the version counter and returns the new value', function () {
    $list = ShoppingList::create(['name' => 'Feria']);

    expect($list->fresh()->version)->toBe(0);

    $newVersion = $list->bumpVersion();

    expect($newVersion)->toBe(1);
    expect($list->fresh()->version)->toBe(1);

    $newVersion = $list->bumpVersion();

    expect($newVersion)->toBe(2);
    expect($list->fresh()->version)->toBe(2);
});
