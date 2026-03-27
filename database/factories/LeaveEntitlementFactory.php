<?php

namespace Database\Factories;

use App\Models\LeaveType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\LeaveEntitlement>
 */
class LeaveEntitlementFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'leave_type_id' => LeaveType::factory(),
            'employment_type' => 'full_time',
            'min_service_months' => 0,
            'max_service_months' => null,
            'days_per_year' => 8.0,
            'is_prorated' => false,
            'carry_forward_max' => 0,
        ];
    }
}
