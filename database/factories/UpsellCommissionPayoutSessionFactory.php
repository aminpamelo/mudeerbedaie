<?php

namespace Database\Factories;

use App\Models\ClassSession;
use App\Models\UpsellCommissionPayout;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\UpsellCommissionPayoutSession>
 */
class UpsellCommissionPayoutSessionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'upsell_commission_payout_id' => UpsellCommissionPayout::factory(),
            'class_session_id' => ClassSession::factory(),
            'paid_revenue' => 1000.00,
            'commission_rate' => 10.00,
            'commission_amount' => 100.00,
        ];
    }
}
