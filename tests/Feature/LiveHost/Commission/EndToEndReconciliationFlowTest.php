<?php

use App\Models\LiveHostPayrollRun;
use App\Models\LiveHostPlatformAccount;
use App\Models\LiveSession;
use App\Models\LiveSessionGmvAdjustment;
use App\Models\Platform;
use App\Models\PlatformAccount;
use App\Models\TiktokLiveReport;
use App\Models\TiktokOrder;
use App\Models\TiktokReportImport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/**
 * Helper — build a verified, ended LiveSession for the given host on the given
 * platform, with host-entered GMV. The verification flag is flipped afterwards
 * so LiveSessionVerifiedObserver fires and snapshots commission.
 *
 * @return array{0: LiveSession, 1: LiveHostPlatformAccount}
 */
function seedE2EHostSession(
    User $host,
    Platform $platform,
    Carbon $actualStart,
    float $gmv,
    ?string $creatorPlatformUserId = null,
): array {
    $account = PlatformAccount::factory()->create([
        'platform_id' => $platform->id,
        'user_id' => $host->id,
    ]);

    $pivot = LiveHostPlatformAccount::create([
        'user_id' => $host->id,
        'platform_account_id' => $account->id,
        'creator_handle' => '@'.strtolower($host->name),
        'creator_platform_user_id' => $creatorPlatformUserId,
        'is_primary' => true,
    ]);

    $session = LiveSession::factory()->create([
        'platform_account_id' => $account->id,
        'live_host_platform_account_id' => $pivot->id,
        'live_host_id' => $host->id,
        'status' => 'ended',
        'verification_status' => 'pending',
        'scheduled_start_at' => $actualStart,
        'actual_start_at' => $actualStart,
        'actual_end_at' => $actualStart->copy()->addMinutes(100),
        'duration_minutes' => 100,
        'gmv_amount' => $gmv,
        'gmv_adjustment' => 0,
        'gmv_source' => 'manual',
    ]);

    // Flip verification_status dirty → observer snapshots commission.
    $session->verification_status = 'verified';
    $session->save();

    return [$session->fresh(), $pivot];
}

beforeEach(function () {
    // Force sync queue so the upload HTTP path runs the job inline.
    config()->set('queue.default', 'sync');
});

