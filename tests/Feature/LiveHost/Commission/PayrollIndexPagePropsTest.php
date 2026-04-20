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

/**
 * Minimal local helper — seeds one verified session so the payroll run has a
 * real aggregate to compute against. Kept small on purpose; the canonical
 * version lives in PayrollRunControllerTest.
 */
function seedVerifiedSessionForIndexProps(User $host, Platform $platform, string $dateTime, float $gmv = 1000): LiveSession
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

it('index page receives runs with counts and totals', function () {
    $this->seed(\Database\Seeders\LiveHostCommissionSeeder::class);
    $ahmad = User::where('email', 'ahmad@livehost.com')->first();
    $tiktok = Platform::where('slug', 'tiktok-shop')->firstOrFail();

    seedVerifiedSessionForIndexProps($ahmad, $tiktok, '2026-03-10 10:00', 1000);
    seedVerifiedSessionForIndexProps($ahmad, $tiktok, '2026-04-10 10:00', 1500);

    $service = app(LiveHostPayrollService::class);

    $marchRun = $service->generateDraft(
        Carbon::parse('2026-03-01'),
        Carbon::parse('2026-03-31')->endOfDay(),
        $this->pic,
    );

    $aprilRun = $service->generateDraft(
        Carbon::parse('2026-04-01'),
        Carbon::parse('2026-04-30')->endOfDay(),
        $this->pic,
    );

    actingAs($this->pic)
        ->get('/livehost/payroll')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('payroll/Index', false)
            ->has('runs.data', 2)
            ->has('runs.data.0', fn (Assert $row) => $row
                ->has('id')
                ->has('period_start')
                ->has('period_end')
                ->has('cutoff_date')
                ->has('status')
                ->has('items_count')
                ->has('net_payout_total_myr')
                ->has('gross_total_myr')
                ->has('locked_at')
                ->has('paid_at')
            )
            // Newest period first — April before March.
            ->where('runs.data.0.id', $aprilRun->id)
            ->where('runs.data.1.id', $marchRun->id)
            ->where('runs.data.0.status', 'draft')
        );
});
