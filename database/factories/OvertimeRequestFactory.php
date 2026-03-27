<?php

namespace Database\Factories;

use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\OvertimeRequest>
 */
class OvertimeRequestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'requested_date' => fake()->dateTimeBetween('now', '+30 days'),
            'start_time' => '18:00',
            'end_time' => '20:00',
            'estimated_hours' => 2.0,
            'reason' => fake()->sentence(),
            'status' => 'pending',
        ];
    }

    /**
     * Set request as approved
     */
    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'approved',
        ]);
    }

    /**
     * Set request as rejected
     */
    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'rejected',
        ]);
    }

    /**
     * Set request as completed with actual hours
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
            'actual_hours' => 2.0,
            'replacement_hours_earned' => 2.0,
        ]);
    }
}
