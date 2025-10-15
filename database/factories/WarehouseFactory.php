<?php

namespace Database\Factories;

use App\Models\Agent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Warehouse>
 */
class WarehouseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company().' Warehouse',
            'code' => 'WH-'.fake()->unique()->numerify('####'),
            'warehouse_type' => fake()->randomElement(['main', 'agent', 'temporary']),
            'agent_id' => null,
            'description' => fake()->optional()->sentence(),
            'address' => [
                'street' => fake()->streetAddress(),
                'city' => fake()->city(),
                'state' => fake()->state(),
                'postcode' => fake()->postcode(),
                'country' => 'Malaysia',
            ],
            'manager_name' => fake()->name(),
            'manager_email' => fake()->safeEmail(),
            'manager_phone' => fake()->phoneNumber(),
            'status' => 'active',
            'is_active' => true,
            'is_default' => false,
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
            'is_active' => true,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'inactive',
            'is_active' => false,
        ]);
    }

    public function default(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_default' => true,
        ]);
    }

    public function withAgent(): static
    {
        return $this->state(fn (array $attributes) => [
            'warehouse_type' => 'agent',
            'agent_id' => Agent::factory(),
        ]);
    }
}
