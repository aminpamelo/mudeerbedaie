<?php

namespace Database\Factories;

use App\Models\ExternalSystem;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ExternalSystem>
 */
class ExternalSystemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = $this->faker->unique()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.$this->faker->unique()->numberBetween(1, 99999),
            'base_url' => 'https://'.$this->faker->domainName(),
            'provision_path' => '/api/v1/provision',
            'auth_type' => 'both',
            'api_key' => 'sk_'.Str::random(32),
            'signing_secret' => Str::random(40),
            'timeout' => 30,
            'is_active' => true,
            'description' => $this->faker->sentence(),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => ['is_active' => false]);
    }
}
