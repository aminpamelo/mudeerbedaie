<?php

namespace Database\Factories;

use App\Models\ClaimType;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ClaimRequest>
 */
class ClaimRequestFactory extends Factory
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

        $yearMonth = now()->format('Ym');

        return [
            'claim_number' => 'CLM-'.$yearMonth.'-'.str_pad(self::$counter, 4, '0', STR_PAD_LEFT),
            'employee_id' => Employee::factory(),
            'claim_type_id' => ClaimType::factory(),
            'amount' => fake()->randomFloat(2, 20, 500),
            'approved_amount' => null,
            'claim_date' => fake()->dateTimeBetween('-30 days', 'now'),
            'description' => fake()->sentence(),
            'receipt_path' => null,
            'status' => 'draft',
            'submitted_at' => null,
            'approved_by' => null,
            'approved_at' => null,
            'rejected_reason' => null,
            'paid_at' => null,
            'paid_reference' => null,
        ];
    }

    /**
     * Set status as pending (submitted).
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
            'submitted_at' => now()->subDays(fake()->numberBetween(1, 7)),
        ]);
    }

    /**
     * Set status as approved.
     */
    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'approved',
            'submitted_at' => now()->subDays(fake()->numberBetween(3, 10)),
            'approved_amount' => $attributes['amount'],
            'approved_by' => Employee::factory(),
            'approved_at' => now()->subDays(fake()->numberBetween(1, 2)),
        ]);
    }

    /**
     * Set status as paid.
     */
    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'paid',
            'submitted_at' => now()->subDays(fake()->numberBetween(5, 14)),
            'approved_amount' => $attributes['amount'],
            'approved_by' => Employee::factory(),
            'approved_at' => now()->subDays(fake()->numberBetween(3, 5)),
            'paid_at' => now()->subDay(),
            'paid_reference' => 'PAY-'.fake()->numerify('######'),
        ]);
    }
}
