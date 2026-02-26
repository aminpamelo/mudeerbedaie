<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\FunnelOrder>
 */
class FunnelOrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'funnel_id' => \App\Models\Funnel::factory(),
            'session_id' => \App\Models\FunnelSession::factory(),
            'product_order_id' => \App\Models\ProductOrder::factory(),
            'step_id' => 1,
            'order_type' => 'main',
            'funnel_revenue' => fake()->randomFloat(2, 10, 500),
        ];
    }
}
