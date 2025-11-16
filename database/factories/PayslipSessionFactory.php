<?php

namespace Database\Factories;

use App\Models\ClassSession;
use App\Models\Payslip;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PayslipSession>
 */
class PayslipSessionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'payslip_id' => Payslip::factory(),
            'session_id' => ClassSession::factory()->completed(),
            'amount' => fake()->randomFloat(2, 50, 300),
            'included_at' => now(),
        ];
    }
}
