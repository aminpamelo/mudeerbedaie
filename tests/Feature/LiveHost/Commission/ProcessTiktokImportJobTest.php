<?php

use App\Jobs\ProcessTiktokImportJob;
use App\Models\LiveHostPlatformAccount;
use App\Models\LiveSession;
use App\Models\LiveSessionGmvAdjustment;
use App\Models\Platform;
use App\Models\PlatformAccount;
use App\Models\ProductOrder;
use App\Models\TiktokLiveReport;
use App\Models\TiktokOrder;
use App\Models\TiktokReportImport;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

/**
 * Copy a shipped TikTok fixture xlsx into the faked `local` disk under the
 * same path the controller would generate. Returns the storage-relative path.
 */
function copyFixtureToStorage(string $fixture, string $storedAs): string
{
    $contents = file_get_contents(base_path("tests/Fixtures/tiktok/{$fixture}"));
    Storage::disk('local')->put($storedAs, $contents);

    return $storedAs;
}

beforeEach(function () {
    $this->pic = User::factory()->create(['role' => 'admin_livehost']);
});

it('parses Live Analysis xlsx and creates TiktokLiveReport rows', function () {
    Storage::fake('local');
    $path = copyFixtureToStorage('live_analysis_sample.xlsx', 'tiktok-imports/live.xlsx');

    $platform = Platform::firstOrCreate(
        ['slug' => 'tiktok-shop'],
        Platform::factory()->make(['slug' => 'tiktok-shop', 'name' => 'TikTok Shop'])->toArray()
    );
    $account = PlatformAccount::factory()->create([
        'platform_id' => $platform->id,
        'user_id' => $this->pic->id,
    ]);

    $import = TiktokReportImport::create([
        'report_type' => 'live_analysis',
        'platform_account_id' => $account->id,
        'file_path' => $path,
        'uploaded_by' => $this->pic->id,
        'uploaded_at' => now(),
        'period_start' => '2026-04-01',
        'period_end' => '2026-04-30',
        'status' => 'pending',
    ]);

    (new ProcessTiktokImportJob($import->id))->handle(
        app(\App\Services\LiveHost\Tiktok\LiveAnalysisXlsxParser::class),
        app(\App\Services\LiveHost\Tiktok\AllOrderXlsxParser::class),
        app(\App\Services\LiveHost\Tiktok\LiveSessionMatcher::class),
        app(\App\Services\LiveHost\Tiktok\OrderRefundReconciler::class),
    );

    $import->refresh();

    expect($import->status)->toBe('completed');
    expect($import->total_rows)->toBe(2);
    expect($import->matched_rows)->toBe(0);
    expect($import->unmatched_rows)->toBe(2);
    expect(TiktokLiveReport::where('import_id', $import->id)->count())->toBe(2);
});

it('attempts to match parsed reports to live sessions', function () {
    Storage::fake('local');

    // Set up a LiveSession that the matcher WILL match for first fixture row:
    // creator_platform_user_id='6526684195492729856', launched_time='2026-04-18 22:14'.
    $host = User::factory()->create(['role' => 'live_host']);
    $platform = Platform::firstOrCreate(
        ['slug' => 'tiktok-shop'],
        Platform::factory()->make(['slug' => 'tiktok-shop', 'name' => 'TikTok Shop'])->toArray()
    );
    $platformAccount = PlatformAccount::factory()->create([
        'user_id' => $host->id,
        'platform_id' => $platform->id,
    ]);
    $pivot = LiveHostPlatformAccount::create([
        'user_id' => $host->id,
        'platform_account_id' => $platformAccount->id,
        'creator_handle' => '@amar',
        'creator_platform_user_id' => '6526684195492729856',
        'is_primary' => true,
    ]);
    $session = LiveSession::factory()->create([
        'live_host_id' => $host->id,
        'platform_account_id' => $platformAccount->id,
        'live_host_platform_account_id' => $pivot->id,
        'status' => 'ended',
        'actual_start_at' => \Carbon\Carbon::parse('2026-04-18 22:14:00'),
    ]);

    $path = copyFixtureToStorage('live_analysis_sample.xlsx', 'tiktok-imports/live.xlsx');

    $import = TiktokReportImport::create([
        'report_type' => 'live_analysis',
        'platform_account_id' => $platformAccount->id,
        'file_path' => $path,
        'uploaded_by' => $this->pic->id,
        'uploaded_at' => now(),
        'period_start' => '2026-04-01',
        'period_end' => '2026-04-30',
        'status' => 'pending',
    ]);

    (new ProcessTiktokImportJob($import->id))->handle(
        app(\App\Services\LiveHost\Tiktok\LiveAnalysisXlsxParser::class),
        app(\App\Services\LiveHost\Tiktok\AllOrderXlsxParser::class),
        app(\App\Services\LiveHost\Tiktok\LiveSessionMatcher::class),
        app(\App\Services\LiveHost\Tiktok\OrderRefundReconciler::class),
    );

    $import->refresh();

    expect($import->status)->toBe('completed');
    expect($import->matched_rows)->toBe(1);

    $matchedReport = TiktokLiveReport::where('import_id', $import->id)
        ->where('tiktok_creator_id', '6526684195492729856')
        ->first();
    expect($matchedReport)->not->toBeNull();
    expect($matchedReport->matched_live_session_id)->toBe($session->id);
});

