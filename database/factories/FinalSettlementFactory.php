<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\FinalSettlement;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<FinalSettlement> */
class FinalSettlementFactory extends Factory
{
    protected $model = FinalSettlement::class;

    public function definition(): array
    {
        $prorated = $this->faker->randomFloat(2, 1000, 5000);
        $encashment = $this->faker->randomFloat(2, 0, 2000);
        $gross = $prorated + $encashment;
        $deductions = $gross * 0.15;

        return [
            'employee_id' => Employee::factory(),
            'prorated_salary' => $prorated,
            'leave_encashment' => $encashment,
            'leave_encashment_days' => $this->faker->randomFloat(1, 0, 15),
            'total_gross' => $gross,
            'total_deductions' => round($deductions, 2),
            'net_amount' => round($gross - $deductions, 2),
            'status' => 'draft',
        ];
    }

    public function calculated(): static
    {
        return $this->state(fn () => ['status' => 'calculated']);
    }
}
