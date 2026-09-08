<?php

namespace Database\Factories;

use App\Models\CekbotProduct;
use App\Models\CekbotProductTestimonial;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CekbotProductTestimonial>
 */
class CekbotProductTestimonialFactory extends Factory
{
    protected $model = CekbotProductTestimonial::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'cekbot_product_id' => CekbotProduct::factory(),
            'author' => $this->faker->name(),
            'text' => $this->faker->sentence(),
            'image' => null,
            'sort_order' => 0,
        ];
    }
}
