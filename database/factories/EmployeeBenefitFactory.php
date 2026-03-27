<?php

namespace Database\Factories;

use App\Models\BenefitType;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\EmployeeBenefit>
 */
class EmployeeBenefitFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startDate = fake()->dateTimeBetween('-2 years', '-1 month');

        return [
            'employee_id' => Employee::factory(),
            'benefit_type_id' => BenefitType::factory(),
            'provider' => fake()->company(),
            'policy_number' => fake()->numerify('POL-######'),
            'coverage_amount' => fake()->randomFloat(2, 5000, 100000),
            'employer_contribution' => fake()->randomFloat(2, 50, 500),
            'employee_contribution' => fake()->randomFloat(2, 10, 200),
            'start_date' => $startDate,
            'end_date' => null,
            'notes' => fake()->optional()->sentence(),
            'is_active' => true,
        ];
    }
}
