<?php

namespace Database\Factories;

use App\Models\LiveHostCommissionProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\LiveHostCommissionProfile>
 */
class LiveHostCommissionProfileFactory extends Factory
{
    protected $model = LiveHostCommissionProfile::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->state(['role' => 'live_host']),
            'base_salary_myr' => fake()->randomElement([0, 1500, 1800, 2000, 2500]),
            'per_live_rate_myr' => fake()->randomElement([0, 20, 25, 30, 50]),
            'upline_user_id' => null,
            'override_rate_l1_percent' => 10,
            'override_rate_l2_percent' => 5,
            'effective_from' => now(),
            'is_active' => true,
        ];
    }

    /**
     * State to set the upline user for this commission profile.
     */
    public function withUpline(User $upline): static
    {
        return $this->state(fn () => ['upline_user_id' => $upline->id]);
    }
}
