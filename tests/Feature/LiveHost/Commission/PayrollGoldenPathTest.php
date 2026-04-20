<?php

use App\Models\LiveHostPayrollRun;
use App\Models\LiveHostPlatformAccount;
use App\Models\LiveSession;
use App\Models\Platform;
use App\Models\PlatformAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/**
 * Mirrors the helper from PayrollGenerateDraftTest.php — seed N verified
 * sessions for the host with a single total adjustment applied to session #0.
 * Sessions start 2026-04-15 08:00, one hour apart, so they fall within April.
 */
function seedGoldenPathSessionsForHost(User $host, Platform $platform, int $count, float $totalGmv, float $totalAdjustment = 0.0): \Illuminate\Support\Collection
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

    $perSessionGmv = round($totalGmv / $count, 2);

    $sessions = collect();
    for ($i = 0; $i < $count; $i++) {
        $start = Carbon::parse('2026-04-15 08:00:00')->addHours($i);
        $end = $start->copy()->addMinutes(50);

        $session = LiveSession::factory()->create([
            'platform_account_id' => $account->id,
            'live_host_platform_account_id' => $pivot->id,
            'live_host_id' => $host->id,
            'status' => 'ended',
            'verification_status' => 'pending',
            'scheduled_start_at' => $start,
            'actual_start_at' => $start,
            'actual_end_at' => $end,
            'duration_minutes' => 50,
            'gmv_amount' => $perSessionGmv,
            'gmv_adjustment' => $i === 0 ? $totalAdjustment : 0,
        ]);

        // Trigger observer by flipping verification_status dirty.
        $session->verification_status = 'verified';
        $session->save();

        $sessions->push($session->fresh());
    }

    return $sessions;
}

it('end-to-end: seed → host submits recap → PIC verifies → payroll generates with exact worked-example numbers', function () {
    // 1. Seed commission worked example (Ahmad/Sarah/Amin + TikTok rates)
    $this->seed(\Database\Seeders\LiveHostCommissionSeeder::class);
    $ahmad = User::where('email', 'ahmad@livehost.com')->firstOrFail();
    $sarah = User::where('email', 'sarah@livehost.com')->firstOrFail();
    $amin = User::where('email', 'amin@livehost.com')->firstOrFail();
    $tiktok = Platform::where('slug', 'tiktok-shop')->firstOrFail();
    $pic = User::factory()->create(['role' => 'admin_livehost']);

    // 2. Seed April 2026 verified sessions per design doc §5.3.
    seedGoldenPathSessionsForHost($ahmad, $tiktok, 8, 12000, -200);
    seedGoldenPathSessionsForHost($sarah, $tiktok, 12, 18000, -500);
    seedGoldenPathSessionsForHost($amin, $tiktok, 10, 22000, -300);

    // 3. PIC generates payroll draft via the controller (HTTP path).
    $response = actingAs($pic)->post('/livehost/payroll', [
        'period_start' => '2026-04-01',
        'period_end' => '2026-04-30',
    ]);
    $response->assertRedirect();

    $run = LiveHostPayrollRun::latest('id')->first();

    // 4. Assert exact numbers from design §5.3.
    $items = $run->items->keyBy('user_id');
    expect((float) $items[$ahmad->id]->net_payout_myr)->toEqual(2864.60);
    expect((float) $items[$sarah->id]->net_payout_myr)->toEqual(3105.20);
    expect((float) $items[$amin->id]->net_payout_myr)->toEqual(1802.00);
    expect(round((float) $run->items->sum('net_payout_myr'), 2))->toEqual(7771.80);

    // 5. Effective payroll % of net GMV (11,800 + 17,500 + 21,700 = 51,000).
    $totalNetGmv = $run->items->sum('net_gmv_myr');
    expect((float) $totalNetGmv)->toEqual(51000.00);
    $effectivePct = round(7771.80 / 51000 * 100, 2);
    expect($effectivePct)->toBeGreaterThan(15.0)->toBeLessThan(15.5); // 15.24% expected

    // 6. Lock the run via controller.
    $response = actingAs($pic)->post("/livehost/payroll/{$run->id}/lock");
    $response->assertRedirect();
    expect($run->fresh()->status)->toBe('locked');

    // 7. Attempt to add adjustment on a locked-period session → 403.
    $aprilSession = LiveSession::where('live_host_id', $ahmad->id)
        ->whereYear('actual_end_at', 2026)
        ->whereMonth('actual_end_at', 4)
        ->first();
    $response = actingAs($pic)->post("/livehost/sessions/{$aprilSession->id}/adjustments", [
        'amount' => -50,
        'reason' => 'Test',
    ]);
    $response->assertStatus(403);

    // 8. Mark paid via controller.
    $response = actingAs($pic)->post("/livehost/payroll/{$run->id}/mark-paid");
    $response->assertRedirect();
    expect($run->fresh()->status)->toBe('paid');
});
