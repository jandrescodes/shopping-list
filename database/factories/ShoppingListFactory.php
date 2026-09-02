<?php

namespace Database\Factories;

use App\Models\ShoppingList;
use Illuminate\Database\Eloquent\Factories\Factory;

class ShoppingListFactory extends Factory
{
    protected $model = ShoppingList::class;

    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
        ];
    }
}
