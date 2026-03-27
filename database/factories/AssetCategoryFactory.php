<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AssetCategory>
 */
class AssetCategoryFactory extends Factory
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

        $categories = ['Laptop', 'Mobile Phone', 'Monitor', 'Printer', 'Vehicle', 'Furniture'];
        $name = fake()->randomElement($categories);
        $code = strtoupper(preg_replace('/[^A-Z]/', '', $name)).self::$counter;

        return [
            'name' => $name,
            'code' => $code,
            'description' => fake()->sentence(),
            'requires_serial_number' => fake()->boolean(70),
            'is_active' => true,
            'sort_order' => self::$counter,
        ];
    }
}
