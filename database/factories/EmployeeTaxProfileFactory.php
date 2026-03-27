<?php

namespace Database\Factories;

use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\EmployeeTaxProfile>
 */
class EmployeeTaxProfileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $numChildren = fake()->numberBetween(0, 4);

        return [
            'employee_id' => Employee::factory(),
            'tax_number' => fake()->optional(0.8)->regexify('SG\d{10}'),
            'marital_status' => fake()->randomElement(['single', 'married_spouse_not_working', 'married_spouse_working']),
            'num_children' => $numChildren,
            'num_children_studying' => fake()->numberBetween(0, $numChildren),
            'disabled_individual' => false,
            'disabled_spouse' => false,
            'is_pcb_manual' => false,
            'manual_pcb_amount' => null,
        ];
    }

    /**
     * Set as single employee (no children).
     */
    public function single(): static
    {
        return $this->state(fn (array $attributes) => [
            'marital_status' => 'single',
            'num_children' => 0,
            'num_children_studying' => 0,
        ]);
    }

    /**
     * Set as married with children.
     */
    public function marriedWithChildren(int $children = 2): static
    {
        return $this->state(fn (array $attributes) => [
            'marital_status' => 'married_spouse_not_working',
            'num_children' => $children,
            'num_children_studying' => $children,
        ]);
    }

    /**
     * Set with manual PCB override.
     */
    public function manualPcb(float $amount = 200): static
    {
        return $this->state(fn (array $attributes) => [
            'is_pcb_manual' => true,
            'manual_pcb_amount' => $amount,
        ]);
    }
}
