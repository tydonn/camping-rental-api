<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Equipment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Equipment>
 */
class EquipmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'category_id' => CategoryFactory::new(),
            'name' => fake()->unique()->words(2, true),
            'description' => fake()->sentence(),
            'price_per_day' => fake()->numberBetween(10, 200) * 1000,
            'stock' => fake()->numberBetween(1, 20),
            'photo' => null,
        ];
    }
}
