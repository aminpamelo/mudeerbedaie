<?php

namespace Database\Factories;

use App\AcademicStatus;
use App\Models\Course;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Enrollment>
 */
class EnrollmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'course_id' => Course::factory(),
            'enrolled_by' => User::factory(),
            'academic_status' => AcademicStatus::ACTIVE,
            'enrollment_date' => $this->faker->date(),
            'start_date' => $this->faker->dateTimeBetween('now', '+1 month'),
            'end_date' => $this->faker->dateTimeBetween('+1 month', '+6 months'),
            'completion_date' => null,
            'enrollment_fee' => $this->faker->randomFloat(2, 50, 1000),
            'notes' => $this->faker->optional()->sentence(),
            'progress_data' => null,
            'stripe_subscription_id' => null,
            'subscription_status' => null,
            'subscription_cancel_at' => null,
        ];
    }

    /**
     * Active enrollment.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'academic_status' => AcademicStatus::ACTIVE,
        ]);
    }

    /**
     * Completed enrollment.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'academic_status' => AcademicStatus::COMPLETED,
            'completion_date' => $this->faker->dateTimeBetween('-1 month', 'now'),
        ]);
    }

    /**
     * With active subscription.
     */
    public function withActiveSubscription(): static
    {
        return $this->state(fn (array $attributes) => [
            'stripe_subscription_id' => 'sub_'.$this->faker->lexify('????????????????'),
            'subscription_status' => 'active',
        ]);
    }

    /**
     * With trialing subscription.
     */
    public function withTrialSubscription(): static
    {
        return $this->state(fn (array $attributes) => [
            'stripe_subscription_id' => 'sub_'.$this->faker->lexify('????????????????'),
            'subscription_status' => 'trialing',
        ]);
    }

    /**
     * With past due subscription.
     */
    public function withPastDueSubscription(): static
    {
        return $this->state(fn (array $attributes) => [
            'stripe_subscription_id' => 'sub_'.$this->faker->lexify('????????????????'),
            'subscription_status' => 'past_due',
        ]);
    }

    /**
     * With pending cancellation.
     */
    public function withPendingCancellation(): static
    {
        return $this->state(fn (array $attributes) => [
            'stripe_subscription_id' => 'sub_'.$this->faker->lexify('????????????????'),
            'subscription_status' => 'active',
            'subscription_cancel_at' => $this->faker->dateTimeBetween('now', '+1 month'),
        ]);
    }

    /**
     * Withdrawn enrollment.
     */
    public function withdrawn(): static
    {
        return $this->state(fn (array $attributes) => [
            'academic_status' => AcademicStatus::WITHDRAWN,
        ]);
    }

    /**
     * Suspended enrollment.
     */
    public function suspended(): static
    {
        return $this->state(fn (array $attributes) => [
            'academic_status' => AcademicStatus::SUSPENDED,
        ]);
    }

    /**
     * Legacy method for backward compatibility.
     */
    public function dropped(): static
    {
        return $this->withdrawn();
    }
}
