<?php

use App\Models\ShoppingList;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function positionMigration(): object
{
    return require database_path('migrations/2026_10_24_000001_add_position_to_items_table.php');
}

it('backfills position keeping creation order and leaves no row at 0', function () {
    $migration = positionMigration();
    $migration->down();

    $first = ShoppingList::factory()->create();
    $second = ShoppingList::factory()->create();
    $row = fn (int $listId, string $name, string $createdAt, ?string $deletedAt = null) => DB::table('items')->insertGetId([
        'shopping_list_id' => $listId,
        'name' => $name,
        'is_purchased' => false,
        'version' => 1,
        'created_at' => $createdAt,
        'updated_at' => $createdAt,
        'deleted_at' => $deletedAt,
    ]);

    $late = $row($first->id, 'late', '2026-01-03 10:00:00');
    $early = $row($first->id, 'early', '2026-01-01 10:00:00');
    $tombstone = $row($first->id, 'gone', '2026-01-02 10:00:00', '2026-01-04 10:00:00');
    $other = $row($second->id, 'other', '2026-01-05 10:00:00');

    try {
        $migration->up();

        $positions = DB::table('items')->pluck('position', 'id');

        expect($positions[$early])->toBe(1024)
            ->and($positions[$tombstone])->toBe(2048)
            ->and($positions[$late])->toBe(3072)
            ->and($positions[$other])->toBe(1024)
            ->and($positions->contains(0))->toBeFalse();
    } finally {
        DB::table('items')->whereIn('id', [$late, $early, $tombstone, $other])->delete();
    }
});

it('makes position a required column without default', function () {
    $position = collect(Schema::getColumns('items'))->firstWhere('name', 'position');

    expect($position['nullable'])->toBeFalse()
        ->and($position['auto_increment'] ?? false)->toBeFalse()
        ->and(Schema::getColumnType('items', 'position'))->toBe('bigint')
        ->and($position['type'])->not->toContain('unsigned')
        ->and(collect(Schema::getIndexes('items'))->pluck('columns')->contains(['shopping_list_id', 'is_purchased', 'position']))->toBeTrue();
});

it('assigns the end position when an item is created without one', function () {
    $list = ShoppingList::factory()->create();

    $first = $list->items()->create(['name' => 'a'])->fresh();
    $second = $list->items()->create(['name' => 'b'])->fresh();
    $explicit = $list->items()->create(['name' => 'c', 'position' => 5])->fresh();

    expect($first->position)->toBe(1024)
        ->and($second->position)->toBe(2048)
        ->and($explicit->position)->toBe(5);
});
