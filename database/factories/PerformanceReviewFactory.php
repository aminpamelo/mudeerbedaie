<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\ReviewCycle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PerformanceReview>
 */
class PerformanceReviewFactory extends Factory
{
    public function definition(): array
    {
        return [
            'review_cycle_id' => ReviewCycle::factory(),
            'employee_id' => Employee::factory(),
            'reviewer_id' => Employee::factory(),
            'status' => 'draft',
        ];
    }
}
