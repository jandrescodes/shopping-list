<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Item extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['name', 'quantity', 'added_by', 'is_purchased'];

    protected $casts = [
        'is_purchased' => 'boolean',
    ];

    public function shoppingList(): BelongsTo
    {
        return $this->belongsTo(ShoppingList::class);
    }

    /**
     * Server-fixed order: not purchased first, then purchased; each group by
     * creation date ascending.
     */
    public function scopeOrderedActive(Builder $query): void
    {
        $query->orderBy('is_purchased')->orderBy('created_at')->orderBy('id');
    }

    public function setQuantityAttribute(?string $value): void
    {
        $trimmed = $value === null ? null : trim($value);
        $this->attributes['quantity'] = $trimmed === '' ? null : $trimmed;
    }

    public function setAddedByAttribute(?string $value): void
    {
        $trimmed = $value === null ? null : trim($value);
        $this->attributes['added_by'] = $trimmed === '' ? null : $trimmed;
    }
}
