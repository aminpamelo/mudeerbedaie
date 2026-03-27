<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ClaimType>
 */
class ClaimTypeFactory extends Factory
{
    private static int $counter = 0;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        self::$counter++;

        $types = ['Medical', 'Travel', 'Meal', 'Entertainment', 'Training', 'Equipment'];
        $name = fake()->randomElement($types).' Claim';
        $code = strtoupper(substr(preg_replace('/[^A-Z]/', '', $name), 0, 8)).self::$counter;

        return [
            'name' => $name,
            'code' => $code,
            'description' => fake()->sentence(),
            'monthly_limit' => fake()->randomElement([300, 500, 1000, null]),
            'yearly_limit' => fake()->randomElement([3000, 6000, 12000, null]),
            'requires_receipt' => fake()->boolean(80),
            'is_active' => true,
            'sort_order' => self::$counter,
        ];
    }
}
