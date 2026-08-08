<?php

namespace Database\Factories;

use App\Models\ClassStudent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class StudentMilestoneFactory extends Factory
{
    public function definition(): array
    {
        return [
            'class_student_id' => ClassStudent::factory(),
            'title' => fake()->sentence(3),
            'achieved_at' => now(),
            'awarded_by' => User::factory(),
            'type' => fake()->randomElement(['attendance', 'syllabus', 'custom']),
        ];
    }
}
