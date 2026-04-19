<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\LiveTimeSlot>
 */
class LiveTimeSlotFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'platform_account_id' => null,
            'day_of_week' => null,
            'start_time' => '09:00:00',
            'end_time' => '11:00:00',
            'duration_minutes' => 120,
            'is_active' => true,
            'sort_order' => fake()->numberBetween(0, 10),
            'status' => 'active',
            'created_by' => null,
        ];
    }
}
