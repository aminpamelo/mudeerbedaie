<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CourseClassSettings>
 */
class CourseClassSettingsFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'course_id' => \App\Models\Course::factory(),
            'billing_type' => fake()->randomElement(['per_session', 'per_month', 'per_minute']),
            'price_per_session' => fake()->randomFloat(2, 30, 100),
            'price_per_month' => fake()->randomFloat(2, 200, 600),
            'price_per_minute' => fake()->randomFloat(2, 1, 5),
            'sessions_per_month' => fake()->numberBetween(8, 20),
        ];
    }
}
