<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\EmployeeHistory>
 */
class EmployeeHistoryFactory extends Factory
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
            'change_type' => fake()->randomElement(['position_change', 'department_transfer', 'status_change', 'salary_revision', 'promotion', 'general_update']),
            'field_name' => fake()->randomElement(['position_id', 'department_id', 'status', 'employment_type']),
            'old_value' => fake()->optional()->word(),
            'new_value' => fake()->word(),
            'effective_date' => fake()->dateTimeBetween('-2 years', 'now'),
            'remarks' => fake()->optional()->sentence(),
            'changed_by' => User::factory(),
        ];
    }
}
