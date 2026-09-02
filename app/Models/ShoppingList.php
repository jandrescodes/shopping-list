<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShoppingList extends Model
{
    use HasFactory;

    protected $fillable = ['name'];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function items(): HasMany
    {
        return $this->hasMany(Item::class);
    }

    public static function boot(): void
    {
        parent::boot();

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
        return rtrim(strtr(base64_encode(random_bytes(16)), '+/', '-_'), '=');
    }
}
