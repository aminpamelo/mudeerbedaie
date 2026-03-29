<?php

namespace Database\Factories;

use App\Models\TrainingCost;
use App\Models\TrainingProgram;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TrainingCost> */
class TrainingCostFactory extends Factory
{
    protected $model = TrainingCost::class;

    public function definition(): array
    {
        return [
            'training_program_id' => TrainingProgram::factory(),
            'description' => $this->faker->sentence(3),
            'amount' => $this->faker->randomFloat(2, 100, 5000),
        ];
    }
}
