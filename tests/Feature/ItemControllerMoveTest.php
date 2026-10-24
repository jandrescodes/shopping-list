<?php

use App\Models\Item;
use App\Models\ShoppingList;

function moveFixture(): array
{
    $list = ShoppingList::factory()->create();
    $items = collect(['a' => 1024, 'b' => 2048, 'c' => 3072])
        ->map(fn ($position, $name) => $list->items()->create(['name' => $name, 'position' => $position]));

    return [$list, $items];
}

it('moves an item before its neighbour and persists the position', function () {
    [$list, $i] = moveFixture();

    $this->patchJson("/api/lists/{$list->slug}/items/{$i['c']->id}/position", ['before_id' => $i['b']->id])
        ->assertOk()
        ->assertJsonPath('id', $i['c']->id)
        ->assertJsonPath('position', 1536);

    expect($i['c']->fresh()->position)->toBe(1536);
});

it('moves to the end with a null before_id', function () {
    [$list, $i] = moveFixture();

    $this->patchJson("/api/lists/{$list->slug}/items/{$i['a']->id}/position", ['before_id' => null])
        ->assertOk()
        ->assertJsonPath('position', 4096);
});

it('bumps the list version once and stamps it on the moved item', function () {
    [$list, $i] = moveFixture();
    $before = $list->fresh()->version;

    $this->patchJson("/api/lists/{$list->slug}/items/{$i['c']->id}/position", ['before_id' => $i['b']->id])
        ->assertOk()
        ->assertJsonPath('version', $before + 1);

    expect($list->fresh()->version)->toBe($before + 1)
        ->and($i['c']->fresh()->version)->toBe($before + 1);
});

it('rejects a missing or invalid before_id with 422', function (array $body) {
    [$list, $i] = moveFixture();

    $this->patchJson("/api/lists/{$list->slug}/items/{$i['c']->id}/position", $body)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('before_id');
})->with([
    'absent' => [[]],
    'zero' => [['before_id' => 0]],
    'negative' => [['before_id' => -3]],
    'text' => [['before_id' => 'abc']],
    'decimal' => [['before_id' => 1.5]],
    'array' => [['before_id' => [1]]],
]);

it('answers 404 for an unknown list or an item of another list', function () {
    [$list, $i] = moveFixture();
    $other = Item::factory()->for(ShoppingList::factory()->create())->create();

    $this->patchJson('/api/lists/does-not-exist-000000/items/'.$i['a']->id.'/position', ['before_id' => null])
        ->assertNotFound()
        ->assertExactJson(['message' => 'Not Found']);
    $this->patchJson("/api/lists/{$list->slug}/items/{$other->id}/position", ['before_id' => null])
        ->assertNotFound();
});

it('ignores a purchased item with 200 and without bumping the version', function () {
    [$list, $i] = moveFixture();
    $i['c']->update(['is_purchased' => true]);
    $version = $list->fresh()->version;

    $this->patchJson("/api/lists/{$list->slug}/items/{$i['c']->id}/position", ['before_id' => $i['a']->id])
        ->assertOk()
        ->assertJsonPath('position', 3072);

    expect($list->fresh()->version)->toBe($version)
        ->and($i['c']->fresh()->position)->toBe(3072);
});

it('answers 404 for a deleted item', function () {
    [$list, $i] = moveFixture();
    $i['b']->delete();

    $this->patchJson("/api/lists/{$list->slug}/items/{$i['b']->id}/position", ['before_id' => null])
        ->assertNotFound();
});

it('does not bump the version when the item is already in that place', function () {
    [$list, $i] = moveFixture();
    $version = $list->fresh()->version;

    $this->patchJson("/api/lists/{$list->slug}/items/{$i['a']->id}/position", ['before_id' => $i['b']->id])->assertOk();
    $this->patchJson("/api/lists/{$list->slug}/items/{$i['c']->id}/position", ['before_id' => null])->assertOk();
    $this->patchJson("/api/lists/{$list->slug}/items/{$i['b']->id}/position", ['before_id' => $i['b']->id])->assertOk();

    expect($list->fresh()->version)->toBe($version);
});

it('brings the moved item with its position in the next sync delta', function () {
    [$list, $i] = moveFixture();
    $cursor = $this->getJson("/api/lists/{$list->slug}/items")->json('cursor');

    $this->patchJson("/api/lists/{$list->slug}/items/{$i['c']->id}/position", ['before_id' => $i['b']->id])->assertOk();

    $this->getJson("/api/lists/{$list->slug}/items?cursor={$cursor}")
        ->assertOk()
        ->assertJsonCount(1, 'items')
        ->assertJsonPath('items.0.id', $i['c']->id)
        ->assertJsonPath('items.0.position', 1536);
});

it('stamps every item with the same version when the list is renumbered', function () {
    $list = ShoppingList::factory()->create();
    $a = $list->items()->create(['name' => 'a', 'position' => 1024]);
    $b = $list->items()->create(['name' => 'b', 'position' => 1025]);
    $c = $list->items()->create(['name' => 'c', 'position' => 5000]);
    $version = $list->fresh()->version;

    $this->patchJson("/api/lists/{$list->slug}/items/{$c->id}/position", ['before_id' => $b->id])->assertOk();

    foreach ([$a, $b, $c] as $item) {
        expect($item->fresh()->version)->toBe($version + 1);
    }
    expect($list->fresh()->version)->toBe($version + 1)
        ->and($a->fresh()->position)->toBe(1024)
        ->and($c->fresh()->position)->toBe(2048)
        ->and($b->fresh()->position)->toBe(3072);
});

it('keeps items added by another device at the end and in their own order', function () {
    [$list, $i] = moveFixture();
    $x = $list->items()->create(['name' => 'x']);
    $y = $list->items()->create(['name' => 'y']);

    $this->patchJson("/api/lists/{$list->slug}/items/{$i['c']->id}/position", ['before_id' => $i['a']->id])->assertOk();

    $names = $list->items()->orderBy('position')->orderBy('id')->pluck('name')->all();
    expect($names)->toBe(['c', 'a', 'b', 'x', 'y'])
        ->and($x->fresh()->position)->toBeLessThan($y->fresh()->position);
});
