<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\Position;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class JobPostingFactory extends Factory
{
    public function definition(): array
    {
        return [
            'title' => fake()->jobTitle(),
            'department_id' => Department::factory(),
            'position_id' => Position::factory(),
            'description' => fake()->paragraphs(3, true),
            'requirements' => fake()->paragraphs(2, true),
            'employment_type' => fake()->randomElement(['full_time', 'part_time', 'contract', 'intern']),
            'salary_range_min' => fake()->numberBetween(2000, 5000),
            'salary_range_max' => fake()->numberBetween(5000, 15000),
            'show_salary' => fake()->boolean(),
            'vacancies' => fake()->numberBetween(1, 5),
            'status' => 'draft',
            'created_by' => User::factory(),
        ];
    }

    public function open(): static
    {
        return $this->state(fn () => ['status' => 'open', 'published_at' => now()]);
    }
}
