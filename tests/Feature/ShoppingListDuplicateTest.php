<?php

use App\Models\Item;
use App\Models\ShoppingList;

it('creates a new list with a different slug', function () {
    $original = ShoppingList::factory()->create();

    $copy = $original->duplicate();

    expect($copy->exists)->toBeTrue()
        ->and($copy->slug)->not->toBe($original->slug)
        ->and($copy->id)->not->toBe($original->id);
});

it('names the copy "Copia de" plus the original name', function () {
    $original = ShoppingList::factory()->create(['name' => 'Compra semanal']);

    expect($original->duplicate()->name)->toBe('Copia de Compra semanal');
});

it('truncates the copy name to 60 characters, counting characters and not bytes', function () {
    $original = ShoppingList::factory()->create(['name' => str_repeat('ñ', 60)]);

    $name = $original->duplicate()->name;

    expect(mb_strlen($name))->toBe(60)
        ->and($name)->toBe(mb_substr('Copia de '.str_repeat('ñ', 60), 0, 60));
});

it('drops trailing spaces left by the truncation', function () {
    // "Copia de " is 9 characters: the 51st original character lands on the cut.
    $original = ShoppingList::factory()->create(['name' => str_repeat('a', 50).' '.str_repeat('b', 9)]);

    $name = $original->duplicate()->name;

    expect($name)->toBe('Copia de '.str_repeat('a', 50))
        ->and($name)->toBe(rtrim($name));
});

it('inherits the currency', function () {
    $original = ShoppingList::factory()->create(['currency' => 'USD']);

    expect($original->duplicate()->currency)->toBe('USD');
});

it('copies name, quantity and price as unmarked items without added_by', function () {
    $original = ShoppingList::factory()->create();
    Item::factory()->for($original, 'shoppingList')->create([
        'name' => 'Leche',
        'quantity' => '2',
        'price' => '12.50',
        'added_by' => 'Ana',
        'is_purchased' => true,
    ]);
    Item::factory()->for($original, 'shoppingList')->create([
        'name' => 'Pan',
        'quantity' => null,
        'price' => null,
        'added_by' => null,
        'is_purchased' => false,
    ]);

    $copied = $original->duplicate()->activeItemsOrdered();

    expect($copied)->toHaveCount(2);

    $milk = $copied->firstWhere('name', 'Leche');
    expect($milk->quantity)->toBe('2')
        ->and($milk->price)->toBe('12.50')
        ->and($milk->added_by)->toBeNull()
        ->and($milk->is_purchased)->toBeFalse();

    $bread = $copied->firstWhere('name', 'Pan');
    expect($bread->quantity)->toBeNull()
        ->and($bread->price)->toBeNull();
});

it('keeps a price of zero, which is not the same as no price', function () {
    $original = ShoppingList::factory()->create();
    Item::factory()->for($original, 'shoppingList')->create(['name' => 'Gratis', 'price' => '0.00']);

    expect($original->duplicate()->activeItemsOrdered()->first()->price)->toBe('0.00');
});

it('copies the items in the creation order of the original', function () {
    $original = ShoppingList::factory()->create();
    Item::factory()->for($original, 'shoppingList')->create(['name' => 'Tercero', 'created_at' => now()->subMinutes(1)]);
    Item::factory()->for($original, 'shoppingList')->create(['name' => 'Primero', 'created_at' => now()->subMinutes(30)]);
    Item::factory()->for($original, 'shoppingList')->create(['name' => 'Segundo', 'created_at' => now()->subMinutes(10)]);

    $copy = $original->duplicate();

    expect($copy->activeItemsOrdered()->pluck('name')->all())->toBe(['Primero', 'Segundo', 'Tercero']);
});

it('does not copy deleted items', function () {
    $original = ShoppingList::factory()->create();
    Item::factory()->for($original, 'shoppingList')->create(['name' => 'Queda']);
    Item::factory()->for($original, 'shoppingList')->create(['name' => 'Borrado'])->delete();

    $copy = $original->duplicate();

    expect($copy->items()->withTrashed()->pluck('name')->all())->toBe(['Queda']);
});

it('copies an empty list as an empty list', function () {
    $original = ShoppingList::factory()->create(['name' => 'Vacía', 'currency' => 'Bs']);

    $copy = $original->duplicate();

    expect($copy->name)->toBe('Copia de Vacía')
        ->and($copy->items()->count())->toBe(0);
});

it('copies every item even when the original has more than 200', function () {
    $original = ShoppingList::factory()->create();
    Item::factory()->count(ShoppingList::MAX_ACTIVE_ITEMS + 1)->for($original, 'shoppingList')->create();

    expect($original->duplicate()->items()->count())->toBe(ShoppingList::MAX_ACTIVE_ITEMS + 1);
});

it('leaves the original list and its items untouched', function () {
    $original = ShoppingList::factory()->create(['name' => 'Original']);
    $item = Item::factory()->for($original, 'shoppingList')->create(['is_purchased' => true]);

    $original->duplicate();

    expect($original->fresh()->name)->toBe('Original')
        ->and($original->fresh()->version)->toBe(0)
        ->and($original->items()->count())->toBe(1)
        ->and($item->fresh()->is_purchased)->toBeTrue()
        ->and($item->fresh()->version)->toBe(0);
});

it('creates nothing when copying an item fails midway', function () {
    $original = ShoppingList::factory()->create();
    Item::factory()->for($original, 'shoppingList')->create(['name' => 'Uno', 'created_at' => now()->subMinutes(5)]);
    Item::factory()->for($original, 'shoppingList')->create(['name' => 'Boom', 'created_at' => now()->subMinutes(1)]);

    Item::creating(function (Item $item) {
        if ($item->name === 'Boom') {
            throw new RuntimeException('forced failure');
        }
    });

    $listsBefore = ShoppingList::count();
    $itemsBefore = Item::count();

    try {
        expect(fn () => $original->duplicate())->toThrow(RuntimeException::class);
    } finally {
        Item::flushEventListeners();
    }

    expect(ShoppingList::count())->toBe($listsBefore)
        ->and(Item::count())->toBe($itemsBefore);
});
