<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\KpiTemplate>
 */
class KpiTemplateFactory extends Factory
{
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(4),
            'target' => fake()->randomElement(['90% completion', 'RM500k revenue', '95% accuracy', '4.0 rating']),
            'weight' => fake()->randomElement([10, 15, 20, 25, 30]),
            'category' => fake()->randomElement(['quantitative', 'qualitative', 'behavioral']),
            'is_active' => true,
        ];
    }
}
