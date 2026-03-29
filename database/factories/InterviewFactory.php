<?php

namespace Database\Factories;

use App\Models\Applicant;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

class InterviewFactory extends Factory
{
    public function definition(): array
    {
        return [
            'applicant_id' => Applicant::factory(),
            'interviewer_id' => Employee::factory(),
            'interview_date' => fake()->dateTimeBetween('+1 day', '+30 days'),
            'start_time' => '10:00',
            'end_time' => '11:00',
            'type' => fake()->randomElement(['phone', 'video', 'in_person']),
            'status' => 'scheduled',
        ];
    }
}
