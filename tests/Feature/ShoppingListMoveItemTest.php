<?php

use App\Models\ShoppingList;

function listWithPositions(array $positions): array
{
    $list = ShoppingList::factory()->create();
    $items = [];
    foreach ($positions as $name => $position) {
        $items[$name] = $list->items()->create(['name' => $name, 'position' => $position]);
    }

    return [$list, $items];
}

function orderedNames(ShoppingList $list): array
{
    return $list->items()->orderBy('position')->orderBy('id')->pluck('name')->all();
}

it('moves an item right before an active neighbour using the midpoint', function () {
    [$list, $i] = listWithPositions(['a' => 1024, 'b' => 2048, 'c' => 3072]);

    $changed = $list->moveItem($i['c'], $i['b']->id);

    expect($changed)->toHaveCount(1)
        ->and($i['c']->fresh()->position)->toBe(1536)
        ->and(orderedNames($list))->toBe(['a', 'c', 'b']);
});

it('moves to the very top before the first item', function () {
    [$list, $i] = listWithPositions(['a' => 1024, 'b' => 2048]);

    $list->moveItem($i['b'], $i['a']->id);

    expect($i['b']->fresh()->position)->toBe(0)
        ->and(orderedNames($list))->toBe(['b', 'a']);
});

it('moves to the end when no before_id is given', function () {
    [$list, $i] = listWithPositions(['a' => 1024, 'b' => 2048, 'c' => 3072]);

    $list->moveItem($i['a'], null);

    expect($i['a']->fresh()->position)->toBe(4096)
        ->and(orderedNames($list))->toBe(['b', 'c', 'a']);
});

it('moves to the end when the neighbour is deleted, foreign or unknown', function () {
    [$list, $i] = listWithPositions(['a' => 1024, 'b' => 2048, 'gone' => 3072]);
    [, $foreign] = listWithPositions(['x' => 1024]);
    $i['gone']->delete();

    foreach ([$i['gone']->id, $foreign['x']->id, 999999] as $beforeId) {
        $list->moveItem($i['a'], $beforeId);
        expect(orderedNames($list))->toBe(['b', 'a']);
        $i['a']->update(['position' => 1024]);
    }
});

it('changes nothing when the item is its own neighbour', function () {
    [$list, $i] = listWithPositions(['a' => 1024, 'b' => 2048]);

    expect($list->moveItem($i['a'], $i['a']->id))->toBeEmpty()
        ->and($i['a']->fresh()->position)->toBe(1024);
});

it('changes nothing when the item is already in that place', function () {
    [$list, $i] = listWithPositions(['a' => 1024, 'b' => 2048, 'c' => 3072]);

    expect($list->moveItem($i['a'], $i['b']->id))->toBeEmpty()
        ->and($list->moveItem($i['c'], null))->toBeEmpty()
        ->and($i['a']->fresh()->position)->toBe(1024)
        ->and($i['c']->fresh()->position)->toBe(3072);
});

it('renumbers every active item in steps of 1024 when the gap is exhausted', function () {
    [$list, $i] = listWithPositions(['a' => 1024, 'b' => 1025, 'c' => 5000]);
    $tombstone = $list->items()->create(['name' => 'gone', 'position' => 1]);
    $tombstone->delete();

    $changed = $list->moveItem($i['c'], $i['b']->id);

    expect(orderedNames($list))->toBe(['a', 'c', 'b'])
        ->and($changed->pluck('name')->sort()->values()->all())->toBe(['a', 'b', 'c'])
        ->and($i['a']->fresh()->position)->toBe(1024)
        ->and($i['c']->fresh()->position)->toBe(2048)
        ->and($i['b']->fresh()->position)->toBe(3072)
        ->and($tombstone->fresh()->position)->toBe(1);
});

it('treats a purchased neighbour as valid, since the sequence is single', function () {
    [$list, $i] = listWithPositions(['a' => 1024, 'b' => 2048, 'c' => 3072]);
    $i['b']->update(['is_purchased' => true]);

    $list->moveItem($i['c'], $i['b']->id);

    expect(orderedNames($list))->toBe(['a', 'c', 'b']);
});

it('computes the position for a new item without a moving item', function () {
    [$list, $i] = listWithPositions(['a' => 1024, 'b' => 2048]);

    expect($list->positionBefore($i['b']->id, null))->toBe(1536)
        ->and($list->positionBefore(null, null))->toBe(3072)
        ->and(ShoppingList::factory()->create()->positionBefore(null, null))->toBe(1024);
});
