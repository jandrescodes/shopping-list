<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class ShoppingList extends Model
{
    use HasFactory;

    public const MAX_ACTIVE_ITEMS = 200;

    protected $fillable = ['name'];

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
