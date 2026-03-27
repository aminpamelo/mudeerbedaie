<?php

namespace Database\Factories;

use App\Models\AssetCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Asset>
 */
class AssetFactory extends Factory
{
    private static int $counter = 0;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        self::$counter++;

        return [
            'asset_tag' => 'AST-'.str_pad(self::$counter, 4, '0', STR_PAD_LEFT),
            'asset_category_id' => AssetCategory::factory(),
            'name' => fake()->words(3, true),
            'brand' => fake()->randomElement(['Dell', 'HP', 'Lenovo', 'Apple', 'Samsung', 'Acer']),
            'model' => fake()->bothify('Model-??-###'),
            'serial_number' => fake()->optional()->bothify('SN-##########'),
            'purchase_date' => fake()->dateTimeBetween('-3 years', '-1 month'),
            'purchase_price' => fake()->randomFloat(2, 500, 10000),
            'warranty_expiry' => fake()->optional()->dateTimeBetween('now', '+2 years'),
            'condition' => fake()->randomElement(['new', 'good', 'fair', 'poor']),
            'status' => 'available',
            'notes' => fake()->optional()->sentence(),
        ];
    }

    /**
     * Set status as assigned.
     */
    public function assigned(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'assigned',
        ]);
    }

    /**
     * Set status as under_repair.
     */
    public function underRepair(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'under_repair',
        ]);
    }
}
