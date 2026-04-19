<?php

use App\Models\LiveHostPayrollRun;
use App\Models\LiveHostPlatformAccount;
use App\Models\LiveSession;
use App\Models\LiveSessionGmvAdjustment;
use App\Models\Platform;
use App\Models\PlatformAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/**
 * Build an ended, verified session wired to the seeded Ahmad host and TikTok
 * Shop platform so the observer can snapshot against a real commission rate.
 *
 * @param  array<string, mixed>  $overrides
 */
function makeCommissionPropsSession(User $host, Platform $platform, array $overrides = []): LiveSession
{
    $account = PlatformAccount::factory()->create([
        'platform_id' => $platform->id,
        'user_id' => $host->id,
    ]);

    $pivot = LiveHostPlatformAccount::create([
        'user_id' => $host->id,
        'platform_account_id' => $account->id,
        'creator_handle' => '@amarmirzabedaie',
        'creator_platform_user_id' => '6526000000',
        'is_primary' => true,
    ]);

    return LiveSession::factory()->create(array_merge([
        'platform_account_id' => $account->id,
        'live_host_platform_account_id' => $pivot->id,
        'live_host_id' => $host->id,
        'status' => 'ended',
        'verification_status' => 'pending',
        'gmv_locked_at' => null,
        'commission_snapshot_json' => null,
        'scheduled_start_at' => now()->subHour(),
        'actual_start_at' => now()->subHour(),
        'actual_end_at' => now()->subMinutes(10),
        'duration_minutes' => 50,
        'gmv_amount' => 1500,
        'gmv_adjustment' => 0,
    ], $overrides));
}

beforeEach(function () {
    $this->seed(\Database\Seeders\LiveHostCommissionSeeder::class);
    $this->ahmad = User::where('email', 'ahmad@livehost.com')->first();
    $this->tiktok = Platform::where('slug', 'tiktok-shop')->firstOrFail();
    $this->pic = User::factory()->create(['role' => 'admin_livehost']);
});

it('exposes commission fields to the PIC Session Detail Inertia props', function () {
    $session = makeCommissionPropsSession($this->ahmad, $this->tiktok);

    // Two adjustments totaling -170
    LiveSessionGmvAdjustment::create([
        'live_session_id' => $session->id,
        'amount_myr' => -120,
        'reason' => 'Order ABC refunded',
        'adjusted_by' => $this->pic->id,
        'adjusted_at' => now(),
    ]);
    LiveSessionGmvAdjustment::create([
        'live_session_id' => $session->id,
        'amount_myr' => -50,
        'reason' => 'COD failure',
        'adjusted_by' => $this->pic->id,
        'adjusted_at' => now(),
    ]);

    // Let the observer snapshot commission on verify.
    $session->gmv_adjustment = -170;
    $session->verification_status = 'verified';
    $session->save();

    $response = actingAs($this->pic)->get(route('livehost.sessions.show', $session));

    $response->assertInertia(fn (Assert $page) => $page
        ->component('sessions/Show', false)
        ->has('session.gmv_amount')
        ->where('session.gmv_amount', 1500)
        ->has('session.gmv_adjustment')
        ->where('session.gmv_adjustment', -170)
        ->has('session.gmv_locked_at')
        ->has('session.commission_snapshot_json')
        ->has('session.gmv_adjustments', 2)
        ->has('session.gmv_adjustments.0.reason')
        ->has('session.gmv_adjustments.0.amount_myr')
        ->has('session.gmv_adjustments.0.adjusted_by')
        ->has('session.creator_handle')
        ->where('session.creator_handle', '@amarmirzabedaie')
        ->has('session.payroll_locked')
        ->where('session.payroll_locked', false)
    );
});

it('exposes payroll_locked=true when session month is in a locked payroll run', function () {
    $session = makeCommissionPropsSession($this->ahmad, $this->tiktok);

    LiveHostPayrollRun::create([
        'period_start' => $session->actual_end_at->copy()->startOfMonth()->toDateString(),
        'period_end' => $session->actual_end_at->copy()->endOfMonth()->toDateString(),
        'cutoff_date' => $session->actual_end_at->copy()->endOfMonth()->toDateString(),
        'status' => 'locked',
        'locked_at' => now(),
        'locked_by' => $this->pic->id,
    ]);

    actingAs($this->pic)
        ->get(route('livehost.sessions.show', $session))
        ->assertInertia(fn (Assert $page) => $page
            ->where('session.payroll_locked', true)
        );
});

it('panel is not visible to live_host role (assertForbidden at route level)', function () {
    $session = makeCommissionPropsSession($this->ahmad, $this->tiktok);

    $host = User::factory()->create(['role' => 'live_host']);

    actingAs($host)
        ->get(route('livehost.sessions.show', $session))
        ->assertForbidden();
});

it('exposes commission fields for a verified session with snapshot', function () {
    $session = makeCommissionPropsSession($this->ahmad, $this->tiktok, [
        'gmv_amount' => 1330,
    ]);

    $session->verification_status = 'verified';
    $session->save();

    actingAs($this->pic)
        ->get(route('livehost.sessions.show', $session))
        ->assertInertia(fn (Assert $page) => $page
            ->has('session.commission_snapshot_json.net_gmv')
            ->has('session.commission_snapshot_json.gmv_commission')
            ->has('session.commission_snapshot_json.per_live_rate')
            ->has('session.commission_snapshot_json.session_total')
            ->has('session.gmv_locked_at')
        );
});
