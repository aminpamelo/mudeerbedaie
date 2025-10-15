<?php

namespace Database\Factories;

use App\Models\ProductOrder;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ProductOrder>
 */
class ProductOrderFactory extends Factory
{
    protected $model = ProductOrder::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_number' => 'ORD-'.fake()->unique()->numerify('######'),
            'student_id' => Student::factory(),
            'order_date' => fake()->dateTimeBetween('-1 year', 'now'),
            'status' => fake()->randomElement(['pending', 'processing', 'shipped', 'delivered']),
            'order_type' => 'product',
            'source' => 'website',
            'currency' => 'MYR',
            'subtotal' => $subtotal = fake()->randomFloat(2, 50, 500),
            'shipping_cost' => $shipping = fake()->randomFloat(2, 5, 20),
            'tax_amount' => 0,
            'total_amount' => $subtotal + $shipping,
            'discount_amount' => 0,
        ];
    }
}
