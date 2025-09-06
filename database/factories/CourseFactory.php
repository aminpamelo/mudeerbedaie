<?php

namespace Database\Factories;

use App\Models\Teacher;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Course>
 */
class CourseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->words(3, true).' Course',
            'description' => $this->faker->paragraph(),
            'status' => 'active',
            'created_by' => User::factory(),
            'teacher_id' => null, // Optional teacher assignment
            'stripe_product_id' => null,
            'stripe_sync_status' => 'pending',
            'stripe_last_synced_at' => null,
        ];
    }

    /**
     * Course with Stripe integration.
     */
    public function withStripe(): static
    {
        return $this->state(fn (array $attributes) => [
            'stripe_product_id' => 'prod_'.$this->faker->lexify('????????????????'),
            'stripe_sync_status' => 'completed',
            'stripe_last_synced_at' => now(),
        ]);
    }

    /**
     * Inactive course.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'inactive',
        ]);
    }
}
