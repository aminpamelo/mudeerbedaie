<?php

namespace Database\Factories;

use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AttendanceLog>
 */
class AttendanceLogFactory extends Factory
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
            'date' => today(),
            'clock_in' => today()->setTime(9, 0),
            'clock_out' => null,
            'status' => 'present',
            'late_minutes' => 0,
            'early_leave_minutes' => 0,
            'total_work_minutes' => 0,
            'is_overtime' => false,
        ];
    }

    /**
     * Set attendance as late
     */
    public function late(): static
    {
        return $this->state(fn (array $attributes) => [
            'clock_in' => today()->setTime(9, 30),
            'late_minutes' => 20,
            'status' => 'late',
        ]);
    }

    /**
     * Set attendance as absent
     */
    public function absent(): static
    {
        return $this->state(fn (array $attributes) => [
            'clock_in' => null,
            'status' => 'absent',
        ]);
    }

    /**
     * Set attendance as work from home
     */
    public function wfh(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'wfh',
        ]);
    }

    /**
     * Set attendance as on leave
     */
    public function onLeave(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'on_leave',
        ]);
    }

    /**
     * Set attendance with clock out completed
     */
    public function clockedOut(): static
    {
        return $this->state(fn (array $attributes) => [
            'clock_out' => today()->setTime(18, 0),
            'total_work_minutes' => 480,
        ]);
    }
}
