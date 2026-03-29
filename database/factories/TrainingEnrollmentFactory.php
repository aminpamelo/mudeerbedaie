<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\TrainingEnrollment;
use App\Models\TrainingProgram;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TrainingEnrollment> */
class TrainingEnrollmentFactory extends Factory
{
    protected $model = TrainingEnrollment::class;

    public function definition(): array
    {
        return [
            'training_program_id' => TrainingProgram::factory(),
            'employee_id' => Employee::factory(),
            'enrolled_by' => User::factory(),
            'status' => 'enrolled',
        ];
    }
}
