<?php

namespace Database\Factories;

use App\Models\AttendanceLog;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AttendancePenalty>
 */
class AttendancePenaltyFactory extends Factory
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
            'attendance_log_id' => AttendanceLog::factory(),
            'penalty_type' => 'late_arrival',
            'penalty_minutes' => fake()->numberBetween(5, 30),
            'month' => now()->month,
            'year' => now()->year,
        ];
    }
}
