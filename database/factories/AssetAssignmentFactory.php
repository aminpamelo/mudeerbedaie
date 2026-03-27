<?php

namespace Database\Factories;

use App\Models\Asset;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AssetAssignment>
 */
class AssetAssignmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'asset_id' => Asset::factory()->assigned(),
            'employee_id' => Employee::factory(),
            'assigned_by' => Employee::factory(),
            'assigned_date' => fake()->dateTimeBetween('-6 months', '-1 month'),
            'expected_return_date' => fake()->optional()->dateTimeBetween('now', '+1 year'),
            'returned_date' => null,
            'returned_condition' => null,
            'return_notes' => null,
            'status' => 'active',
            'notes' => fake()->optional()->sentence(),
        ];
    }

    /**
     * Set status as returned.
     */
    public function returned(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'returned',
            'returned_date' => fake()->dateTimeBetween('-1 month', 'now'),
            'returned_condition' => fake()->randomElement(['good', 'fair', 'damaged']),
            'return_notes' => fake()->optional()->sentence(),
        ]);
    }
}
