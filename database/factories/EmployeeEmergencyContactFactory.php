<?php

namespace Database\Factories;

use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\EmployeeEmergencyContact>
 */
class EmployeeEmergencyContactFactory extends Factory
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
            'name' => fake()->name(),
            'relationship' => fake()->randomElement(['spouse', 'parent', 'sibling', 'child', 'friend']),
            'phone' => fake()->phoneNumber(),
            'address' => fake()->optional()->address(),
        ];
    }
}
