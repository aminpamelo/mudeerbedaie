<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\ExitInterview;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ExitInterview> */
class ExitInterviewFactory extends Factory
{
    protected $model = ExitInterview::class;

    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'conducted_by' => Employee::factory(),
            'interview_date' => $this->faker->date(),
            'reason_for_leaving' => $this->faker->randomElement(['better_opportunity', 'salary', 'work_environment', 'personal', 'relocation', 'career_change', 'management', 'other']),
            'overall_satisfaction' => $this->faker->numberBetween(1, 5),
            'would_recommend' => $this->faker->boolean(),
            'feedback' => $this->faker->paragraph(),
            'improvements' => $this->faker->paragraph(),
        ];
    }
}
