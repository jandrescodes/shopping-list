<?php

use App\Models\Item;
use App\Models\ShoppingList;

it('toggles is_purchased without a confirmation step and bumps the version', function () {
    $list = ShoppingList::factory()->create();
    $item = Item::factory()->for($list, 'shoppingList')->create(['is_purchased' => false]);

    $this->patchJson("/api/lists/{$list->slug}/items/{$item->id}", ['is_purchased' => true])
        ->assertOk()
        ->assertJson(['id' => $item->id, 'is_purchased' => true, 'version' => 1]);

    expect($item->fresh()->is_purchased)->toBeTrue()
        ->and($list->fresh()->version)->toBe(1);
});

it('keeps both changes on two sequential field-by-field patches', function () {
    $list = ShoppingList::factory()->create();
    $item = Item::factory()->for($list, 'shoppingList')->create([
        'name' => 'Leche',
        'is_purchased' => false,
    ]);

    $this->patchJson("/api/lists/{$list->slug}/items/{$item->id}", ['name' => 'Leche entera'])->assertOk();
    $this->patchJson("/api/lists/{$list->slug}/items/{$item->id}", ['is_purchased' => true])
        ->assertOk()
        ->assertJsonPath('version', 2);

    $fresh = $item->fresh();
    expect($fresh->name)->toBe('Leche entera')
        ->and($fresh->is_purchased)->toBeTrue();
});

it('edits the name and quantity applying the validation rules', function () {
    $list = ShoppingList::factory()->create();
    $item = Item::factory()->for($list, 'shoppingList')->create();

    $this->patchJson("/api/lists/{$list->slug}/items/{$item->id}", [
        'name' => '  Pan integral  ',
        'quantity' => '  3  ',
    ])->assertOk()->assertJson(['name' => 'Pan integral', 'quantity' => '3']);

    $this->patchJson("/api/lists/{$list->slug}/items/{$item->id}", ['name' => ''])->assertStatus(422);
    $this->patchJson("/api/lists/{$list->slug}/items/{$item->id}", ['name' => str_repeat('a', 101)])->assertStatus(422);
    $this->patchJson("/api/lists/{$list->slug}/items/{$item->id}", ['quantity' => str_repeat('a', 51)])->assertStatus(422);
});

it('blanks a whitespace-only quantity to null', function () {
    $list = ShoppingList::factory()->create();
    $item = Item::factory()->for($list, 'shoppingList')->create(['quantity' => '2']);

    $this->patchJson("/api/lists/{$list->slug}/items/{$item->id}", ['quantity' => '   '])
        ->assertOk()
        ->assertJson(['quantity' => null]);
});

it('returns 404 when the item belongs to another list', function () {
    $listA = ShoppingList::factory()->create();
    $listB = ShoppingList::factory()->create();
    $itemB = Item::factory()->for($listB, 'shoppingList')->create();

    $this->patchJson("/api/lists/{$listA->slug}/items/{$itemB->id}", ['is_purchased' => true])
        ->assertNotFound();

    expect($itemB->fresh()->is_purchased)->toBeFalse();
});

it('returns 404 when the list does not exist', function () {
    $this->patchJson('/api/lists/does-not-exist-000000/items/1', ['is_purchased' => true])->assertNotFound();
});
