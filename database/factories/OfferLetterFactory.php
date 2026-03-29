<?php

namespace Database\Factories;

use App\Models\Applicant;
use App\Models\Position;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class OfferLetterFactory extends Factory
{
    public function definition(): array
    {
        return [
            'applicant_id' => Applicant::factory(),
            'position_id' => Position::factory(),
            'offered_salary' => fake()->numberBetween(3000, 12000),
            'start_date' => fake()->dateTimeBetween('+14 days', '+60 days'),
            'employment_type' => 'full_time',
            'status' => 'draft',
            'created_by' => User::factory(),
        ];
    }
}