it('parses All Order xlsx and creates TiktokOrder rows', function () {
    Storage::fake('local');
    $path = copyFixtureToStorage('all_order_sample.xlsx', 'tiktok-imports/orders.xlsx');

    $platform = Platform::firstOrCreate(
        ['slug' => 'tiktok-shop'],
        Platform::factory()->make(['slug' => 'tiktok-shop', 'name' => 'TikTok Shop'])->toArray()
    );
    $account = PlatformAccount::factory()->create([
        'platform_id' => $platform->id,
        'user_id' => $this->pic->id,
    ]);

    $import = TiktokReportImport::create([
        'report_type' => 'order_list',
        'platform_account_id' => $account->id,
        'file_path' => $path,
        'uploaded_by' => $this->pic->id,
        'uploaded_at' => now(),
        'period_start' => '2026-04-01',
        'period_end' => '2026-04-30',
        'status' => 'pending',
    ]);

    (new ProcessTiktokImportJob($import->id))->handle(
        app(\App\Services\LiveHost\Tiktok\LiveAnalysisXlsxParser::class),
        app(\App\Services\LiveHost\Tiktok\AllOrderXlsxParser::class),
        app(\App\Services\LiveHost\Tiktok\LiveSessionMatcher::class),
        app(\App\Services\LiveHost\Tiktok\OrderRefundReconciler::class),
    );

    $import->refresh();

    expect($import->status)->toBe('completed');
    expect($import->total_rows)->toBe(3);
    expect(TiktokOrder::where('import_id', $import->id)->count())->toBe(3);
});

it('marks import failed on parse error', function () {
    Storage::fake('local');

    // Point at a path that does not exist on the faked disk — PhpSpreadsheet's
    // IOFactory throws "File does not exist or is not readable" which the job
    // must catch, persist to error_log_json, and rethrow.
    $import = TiktokReportImport::create([
        'report_type' => 'live_analysis',
        'file_path' => 'tiktok-imports/missing.xlsx',
        'uploaded_by' => $this->pic->id,
        'uploaded_at' => now(),
        'period_start' => '2026-04-01',
        'period_end' => '2026-04-30',
        'status' => 'pending',
    ]);

    try {
        (new ProcessTiktokImportJob($import->id))->handle(
            app(\App\Services\LiveHost\Tiktok\LiveAnalysisXlsxParser::class),
            app(\App\Services\LiveHost\Tiktok\AllOrderXlsxParser::class),
            app(\App\Services\LiveHost\Tiktok\LiveSessionMatcher::class),
            app(\App\Services\LiveHost\Tiktok\OrderRefundReconciler::class),
        );
        $this->fail('Expected parse exception to be rethrown.');
    } catch (\Throwable $e) {
        // expected — job rethrows after marking failed
    }

    $import->refresh();
    expect($import->status)->toBe('failed');
    expect($import->error_log_json)->toBeArray();
    expect($import->error_log_json)->toHaveKey('message');
    expect($import->error_log_json)->toHaveKey('line');
});

it('runs OrderRefundReconciler after a live_analysis import is processed', function () {
    Storage::fake('local');
    $path = copyFixtureToStorage('live_analysis_sample.xlsx', 'tiktok-imports/live.xlsx');

    $platform = Platform::firstOrCreate(
        ['slug' => 'tiktok-shop'],
        Platform::factory()->make(['slug' => 'tiktok-shop', 'name' => 'TikTok Shop'])->toArray()
    );
    $account = PlatformAccount::factory()->create([
        'platform_id' => $platform->id,
        'user_id' => $this->pic->id,
    ]);

    // A live session covering a refunded order's paid_time inside the period.
    $paidAt = \Carbon\Carbon::parse('2026-04-19 14:30:00');
    $session = LiveSession::factory()->create([
        'platform_account_id' => $account->id,
        'status' => 'ended',
        'scheduled_start_at' => $paidAt->copy()->subHours(2),
        'actual_start_at' => $paidAt->copy()->subHour(),
        'actual_end_at' => $paidAt->copy()->addMinutes(10),
        'duration_minutes' => 70,
        'gmv_amount' => 500,
        'gmv_adjustment' => 0,
    ]);

    ProductOrder::factory()->create([
        'source' => 'tiktok_shop',
        'platform_account_id' => $account->id,
        'matched_live_session_id' => $session->id,
        'platform_order_id' => 'JOB-RECONCILE-1',
        'status' => 'refunded',
        'paid_time' => $paidAt,
        'total_amount' => 200,
    ]);

    $import = TiktokReportImport::create([
        'report_type' => 'live_analysis',
        'platform_account_id' => $account->id,
        'file_path' => $path,
        'uploaded_by' => $this->pic->id,
        'uploaded_at' => now(),
        'period_start' => '2026-04-01',
        'period_end' => '2026-04-30',
        'status' => 'pending',
    ]);

    (new ProcessTiktokImportJob($import->id))->handle(
        app(\App\Services\LiveHost\Tiktok\LiveAnalysisXlsxParser::class),
        app(\App\Services\LiveHost\Tiktok\AllOrderXlsxParser::class),
        app(\App\Services\LiveHost\Tiktok\LiveSessionMatcher::class),
        app(\App\Services\LiveHost\Tiktok\OrderRefundReconciler::class),
    );

    $import->refresh();
    expect($import->status)->toBe('completed');

    // Reconciler must have proposed a negative adjustment for the refunded order.
    $adjustment = LiveSessionGmvAdjustment::where('live_session_id', $session->id)->first();
    expect($adjustment)->not->toBeNull();
    expect($adjustment->status)->toBe('proposed');
    expect((float) $adjustment->amount_myr)->toBe(-200.0);
    expect($adjustment->reason)->toContain('JOB-RECONCILE-1');
});

