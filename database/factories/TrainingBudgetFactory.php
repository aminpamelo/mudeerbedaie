<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\TrainingBudget;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TrainingBudget> */
class TrainingBudgetFactory extends Factory
{
    protected $model = TrainingBudget::class;

    public function definition(): array
    {
        return [
            'department_id' => Department::factory(),
            'year' => now()->year,
            'allocated_amount' => $this->faker->randomFloat(2, 5000, 50000),
            'spent_amount' => $this->faker->randomFloat(2, 0, 20000),
        ];
    }
}
