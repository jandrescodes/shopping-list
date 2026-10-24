<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ShoppingList extends Model
{
    use HasFactory;

    public const MAX_ACTIVE_ITEMS = 200;

    public const POSITION_STEP = 1024;

    protected $fillable = ['name', 'currency', 'budget'];

    protected $casts = [
        'budget' => 'decimal:2',
    ];

    /**
     * Test seam: when set, replaces the CSPRNG slug generator so a test can
     * force a collision. Production code never touches this.
     *
     * @var (callable(): string)|null
     */
    public static $slugGenerator = null;

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function items(): HasMany
    {
        return $this->hasMany(Item::class);
    }

    protected static function booted(): void
    {
        static::creating(function (ShoppingList $list) {
            if (is_null($list->slug)) {
                $list->slug = self::generateUniqueSlug();
            }
        });
    }

    public function bumpVersion(): int
    {
        $this->increment('version');

        return $this->version;
    }

    /**
     * @return Collection<int, Item>
     */
    public function activeItemsOrdered(): Collection
    {
        return $this->items()->orderedActive()->get();
    }

    /**
     * @return Collection<int, Item>
     */
    public function activeItemsChangedSince(int $cursor): Collection
    {
        return $this->items()->orderedActive()->where('version', '>', $cursor)->get();
    }

    /**
     * @return array<int, int>
     */
    public function deletedItemIdsSince(int $cursor): array
    {
        return $this->items()->onlyTrashed()->where('version', '>', $cursor)->pluck('id')->all();
    }

    /**
     * Resolves a raw sync cursor query param into a usable version bound, or
     * null when it's missing, malformed, or past the list's current version —
     * any of which means the client needs a full sync.
     */
    public function resolveSyncCursor(mixed $raw): ?int
    {
        $cursor = is_string($raw) && ctype_digit($raw) ? (int) $raw : null;

        return $cursor !== null && $cursor <= $this->version ? $cursor : null;
    }

    /**
     * Position that places an item right before the active item `$beforeId`,
     * or at the end when it is null, unknown, deleted or from another list.
     * `$moving` is left out of the neighbour search. Returns null when the gap
     * before the neighbour is exhausted and the list needs renumbering.
     */
    public function positionBefore(?int $beforeId, ?Item $moving): ?int
    {
        $others = fn () => $this->items()->when($moving, fn ($q) => $q->whereKeyNot($moving->getKey()));
        $before = $beforeId === null ? null : $others()->whereKey($beforeId)->first();

        if ($before === null) {
            return ($others()->max('position') ?? 0) + self::POSITION_STEP;
        }

        $prev = $others()
            ->where(fn ($q) => $q->where('position', '<', $before->position)
                ->orWhere(fn ($q) => $q->where('position', $before->position)->where('id', '<', $before->id)))
            ->orderByDesc('position')->orderByDesc('id')->first();

        if ($prev === null) {
            return $before->position - self::POSITION_STEP;
        }

        $position = ($prev->position + $before->position) >> 1;

        return $position > $prev->position && $position < $before->position ? $position : null;
    }

    /**
     * Moves an item right before `$beforeId` (or to the end) and returns the
     * items whose position changed: empty when it is already there, every
     * active item when the list had to be renumbered.
     *
     * @return Collection<int, Item>
     */
    public function moveItem(Item $item, ?int $beforeId): Collection
    {
        $ordered = $this->itemsExcept($item);
        $slot = $this->slotOf($ordered, $beforeId);

        if ($beforeId === $item->id || $this->isInPlace($item, $ordered, $slot)) {
            return new Collection;
        }

        $position = $this->positionBefore($beforeId, $item);

        if ($position === null) {
            return $this->renumberItems($ordered, $item, $slot ?? false);
        }

        $item->update(['position' => $position]);

        return new Collection([$item]);
    }

    /**
     * Whether moving `$item` before `$beforeId` would change nothing, so the
     * caller can skip the versioned write.
     */
    public function isItemInPlace(Item $item, ?int $beforeId): bool
    {
        $ordered = $this->itemsExcept($item);

        return $beforeId === $item->id || $this->isInPlace($item, $ordered, $this->slotOf($ordered, $beforeId));
    }

    /**
     * @return Collection<int, Item>
     */
    private function itemsExcept(Item $item): Collection
    {
        return $this->items()->whereKeyNot($item->getKey())->orderBy('position')->orderBy('id')->get();
    }

    /**
     * Index of the neighbour in `$ordered`, or null when the target is the end.
     *
     * @param  Collection<int, Item>  $ordered
     */
    private function slotOf(Collection $ordered, ?int $beforeId): ?int
    {
        $slot = $beforeId === null ? false : $ordered->search(fn (Item $other) => $other->id === $beforeId);

        return $slot === false ? null : $slot;
    }

    /**
     * Rewrites every active position in steps of 1024 with `$item` placed at
     * `$slot` of `$ordered` (the end when false).
     *
     * @param  Collection<int, Item>  $ordered  active items without `$item`, in order
     * @return Collection<int, Item>
     */
    private function renumberItems(Collection $ordered, Item $item, int|false $slot): Collection
    {
        $sequence = $ordered->values();
        $sequence->splice($slot === false ? $sequence->count() : $slot, 0, [$item]);

        return $sequence->each(function (Item $each, int $index) {
            $each->update(['position' => ($index + 1) * self::POSITION_STEP]);
        });
    }

    private function isInPlace(Item $item, Collection $ordered, ?int $slot): bool
    {
        $after = fn (Item $other) => [$other->position, $other->id] <=> [$item->position, $item->id];
        $previous = $slot === null ? $ordered->last() : ($slot > 0 ? $ordered[$slot - 1] : null);
        $next = $slot === null ? null : $ordered[$slot];

        return ($previous === null || $after($previous) < 0) && ($next === null || $after($next) > 0);
    }

    public function hasReachedActiveItemLimit(): bool
    {
        return $this->items()->count() >= self::MAX_ACTIVE_ITEMS;
    }

    public function hasPurchasedItems(): bool
    {
        return $this->items()->where('is_purchased', true)->exists();
    }

    /**
     * @return Collection<int, Item>
     */
    public function purgePurchasedItems(): Collection
    {
        $purchased = $this->items()->where('is_purchased', true)->get();
        $purchased->each->delete();

        return $purchased;
    }

    /**
     * Unmarks every active purchased item, reading the database state at the
     * moment it runs, and returns the items it changed.
     *
     * @return Collection<int, Item>
     */
    public function resetPurchasedItems(): Collection
    {
        $purchased = $this->items()->where('is_purchased', true)->get();
        $purchased->each->update(['is_purchased' => false]);

        return $purchased;
    }

    /**
     * Whether any of the given ids is an active, unmarked item of this list.
     *
     * @param  array<int, int>  $ids
     */
    public function hasRestorableItems(array $ids): bool
    {
        return $this->items()->whereIn('id', $ids)->where('is_purchased', false)->exists();
    }

    /**
     * Marks as purchased the given items that are, in the database at the
     * moment it runs, active items of this list still unmarked; any other id
     * is ignored. Returns the items it changed.
     *
     * @param  array<int, int>  $ids
     * @return Collection<int, Item>
     */
    public function restorePurchasedItems(array $ids): Collection
    {
        $restorable = $this->items()->whereIn('id', $ids)->where('is_purchased', false)->get();
        $restorable->each->update(['is_purchased' => true]);

        return $restorable;
    }

    /**
     * Creates a new list named "Copia de <name>" (cut to the 60-character name
     * limit) with this list's currency, plus an unmarked copy of every active
     * item's name, quantity, price and position, so the manual order carries
     * over. Who added each item is not carried over, and deleted items stay
     * behind. All or nothing.
     */
    public function duplicate(): self
    {
        return DB::transaction(function () {
            // Fresh from the database: the current name and currency win over
            // whatever this instance was loaded or created with.
            $source = $this->fresh();

            $copy = static::create([
                'name' => rtrim(mb_substr('Copia de '.$source->name, 0, 60)),
                'currency' => $source->currency,
                'budget' => $source->budget,
            ]);

            $source->items()->orderBy('position')->orderBy('id')->get()->each(
                fn (Item $item) => $copy->items()->create($item->only(['name', 'quantity', 'price', 'position']))
            );

            return $copy;
        });
    }

    /**
     * Physically deletes the list and every one of its items, active rows and
     * tombstones alike.
     */
    public function deleteWithItems(): void
    {
        $this->items()->withTrashed()->forceDelete();
        $this->forceDelete();
    }

    private static function generateUniqueSlug(): string
    {
        do {
            $slug = self::generateSlug();
        } while (static::withoutGlobalScopes()->where('slug', $slug)->exists());

        return $slug;
    }

    private static function generateSlug(): string
    {
        if (self::$slugGenerator !== null) {
            return (self::$slugGenerator)();
        }

        return rtrim(strtr(base64_encode(random_bytes(16)), '+/', '-_'), '=');
    }
}