it('end-to-end: host entry → PIC verify → payroll draft → TikTok import + apply → reconcile refunds → lock payroll', function () {
    // 1. Seed commission worked example + pick up canonical TikTok platform.
    $this->seed(\Database\Seeders\LiveHostCommissionSeeder::class);
    $ahmad = User::where('email', 'ahmad@livehost.com')->firstOrFail();
    $sarah = User::where('email', 'sarah@livehost.com')->firstOrFail();
    $amin = User::where('email', 'amin@livehost.com')->firstOrFail();
    $tiktok = Platform::where('slug', 'tiktok-shop')->firstOrFail();
    $pic = User::factory()->create(['role' => 'admin_livehost']);

    Storage::fake('local');

    // 2. Seed April 2026 verified sessions per host.
    //    Ahmad #1: host-entered 400 @ 2026-04-18 22:14 (matches fixture creator
    //              ID 6526684195492729856 + launched_time within 30min window).
    //              TikTok reality from fixture = 444.23.
    //    Ahmad #2: host-entered 450 @ 2026-04-17 10:00 (covers All Order
    //              refund rows at 17/04/2026 10:15 + 15/04/2026 08:22 if we
    //              push start back further; we give this session a wide
    //              window to catch the 17th refund by extending into that
    //              day — for the smoke test one matched refund is enough).
    //    Sarah:    host-entered 800 @ 2026-04-16 09:00, no creator id.
    //    Amin:     host-entered 1000 @ 2026-04-15 07:00, covers the
    //              15/04/2026 08:22 refund row.
    [$ahmadSession1] = seedE2EHostSession(
        $ahmad,
        $tiktok,
        Carbon::parse('2026-04-18 22:14:00'),
        400.00,
        '6526684195492729856',
    );

    // Ahmad's second session — wider window so the 17/04 10:15 order refund
    // falls cleanly inside [actual_start, actual_end+12h].
    $ahmadAccount2 = PlatformAccount::factory()->create([
        'platform_id' => $tiktok->id,
        'user_id' => $ahmad->id,
    ]);
    $ahmadPivot2 = LiveHostPlatformAccount::create([
        'user_id' => $ahmad->id,
        'platform_account_id' => $ahmadAccount2->id,
        'creator_handle' => '@ahmad2',
        'creator_platform_user_id' => null,
        'is_primary' => false,
    ]);
    $ahmadSession2 = LiveSession::factory()->create([
        'platform_account_id' => $ahmadAccount2->id,
        'live_host_platform_account_id' => $ahmadPivot2->id,
        'live_host_id' => $ahmad->id,
        'status' => 'ended',
        'verification_status' => 'pending',
        'scheduled_start_at' => Carbon::parse('2026-04-17 09:00:00'),
        'actual_start_at' => Carbon::parse('2026-04-17 09:00:00'),
        'actual_end_at' => Carbon::parse('2026-04-17 11:00:00'),
        'duration_minutes' => 120,
        'gmv_amount' => 450.00,
        'gmv_adjustment' => 0,
        'gmv_source' => 'manual',
    ]);
    $ahmadSession2->verification_status = 'verified';
    $ahmadSession2->save();
    $ahmadSession2 = $ahmadSession2->fresh();

    [$sarahSession] = seedE2EHostSession(
        $sarah,
        $tiktok,
        Carbon::parse('2026-04-16 09:00:00'),
        800.00,
    );

    [$aminSession] = seedE2EHostSession(
        $amin,
        $tiktok,
        Carbon::parse('2026-04-15 07:00:00'),
        1000.00,
    );

    // 3. Verification snapshots must be in place (set by observer).
    expect($ahmadSession1->gmv_locked_at)->not->toBeNull();
    expect($sarahSession->gmv_locked_at)->not->toBeNull();
    expect($aminSession->gmv_locked_at)->not->toBeNull();

    // 4. PIC generates a payroll draft via the controller.
    actingAs($pic)
        ->post('/livehost/payroll', [
            'period_start' => '2026-04-01',
            'period_end' => '2026-04-30',
        ])
        ->assertRedirect();

    $run = LiveHostPayrollRun::latest('id')->firstOrFail();
    expect($run->status)->toBe('draft');

    $initialGrossGmv = (float) $run->items->sum('total_gmv_myr');
    // Ahmad 400 + 450 + Sarah 800 + Amin 1000 = 2650.
    expect($initialGrossGmv)->toEqual(2650.00);

    $initialNetPayout = (float) $run->items->sum('net_payout_myr');
    expect($initialNetPayout)->toBeGreaterThan(0.0);

    // 5. PIC uploads the Live Analysis fixture xlsx via multipart. Queue is
    //    synchronous (beforeEach) so the job runs inline. Target Ahmad's
    //    session #1 platform account so the shop-scoped matcher picks up his
    //    fixture row.
    $laContents = file_get_contents(base_path('tests/Fixtures/tiktok/live_analysis_sample.xlsx'));
    actingAs($pic)
        ->post('/livehost/tiktok-imports', [
            'report_type' => 'live_analysis',
            'platform_account_id' => $ahmadSession1->platform_account_id,
            'file' => UploadedFile::fake()->createWithContent('la.xlsx', $laContents),
            'period_start' => '2026-04-01',
            'period_end' => '2026-04-30',
        ])
        ->assertRedirect();

    $liveAnalysisImport = TiktokReportImport::where('report_type', 'live_analysis')
        ->latest('id')
        ->firstOrFail();

    expect($liveAnalysisImport->status)->toBe('completed');
    expect($liveAnalysisImport->total_rows)->toBeGreaterThan(0);

    // 6. At least one row (Ahmad's fixture row) matches the first session.
    $matchedReport = TiktokLiveReport::where('import_id', $liveAnalysisImport->id)
        ->whereNotNull('matched_live_session_id')
        ->where('tiktok_creator_id', '6526684195492729856')
        ->first();
    expect($matchedReport)->not->toBeNull();
    expect($matchedReport->matched_live_session_id)->toBe($ahmadSession1->id);

    // 7. PIC applies the matched report → session GMV shifts to TikTok value.
    actingAs($pic)
        ->post("/livehost/tiktok-imports/{$liveAnalysisImport->id}/apply", [
            'report_ids' => [$matchedReport->id],
        ])
        ->assertRedirect();

    $ahmadSession1->refresh();
    expect((float) $ahmadSession1->gmv_amount)->toEqual(444.23);
    expect($ahmadSession1->gmv_source)->toBe('tiktok_import');

    // 8. PIC uploads the All Order fixture xlsx — reconciler runs inline.
    //    Target Amin's platform account so the 15/04 refund window matches
    //    his session under the shop-scoped reconciler.
    $orderContents = file_get_contents(base_path('tests/Fixtures/tiktok/all_order_sample.xlsx'));
    actingAs($pic)
        ->post('/livehost/tiktok-imports', [
            'report_type' => 'order_list',
            'platform_account_id' => $aminSession->platform_account_id,
            'file' => UploadedFile::fake()->createWithContent('orders.xlsx', $orderContents),
            'period_start' => '2026-04-01',
            'period_end' => '2026-04-30',
        ])
        ->assertRedirect();

    $orderImport = TiktokReportImport::where('report_type', 'order_list')
        ->latest('id')
        ->firstOrFail();

    expect($orderImport->status)->toBe('completed');
    expect(TiktokOrder::where('import_id', $orderImport->id)->count())->toBeGreaterThan(0);

    // 9. Reconciler should have proposed at least one adjustment for the
    //    April refund/cancelled orders (17/04 cancelled 80.00, 15/04 refund
    //    70.00, both fall into Ahmad #2 / Amin windows).
    $proposed = LiveSessionGmvAdjustment::where('status', 'proposed')
        ->where('reason', 'like', 'Auto: Order #%')
        ->get();
    expect($proposed)->not->toBeEmpty();

    // 10. PIC approves each proposed adjustment via controller POST.
    foreach ($proposed as $adj) {
        actingAs($pic)
            ->post("/livehost/sessions/{$adj->live_session_id}/adjustments/{$adj->id}/approve")
            ->assertRedirect();

        expect($adj->fresh()->status)->toBe('approved');
    }

    // Approving recomputes the session's cached gmv_adjustment, so the sum
    // of negative adjustments must show up on at least one session.
    $totalNegativeAdj = (float) LiveSession::query()
        ->whereIn('live_host_id', [$ahmad->id, $sarah->id, $amin->id])
        ->sum('gmv_adjustment');
    expect($totalNegativeAdj)->toBeLessThan(0.0);

    // 11. PIC recomputes the draft payroll so adjustments are rolled in.
    actingAs($pic)
        ->post("/livehost/payroll/{$run->id}/recompute")
        ->assertRedirect();

    $run->refresh();
    $run->load('items');

    $recomputedGrossGmv = (float) $run->items->sum('total_gmv_myr');
    $recomputedNetGmv = (float) $run->items->sum('net_gmv_myr');
    $recomputedNetPayout = (float) $run->items->sum('net_payout_myr');

    // Net GMV must be strictly LESS than gross GMV because approved negative
    // adjustments are now pulling it down.
    expect($recomputedNetGmv)->toBeLessThan($recomputedGrossGmv);

    // Ahmad's session #1 GMV also bumped from 400 → 444.23, so gross GMV is
    // higher than the initial draft (which used host-entered 400).
    expect($recomputedGrossGmv)->toBeGreaterThan($initialGrossGmv);

    // Net payout still positive — sanity check the chain didn't collapse.
    expect($recomputedNetPayout)->toBeGreaterThan(0.0);

    // 12. PIC locks the payroll.
    actingAs($pic)
        ->post("/livehost/payroll/{$run->id}/lock")
        ->assertRedirect();

    expect($run->fresh()->status)->toBe('locked');

    // 13. Further adjustment attempts on any April session → 403.
    actingAs($pic)
        ->post("/livehost/sessions/{$ahmadSession1->id}/adjustments", [
            'amount' => -10,
            'reason' => 'Should be blocked',
        ])
        ->assertStatus(403);

    actingAs($pic)
        ->post("/livehost/sessions/{$aminSession->id}/adjustments", [
            'amount' => -25,
            'reason' => 'Should be blocked too',
        ])
        ->assertStatus(403);

    // 14. And re-applying the TikTok Live Analysis row against the locked
    //     session silently skips instead of mutating GMV.
    $gmvBeforeReapply = (float) $ahmadSession1->fresh()->gmv_amount;
    $response = actingAs($pic)
        ->post("/livehost/tiktok-imports/{$liveAnalysisImport->id}/apply", [
            'report_ids' => [$matchedReport->id],
        ]);
    $response->assertRedirect();

    expect((float) $ahmadSession1->fresh()->gmv_amount)->toEqual($gmvBeforeReapply);
});
