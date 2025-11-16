<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Certificate>
 */
class CertificateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true).' Certificate',
            'description' => fake()->sentence(),
            'size' => fake()->randomElement(['letter', 'a4']),
            'orientation' => fake()->randomElement(['portrait', 'landscape']),
            'width' => 800,
            'height' => 600,
            'background_image' => null,
            'background_color' => '#ffffff',
            'elements' => [],
            'status' => 'active',
            'created_by' => \App\Models\User::factory(),
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => ['status' => 'active']);
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes) => ['status' => 'draft']);
    }

    public function archived(): static
    {
        return $this->state(fn (array $attributes) => ['status' => 'archived']);
    }
}
