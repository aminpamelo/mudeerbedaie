<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\LeaveType>
 */
class LeaveTypeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $leaveTypes = [
            'Annual Leave', 'Medical Leave', 'Emergency Leave',
            'Compassionate Leave', 'Maternity Leave', 'Paternity Leave',
            'Unpaid Leave', 'Study Leave', 'Replacement Leave',
        ];

        return [
            'name' => fake()->randomElement($leaveTypes),
            'code' => fake()->unique()->regexify('[A-Z]{2,3}'),
            'is_paid' => true,
            'is_attachment_required' => false,
            'is_system' => false,
            'is_active' => true,
            'color' => fake()->hexColor(),
            'sort_order' => 0,
        ];
    }

    /**
     * Set leave type as system type
     */
    public function system(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_system' => true,
        ]);
    }

    /**
     * Set leave type as medical leave
     */
    public function medical(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Medical Leave',
            'code' => 'MC',
            'is_attachment_required' => true,
        ]);
    }

    /**
     * Set leave type as annual leave
     */
    public function annual(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Annual Leave',
            'code' => 'AL',
        ]);
    }
}
