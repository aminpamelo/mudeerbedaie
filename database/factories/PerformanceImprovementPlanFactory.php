<?php

namespace Database\Factories;

use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PerformanceImprovementPlan>
 */
class PerformanceImprovementPlanFactory extends Factory
{
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'initiated_by' => Employee::factory(),
            'reason' => fake()->paragraph(),
            'start_date' => now(),
            'end_date' => now()->addMonths(3),
            'status' => 'active',
        ];
    }
}
