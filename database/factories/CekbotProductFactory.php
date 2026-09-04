<?php

namespace Database\Factories;

use App\Models\CekbotProduct;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CekbotProduct>
 */
class CekbotProductFactory extends Factory
{
    protected $model = CekbotProduct::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => null,
            'name' => $this->faker->words(2, true),
            'description' => $this->faker->sentence(),
            'price' => $this->faker->randomFloat(2, 10, 500),
            'currency' => 'RM',
            'url' => $this->faker->url(),
            'images' => [],
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
