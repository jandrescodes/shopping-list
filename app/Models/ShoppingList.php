<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShoppingList extends Model
{
    use HasFactory;

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
