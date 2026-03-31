<?php

namespace Database\Factories;

use App\Models\ClaimType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ClaimTypeVehicleRate>
 */
class ClaimTypeVehicleRateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $vehicles = [
            ['name' => 'Car', 'rate' => 0.60],
            ['name' => 'Motorcycle', 'rate' => 0.30],
            ['name' => 'Van', 'rate' => 0.80],
            ['name' => '4WD', 'rate' => 0.75],
        ];

        $vehicle = $this->faker->randomElement($vehicles);

        return [
            'claim_type_id' => ClaimType::factory(),
            'name' => $vehicle['name'],
            'rate_per_km' => $vehicle['rate'],
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
