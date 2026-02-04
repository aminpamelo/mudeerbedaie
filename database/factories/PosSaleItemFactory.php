<?php

namespace Database\Factories;

use App\Models\PosSale;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PosSaleItem>
 */
class PosSaleItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quantity = fake()->numberBetween(1, 5);
        $unitPrice = fake()->randomFloat(2, 5, 200);

        return [
            'pos_sale_id' => PosSale::factory(),
            'itemable_type' => Product::class,
            'itemable_id' => Product::factory(),
            'product_variant_id' => null,
            'class_id' => null,
            'item_name' => fake()->words(3, true),
            'variant_name' => null,
            'sku' => fake()->optional()->bothify('SKU-####'),
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'total_price' => $quantity * $unitPrice,
            'metadata' => null,
        ];
    }
}
