<?php

use App\Support\ItemOrdering;

function others(array $positions): array
{
    $list = [];
    foreach ($positions as $id => $position) {
        $list[] = ['id' => $id, 'position' => $position];
    }

    return $list;
}

it('places the item at the midpoint before a neighbour', function () {
    expect(ItemOrdering::move(others([1 => 1024, 2 => 2048]), 3, 4096, 2))->toBe([3 => 1536]);
});

it('places the item one step before the first neighbour', function () {
    expect(ItemOrdering::move(others([1 => 1024, 2 => 2048]), 3, 4096, 1))->toBe([3 => 0]);
});

it('places the item one step after the last one when there is no neighbour', function () {
    expect(ItemOrdering::move(others([1 => 1024, 2 => 2048]), 3, 1, null))->toBe([3 => 3072]);
});

it('falls back to the end when the neighbour is unknown', function () {
    expect(ItemOrdering::move(others([1 => 1024, 2 => 2048]), 3, 1, 999))->toBe([3 => 3072]);
});

it('returns nothing when the item is its own neighbour or already in place', function () {
    $others = others([2 => 2048, 3 => 3072]);

    expect(ItemOrdering::move(others([]), 1, 5000, null))->toBe([])
        ->and(ItemOrdering::move($others, 1, 1024, 1))->toBe([])
        ->and(ItemOrdering::move($others, 1, 1024, 2))->toBe([])
        ->and(ItemOrdering::move(others([1 => 1024, 2 => 2048]), 3, 3072, null))->toBe([]);
});

it('breaks position ties by id when deciding if it is in place', function () {
    expect(ItemOrdering::move(others([1 => 1024, 3 => 1024]), 2, 1024, 3))->toBe([])
        ->and(ItemOrdering::move(others([1 => 1024, 3 => 1024]), 4, 1024, 1))->toBe([4 => 0]);
});

it('renumbers everything in steps of 1024 when the gap is exhausted', function () {
    $result = ItemOrdering::move(others([1 => 1024, 2 => 1025]), 3, 5000, 2);

    expect($result)->toBe([1 => 1024, 3 => 2048, 2 => 3072]);
});

it('renumbers with the moving item at the end when the slot is null', function () {
    $result = ItemOrdering::move(others([1 => 1024, 2 => 1025, 4 => 1026]), 3, 1, 4);

    expect($result)->toBe([1 => 1024, 2 => 2048, 3 => 3072, 4 => 4096]);
});
