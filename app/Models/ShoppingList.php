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

    protected $fillable = ['name', 'currency'];

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
     * item's name, quantity and price in creation order. Who added each item is
     * not carried over, and deleted items stay behind. All or nothing.
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
            ]);

            $source->items()->orderBy('created_at')->orderBy('id')->get()->each(
                fn (Item $item) => $copy->items()->create($item->only(['name', 'quantity', 'price']))
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
