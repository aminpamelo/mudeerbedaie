<?php

use App\Models\LiveHostPlatformAccount;
use App\Models\LiveSession;
use App\Models\Platform;
use App\Models\PlatformAccount;
use App\Models\User;
use App\Services\LiveHost\LiveHostPayrollService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

function seedVerifiedSessionForShowProps(User $host, Platform $platform, string $dateTime, float $gmv = 1000): LiveSession
{
    $account = PlatformAccount::factory()->create([
        'platform_id' => $platform->id,
        'user_id' => $host->id,
    ]);
    $pivot = LiveHostPlatformAccount::create([
        'user_id' => $host->id,
        'platform_account_id' => $account->id,
        'is_primary' => true,
    ]);

    $start = Carbon::parse($dateTime);
    $session = LiveSession::factory()->create([
        'platform_account_id' => $account->id,
        'live_host_platform_account_id' => $pivot->id,
        'live_host_id' => $host->id,
        'status' => 'ended',
        'verification_status' => 'pending',
        'scheduled_start_at' => $start,
        'actual_start_at' => $start,
        'actual_end_at' => $start->copy()->addMinutes(50),
        'duration_minutes' => 50,
        'gmv_amount' => $gmv,
        'gmv_adjustment' => 0,
    ]);
    $session->verification_status = 'verified';
    $session->save();

    return $session->fresh();
}

beforeEach(function () {
    $this->pic = User::factory()->create(['role' => 'admin_livehost']);
});

it('show page receives run with items + breakdown', function () {
    $this->seed(\Database\Seeders\LiveHostCommissionSeeder::class);
    $ahmad = User::where('email', 'ahmad@livehost.com')->first();
    $tiktok = Platform::where('slug', 'tiktok-shop')->firstOrFail();

    seedVerifiedSessionForShowProps($ahmad, $tiktok, '2026-04-12 10:00', 1000);

    $run = app(LiveHostPayrollService::class)->generateDraft(
        Carbon::parse('2026-04-01'),
        Carbon::parse('2026-04-30')->endOfDay(),
        $this->pic,
    );

    actingAs($this->pic)
        ->get("/livehost/payroll/{$run->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('payroll/Show', false)
            ->has('run', fn (Assert $runProp) => $runProp
                ->where('id', $run->id)
                ->where('status', 'draft')
                ->has('period_start')
                ->has('period_end')
                ->has('cutoff_date')
                ->has('locked_at')
                ->has('paid_at')
                ->has('notes')
                ->has('items')
                ->has('totals', fn (Assert $totals) => $totals
                    ->has('base_salary_myr')
                    ->has('total_per_live_myr')
                    ->has('net_gmv_myr')
                    ->has('gmv_commission_myr')
                    ->has('override_l1_myr')
                    ->has('override_l2_myr')
                    ->has('gross_total_myr')
                    ->has('deductions_myr')
                    ->has('net_payout_myr')
                )
                ->etc()
            )
            ->has('run.items.0', fn (Assert $item) => $item
                ->has('id')
                ->has('user_id')
                ->has('host_name')
                ->has('host_email')
                ->has('base_salary_myr')
                ->has('sessions_count')
                ->has('total_per_live_myr')
                ->has('total_gmv_myr')
                ->has('total_gmv_adjustment_myr')
                ->has('net_gmv_myr')
                ->has('gmv_commission_myr')
                ->has('override_l1_myr')
                ->has('override_l2_myr')
                ->has('gross_total_myr')
                ->has('deductions_myr')
                ->has('net_payout_myr')
                ->has('calculation_breakdown_json', fn (Assert $breakdown) => $breakdown
                    ->has('sessions')
                    ->has('overrides_l1')
                    ->has('overrides_l2')
                )
            )
        );
});
