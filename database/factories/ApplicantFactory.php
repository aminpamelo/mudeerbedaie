<?php

namespace Database\Factories;

use App\Models\Applicant;
use App\Models\JobPosting;
use Illuminate\Database\Eloquent\Factories\Factory;

class ApplicantFactory extends Factory
{
    public function definition(): array
    {
        return [
            'job_posting_id' => JobPosting::factory(),
            'applicant_number' => Applicant::generateApplicantNumber(),
            'full_name' => fake()->name(),
            'email' => fake()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'ic_number' => fake()->numerify('######-##-####'),
            'resume_path' => 'resumes/'.fake()->uuid().'.pdf',
            'source' => fake()->randomElement(['website', 'referral', 'jobstreet', 'linkedin', 'walk_in']),
            'current_stage' => 'applied',
            'applied_at' => now(),
        ];
    }
}