it('does NOT run OrderRefundReconciler on legacy order_list imports', function () {
    Storage::fake('local');
    $path = copyFixtureToStorage('all_order_sample.xlsx', 'tiktok-imports/orders.xlsx');

    $platform = Platform::firstOrCreate(
        ['slug' => 'tiktok-shop'],
        Platform::factory()->make(['slug' => 'tiktok-shop', 'name' => 'TikTok Shop'])->toArray()
    );
    $account = PlatformAccount::factory()->create([
        'platform_id' => $platform->id,
        'user_id' => $this->pic->id,
    ]);

    // ProductOrder data that, if reconciler were triggered, would propose
    // an adjustment. We assert NO adjustment is proposed because Task 9
    // moved the trigger to the live_analysis branch only.
    $paidAt = \Carbon\Carbon::parse('2026-04-19 14:30:00');
    $session = LiveSession::factory()->create([
        'platform_account_id' => $account->id,
        'status' => 'ended',
        'scheduled_start_at' => $paidAt->copy()->subHours(2),
        'actual_start_at' => $paidAt->copy()->subHour(),
        'actual_end_at' => $paidAt->copy()->addMinutes(10),
        'duration_minutes' => 70,
        'gmv_amount' => 500,
        'gmv_adjustment' => 0,
    ]);

    ProductOrder::factory()->create([
        'source' => 'tiktok_shop',
        'platform_account_id' => $account->id,
        'matched_live_session_id' => $session->id,
        'platform_order_id' => 'JOB-NORECON-1',
        'status' => 'refunded',
        'paid_time' => $paidAt,
        'total_amount' => 200,
    ]);

    $import = TiktokReportImport::create([
        'report_type' => 'order_list',
        'platform_account_id' => $account->id,
        'file_path' => $path,
        'uploaded_by' => $this->pic->id,
        'uploaded_at' => now(),
        'period_start' => '2026-04-01',
        'period_end' => '2026-04-30',
        'status' => 'pending',
    ]);

    (new ProcessTiktokImportJob($import->id))->handle(
        app(\App\Services\LiveHost\Tiktok\LiveAnalysisXlsxParser::class),
        app(\App\Services\LiveHost\Tiktok\AllOrderXlsxParser::class),
        app(\App\Services\LiveHost\Tiktok\LiveSessionMatcher::class),
        app(\App\Services\LiveHost\Tiktok\OrderRefundReconciler::class),
    );

    $import->refresh();
    expect($import->status)->toBe('completed');

    // Reconciler must NOT have run on the order_list branch.
    expect(LiveSessionGmvAdjustment::count())->toBe(0);
});

it('skips already-completed imports (idempotent re-run)', function () {
    Storage::fake('local');
    $path = copyFixtureToStorage('live_analysis_sample.xlsx', 'tiktok-imports/live.xlsx');

    $import = TiktokReportImport::create([
        'report_type' => 'live_analysis',
        'file_path' => $path,
        'uploaded_by' => $this->pic->id,
        'uploaded_at' => now(),
        'period_start' => '2026-04-01',
        'period_end' => '2026-04-30',
        'status' => 'completed',
        'total_rows' => 5,
    ]);

    (new ProcessTiktokImportJob($import->id))->handle(
        app(\App\Services\LiveHost\Tiktok\LiveAnalysisXlsxParser::class),
        app(\App\Services\LiveHost\Tiktok\AllOrderXlsxParser::class),
        app(\App\Services\LiveHost\Tiktok\LiveSessionMatcher::class),
        app(\App\Services\LiveHost\Tiktok\OrderRefundReconciler::class),
    );

    $import->refresh();

    // Totals untouched, no new live report rows created.
    expect($import->total_rows)->toBe(5);
    expect(TiktokLiveReport::where('import_id', $import->id)->count())->toBe(0);
});
