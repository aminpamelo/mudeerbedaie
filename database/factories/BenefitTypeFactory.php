<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\BenefitType>
 */
class BenefitTypeFactory extends Factory
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

        $categories = ['insurance', 'allowance', 'subsidy', 'other'];
        $names = ['Medical Insurance', 'Dental Coverage', 'Vision Allowance', 'Life Insurance', 'Housing Loan'];
        $name = fake()->randomElement($names);
        $code = strtoupper(preg_replace('/[^A-Z]/', '', $name)).self::$counter;

        return [
            'name' => $name,
            'code' => $code,
            'description' => fake()->sentence(),
            'category' => fake()->randomElement($categories),
            'is_active' => true,
            'sort_order' => self::$counter,
        ];
    }
}
