<?php

namespace Database\Factories;

use App\Models\Item;
use App\Models\ShoppingList;
use Illuminate\Database\Eloquent\Factories\Factory;

class ItemFactory extends Factory
{
    protected $model = Item::class;

    public function definition(): array
    {
        return [
            'shopping_list_id' => ShoppingList::factory(),
            'name' => fake()->words(2, true),
            'quantity' => fake()->randomElement([null, '1', '2', '500g', '1 kg']),
            'added_by' => fake()->randomElement([null, fake()->firstName()]),
        ];
    }
}
