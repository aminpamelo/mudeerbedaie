<?php

namespace Database\Seeders;

use App\Models\LiveHostCommissionProfile;
use App\Models\LiveHostPlatformCommissionRate;
use App\Models\LiveHostPlatformCommissionTier;
use App\Models\Platform;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class LiveHostCommissionSeeder extends Seeder
{
    /**
     * Seed the commission worked-example fixture from design doc §5.3.
     *
     * Creates:
     *  - Ahmad Rahman (top of chain, no upline)
     *  - Sarah Chen   (upline: Ahmad)
     *  - Amin         (upline: Sarah)
     *
     * Along with their commission profiles, TikTok per-host platform
     * commission rates (legacy flat-rate rail), AND a matching single
     * open-ended Tier 1 row on the tier rail so payroll computation under
     * the new tier-based math produces non-zero commissions. Idempotent:
     * running twice does not duplicate users, profiles, rates, or tiers.
     */
    public function run(): void
    {
        // Use the canonical slug from PlatformSeeder (`tiktok-shop`) so
        // we reconcile with the production platform record. If it's missing
        // (fresh DB where PlatformSeeder hasn't run), call that seeder
        // first rather than creating a divergent row with a different slug.
        $tiktok = Platform::where('slug', 'tiktok-shop')->first();

        if (! $tiktok) {
            $this->call(PlatformSeeder::class);
            $tiktok = Platform::where('slug', 'tiktok-shop')->firstOrFail();
        }

        $ahmad = User::firstOrCreate(
            ['email' => 'ahmad@livehost.com'],
            [
                'name' => 'Ahmad Rahman',
                'role' => 'live_host',
                'status' => 'active',
                'password' => Hash::make('password'),
            ]
        );

        $sarah = User::firstOrCreate(
            ['email' => 'sarah@livehost.com'],
            [
                'name' => 'Sarah Chen',
                'role' => 'live_host',
                'status' => 'active',
                'password' => Hash::make('password'),
            ]
        );

        $amin = User::firstOrCreate(
            ['email' => 'amin@livehost.com'],
            [
                'name' => 'Amin',
                'role' => 'live_host',
                'status' => 'active',
                'password' => Hash::make('password'),
            ]
        );

        // Ahmad: top of chain
        $ahmadProfile = LiveHostCommissionProfile::updateOrCreate(
            ['user_id' => $ahmad->id, 'is_active' => true],
            [
                'base_salary_myr' => 2000.00,
                'per_live_rate_myr' => 30.00,
                'upline_user_id' => null,
                'override_rate_l1_percent' => 10.00,
                'override_rate_l2_percent' => 5.00,
                'effective_from' => now()->subMonths(3),
            ]
        );

        // Sarah: under Ahmad
        $sarahProfile = LiveHostCommissionProfile::updateOrCreate(
            ['user_id' => $sarah->id, 'is_active' => true],
            [
                'base_salary_myr' => 1800.00,
                'per_live_rate_myr' => 25.00,
                'upline_user_id' => $ahmad->id,
                'override_rate_l1_percent' => 10.00,
                'override_rate_l2_percent' => 5.00,
                'effective_from' => now()->subMonths(3),
            ]
        );

        // Amin: under Sarah
        $aminProfile = LiveHostCommissionProfile::updateOrCreate(
            ['user_id' => $amin->id, 'is_active' => true],
            [
                'base_salary_myr' => 0.00,
                'per_live_rate_myr' => 50.00,
                'upline_user_id' => $sarah->id,
                'override_rate_l1_percent' => 0.00,
                'override_rate_l2_percent' => 0.00,
                'effective_from' => now()->subMonths(3),
            ]
        );

        // TikTok per-host commission rates (legacy flat-rate rail + matching
        // single-tier schedule on the new tier rail). The tier row mirrors
        // Task 10's production "zero-override backfill" strategy:
        // `internal_percent` preserves the flat commission rate so each host's
        // own commission is unchanged, but `l1_percent` and `l2_percent` are
        // ZERO — uplines receive no overrides until admin explicitly configures
        // real tier schedules through the UI. This is the safest transition:
        // the override system becomes opt-in per-host rather than silently
        // paying the old flat overrides against the new rail.
        $effectiveFrom = now()->subMonths(3)->toDateString();
        foreach ([
            [$ahmad, 4.00],
            [$sarah, 5.00],
            [$amin, 6.00],
        ] as [$user, $ratePercent]) {
            LiveHostPlatformCommissionRate::updateOrCreate(
                ['user_id' => $user->id, 'platform_id' => $tiktok->id, 'is_active' => true],
                [
                    'commission_rate_percent' => $ratePercent,
                    'effective_from' => now()->subMonths(3),
                ]
            );

            LiveHostPlatformCommissionTier::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'platform_id' => $tiktok->id,
                    'tier_number' => 1,
                ],
                [
                    'min_gmv_myr' => 0,
                    'max_gmv_myr' => null,
                    'internal_percent' => $ratePercent,
                    'l1_percent' => 0.00,
                    'l2_percent' => 0.00,
                    'effective_from' => $effectiveFrom,
                    'effective_to' => null,
                    'is_active' => true,
                ]
            );
        }
    }
}
