<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\StockLevel>
 */
class StockLevelFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quantity = fake()->numberBetween(0, 1000);
        $reservedQuantity = fake()->numberBetween(0, (int) ($quantity * 0.3));

        return [
            'product_id' => \App\Models\Product::factory(),
            'warehouse_id' => \App\Models\Warehouse::factory(),
            'quantity' => $quantity,
            'reserved_quantity' => $reservedQuantity,
            'average_cost' => fake()->randomFloat(2, 5, 500),
            'last_movement_at' => now(),
        ];
    }
}
