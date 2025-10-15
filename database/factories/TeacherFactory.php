<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Teacher>
 */
class TeacherFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->teacher(),
            'teacher_id' => 'T'.fake()->unique()->numerify('######'),
            'ic_number' => fake()->numerify('############'),
            'phone' => fake()->phoneNumber(),
            'status' => 'active',
            'joined_at' => now(),
            'bank_account_holder' => fake()->name(),
            'bank_account_number' => fake()->numerify('################'),
            'bank_name' => fake()->randomElement(['Maybank', 'CIMB', 'Public Bank', 'RHB', 'Hong Leong Bank']),
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'inactive',
        ]);
    }
}
