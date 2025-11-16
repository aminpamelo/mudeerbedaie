<?php

namespace Database\Factories;

use App\Models\ClassSession;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ClassAttendance>
 */
class ClassAttendanceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'session_id' => ClassSession::factory(),
            'student_id' => Student::factory(),
            'status' => 'present',
            'checked_in_at' => now(),
            'checked_out_at' => null,
            'notes' => fake()->optional()->sentence(),
        ];
    }

    public function present(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'present',
            'checked_in_at' => now(),
        ]);
    }

    public function absent(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'absent',
            'checked_in_at' => null,
        ]);
    }

    public function late(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'late',
            'checked_in_at' => now()->addMinutes(15),
        ]);
    }

    public function excused(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'excused',
            'checked_in_at' => null,
            'notes' => 'Medical excuse',
        ]);
    }
}
