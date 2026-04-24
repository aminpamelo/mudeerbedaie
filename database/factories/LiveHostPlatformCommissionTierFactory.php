<?php

namespace Database\Factories;

use App\Models\LiveHostPlatformCommissionTier;
use App\Models\Platform;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class LiveHostPlatformCommissionTierFactory extends Factory
{
    protected $model = LiveHostPlatformCommissionTier::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'platform_id' => Platform::factory(),
            'tier_number' => 1,
            'min_gmv_myr' => 0,
            'max_gmv_myr' => null,
            'internal_percent' => 6.00,
            'l1_percent' => 1.00,
            'l2_percent' => 2.00,
            'effective_from' => now()->toDateString(),
            'effective_to' => null,
            'is_active' => true,
        ];
    }
}
