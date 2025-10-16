<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ProductOrderItem>
 */
class ProductOrderItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $unitPrice = fake()->randomFloat(2, 10, 1000);
        $quantity = fake()->numberBetween(1, 5);

        return [
            'order_id' => ProductOrder::factory(),
            'product_id' => Product::factory(),
            'product_name' => fake()->words(3, true),
            'sku' => fake()->unique()->regexify('[A-Z]{3}-[0-9]{4}'),
            'product_snapshot' => [],
            'quantity_ordered' => $quantity,
            'quantity_fulfilled' => 0,
            'quantity_cancelled' => 0,
            'quantity_returned' => 0,
            'unit_price' => $unitPrice,
            'total_price' => $unitPrice * $quantity,
            'unit_cost' => $unitPrice * 0.6, // 40% margin
            'status' => 'pending',
        ];
    }
}
