<?php

namespace App\Http\Resources;

use App\Models\ShoppingList;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ShoppingList
 */
class ShoppingListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'slug' => $this->slug,
            'name' => $this->name,
            'currency' => $this->currency,
            'budget' => $this->budget,
            'version' => $this->version,
        ];
    }
}
