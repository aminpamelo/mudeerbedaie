<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SalaryComponent>
 */
class SalaryComponentFactory extends Factory
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

        $type = fake()->randomElement(['earning', 'deduction']);
        $earningCategories = ['basic', 'fixed_allowance', 'variable_allowance'];
        $deductionCategories = ['fixed_deduction', 'variable_deduction'];

        $category = $type === 'earning'
            ? fake()->randomElement($earningCategories)
            : fake()->randomElement($deductionCategories);

        return [
            'name' => fake()->randomElement(['Housing Allowance', 'Transport Allowance', 'Meal Allowance', 'Performance Bonus', 'Overtime Pay']).' '.self::$counter,
            'code' => 'COMP_'.str_pad(self::$counter, 4, '0', STR_PAD_LEFT),
            'type' => $type,
            'category' => $category,
            'is_taxable' => fake()->boolean(70),
            'is_epf_applicable' => fake()->boolean(60),
            'is_socso_applicable' => fake()->boolean(60),
            'is_eis_applicable' => fake()->boolean(60),
            'is_system' => false,
            'is_active' => true,
            'sort_order' => self::$counter * 10,
        ];
    }

    /**
     * Set as an earning component.
     */
    public function earning(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'earning',
            'category' => 'fixed_allowance',
        ]);
    }

    /**
     * Set as a deduction component.
     */
    public function deduction(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'deduction',
            'category' => 'fixed_deduction',
        ]);
    }

    /**
     * Set as a system component (cannot be deleted).
     */
    public function system(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_system' => true,
        ]);
    }

    /**
     * Set as inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
