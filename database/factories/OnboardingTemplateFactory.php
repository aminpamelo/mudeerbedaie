<?php

namespace Database\Factories;

use App\Models\Department;
use Illuminate\Database\Eloquent\Factories\Factory;

class OnboardingTemplateFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true).' Onboarding',
            'department_id' => Department::factory(),
            'is_active' => true,
        ];
    }
}
