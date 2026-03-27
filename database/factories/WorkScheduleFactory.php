<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\WorkSchedule>
 */
class WorkScheduleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Office Hours',
            'type' => 'fixed',
            'start_time' => '09:00',
            'end_time' => '18:00',
            'break_duration_minutes' => 60,
            'min_hours_per_day' => 8.0,
            'grace_period_minutes' => 10,
            'working_days' => [1, 2, 3, 4, 5],
            'is_default' => false,
        ];
    }

    /**
     * Set schedule type as flexible
     */
    public function flexible(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Flexible Hours',
            'type' => 'flexible',
            'start_time' => '07:00',
            'end_time' => '19:00',
            'grace_period_minutes' => 30,
        ]);
    }

    /**
     * Set schedule type as shift
     */
    public function shift(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Shift Work',
            'type' => 'shift',
            'start_time' => '06:00',
            'end_time' => '14:00',
            'break_duration_minutes' => 30,
            'min_hours_per_day' => 7.5,
            'grace_period_minutes' => 5,
        ]);
    }

    /**
     * Set as default schedule
     */
    public function default(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_default' => true,
        ]);
    }
}
