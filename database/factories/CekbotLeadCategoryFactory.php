<?php

namespace Database\Factories;

use App\Models\CekbotLeadCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CekbotLeadCategory>
 */
class CekbotLeadCategoryFactory extends Factory
{
    protected $model = CekbotLeadCategory::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->word(),
            'color' => $this->faker->randomElement(['blue', 'amber', 'violet', 'emerald', 'rose', 'sky']),
            'sort_order' => 0,
        ];
    }
}
