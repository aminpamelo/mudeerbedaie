<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ReviewCycle>
 */
class ReviewCycleFactory extends Factory
{
    public function definition(): array
    {
        $startDate = fake()->dateTimeBetween('-3 months', 'now');
        $endDate = (clone $startDate)->modify('+3 months');

        return [
            'name' => 'Q'.fake()->numberBetween(1, 4).' '.date('Y').' Review',
            'type' => fake()->randomElement(['monthly', 'quarterly', 'semi_annual', 'annual']),
            'start_date' => $startDate,
            'end_date' => $endDate,
            'submission_deadline' => (clone $endDate)->modify('+14 days'),
            'status' => 'draft',
            'created_by' => User::factory(),
        ];
    }
}
