<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\LiveSlotTemplate>
 */
class LiveSlotTemplateFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'description' => fake()->optional()->sentence(4),
            'slots' => [
                ['day_of_week' => 1, 'start_time' => '09:00', 'end_time' => '11:00'],
                ['day_of_week' => 1, 'start_time' => '14:00', 'end_time' => '16:00'],
            ],
            'is_active' => true,
            'created_by' => null,
        ];
    }
}
