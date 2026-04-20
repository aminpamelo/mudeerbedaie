<?php

use App\Jobs\ProcessTiktokImportJob;
use App\Models\LiveHostPayrollRun;
use App\Models\LiveHostPlatformAccount;
use App\Models\LiveSession;
use App\Models\Platform;
use App\Models\PlatformAccount;
use App\Models\TiktokLiveReport;
use App\Models\TiktokReportImport;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->seed(\Database\Seeders\LiveHostCommissionSeeder::class);
    $this->pic = User::factory()->create(['role' => 'admin_livehost']);
    $this->ahmad = User::where('email', 'ahmad@livehost.com')->first();
    $this->tiktok = Platform::where('slug', 'tiktok-shop')->firstOrFail();
});

/**
 * Build a verified, ended TikTok Shop session for the seeded Ahmad host so
 * apply() has a real snapshot-capable target. Returns [session, pivot, report].
 */
function makeLiveAnalysisTarget(User $host, Platform $platform, User $pic, array $overrides = []): array
{
    $account = PlatformAccount::factory()->create([
        'platform_id' => $platform->id,
        'user_id' => $host->id,
    ]);

    $pivot = LiveHostPlatformAccount::create([
        'user_id' => $host->id,
        'platform_account_id' => $account->id,
        'creator_handle' => '@ahmad',
        'creator_platform_user_id' => '6526684195492729856',
        'is_primary' => true,
    ]);

    $session = LiveSession::factory()->create(array_merge([
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
        'gmv_amount' => 100,
        'gmv_adjustment' => 0,
        'gmv_source' => 'manual',
    ], $overrides));

    $import = TiktokReportImport::create([
        'report_type' => 'live_analysis',
        'file_path' => 'tiktok-imports/dummy.xlsx',
        'uploaded_by' => $pic->id,
        'uploaded_at' => now(),
        'period_start' => now()->startOfMonth()->toDateString(),
        'period_end' => now()->endOfMonth()->toDateString(),
        'status' => 'completed',
    ]);

    $report = TiktokLiveReport::create([
        'import_id' => $import->id,
        'tiktok_creator_id' => '6526684195492729856',
        'creator_display_name' => 'Ahmad',
        'creator_nickname' => 'ahmad',
        'launched_time' => $session->actual_start_at,
        'duration_seconds' => 3000,
        'gmv_myr' => 987.65,
        'live_attributed_gmv_myr' => 987.65,
        'matched_live_session_id' => $session->id,
    ]);

    return [$session, $pivot, $report, $import];
}

it('PIC uploads Live Analysis xlsx and gets an import record', function () {
    Storage::fake('local');
    Queue::fake();

    $response = actingAs($this->pic)->post('/livehost/tiktok-imports', [
        'report_type' => 'live_analysis',
        'file' => UploadedFile::fake()->create('report.xlsx', 100, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'),
        'period_start' => '2026-04-01',
        'period_end' => '2026-04-30',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('tiktok_report_imports', [
        'report_type' => 'live_analysis',
        'status' => 'pending',
        'uploaded_by' => $this->pic->id,
    ]);
    Queue::assertPushed(ProcessTiktokImportJob::class);

    $import = TiktokReportImport::latest('id')->first();
    Storage::disk('local')->assertExists($import->file_path);
});

it('PIC uploads All Order xlsx and gets an order_list import record', function () {
    Storage::fake('local');
    Queue::fake();

    $response = actingAs($this->pic)->post('/livehost/tiktok-imports', [
        'report_type' => 'order_list',
        'file' => UploadedFile::fake()->create('orders.xlsx', 100, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'),
        'period_start' => '2026-04-01',
        'period_end' => '2026-04-30',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('tiktok_report_imports', [
        'report_type' => 'order_list',
        'status' => 'pending',
    ]);
    Queue::assertPushed(ProcessTiktokImportJob::class);
});

it('validates required fields on upload', function () {
    Storage::fake('local');

    actingAs($this->pic)
        ->post('/livehost/tiktok-imports', [])
        ->assertSessionHasErrors(['report_type', 'file', 'period_start', 'period_end']);
});

it('validates period_end is after period_start', function () {
    Storage::fake('local');

    actingAs($this->pic)
        ->post('/livehost/tiktok-imports', [
            'report_type' => 'live_analysis',
            'file' => UploadedFile::fake()->create('report.xlsx', 50, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'),
            'period_start' => '2026-04-30',
            'period_end' => '2026-04-01',
        ])
        ->assertSessionHasErrors('period_end');
});

it('rejects files over 20 MB', function () {
    Storage::fake('local');

    actingAs($this->pic)
        ->post('/livehost/tiktok-imports', [
            'report_type' => 'live_analysis',
            'file' => UploadedFile::fake()->create('huge.xlsx', 25000, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'),
            'period_start' => '2026-04-01',
            'period_end' => '2026-04-30',
        ])
        ->assertSessionHasErrors('file');
});

it('index lists imports for PIC', function () {
    TiktokReportImport::create([
        'report_type' => 'live_analysis',
        'file_path' => 'tiktok-imports/a.xlsx',
        'uploaded_by' => $this->pic->id,
        'uploaded_at' => now(),
        'period_start' => '2026-04-01',
        'period_end' => '2026-04-30',
        'status' => 'completed',
    ]);

    actingAs($this->pic)
        ->get('/livehost/tiktok-imports')
        ->assertSuccessful();
});

it('show returns the import with its rows', function () {
    [, , , $import] = makeLiveAnalysisTarget($this->ahmad, $this->tiktok, $this->pic);

    actingAs($this->pic)
        ->get("/livehost/tiktok-imports/{$import->id}")
        ->assertSuccessful();
});

it('apply updates matched sessions and sets gmv_source=tiktok_import', function () {
    [$session, , $report] = makeLiveAnalysisTarget($this->ahmad, $this->tiktok, $this->pic);

    actingAs($this->pic)
        ->post("/livehost/tiktok-imports/{$report->import_id}/apply", [
            'report_ids' => [$report->id],
        ])
        ->assertRedirect();

    $session->refresh();

    expect((float) $session->gmv_amount)->toBe(987.65);
    expect($session->gmv_source)->toBe('tiktok_import');
});

it('apply re-snapshots commission when session is already verified', function () {
    [$session, , $report] = makeLiveAnalysisTarget($this->ahmad, $this->tiktok, $this->pic);

    // Verify first to create the initial snapshot via observer.
    $session->forceFill(['verification_status' => 'verified'])->save();
    $session->refresh();
    expect($session->gmv_locked_at)->not->toBeNull();
    expect((float) $session->commission_snapshot_json['net_gmv'])->toBe(100.0);

    actingAs($this->pic)
        ->post("/livehost/tiktok-imports/{$report->import_id}/apply", [
            'report_ids' => [$report->id],
        ])
        ->assertRedirect();

    $session->refresh();

    expect((float) $session->gmv_amount)->toBe(987.65);
    expect((float) $session->commission_snapshot_json['net_gmv'])->toBe(987.65);
});

it('apply skips sessions in locked payroll periods', function () {
    [$session, , $report] = makeLiveAnalysisTarget($this->ahmad, $this->tiktok, $this->pic);

    LiveHostPayrollRun::create([
        'period_start' => $session->actual_end_at->copy()->startOfMonth()->toDateString(),
        'period_end' => $session->actual_end_at->copy()->endOfMonth()->toDateString(),
        'cutoff_date' => $session->actual_end_at->copy()->endOfMonth()->toDateString(),
        'status' => 'locked',
        'locked_at' => now(),
        'locked_by' => $this->pic->id,
    ]);

    $response = actingAs($this->pic)
        ->post("/livehost/tiktok-imports/{$report->import_id}/apply", [
            'report_ids' => [$report->id],
        ]);

    $response->assertRedirect();
    $response->assertSessionHas('success', fn (string $msg) => str_contains($msg, 'skipped 1'));

    $session->refresh();

    // GMV should NOT have changed; source stays manual.
    expect((float) $session->gmv_amount)->toBe(100.0);
    expect($session->gmv_source)->toBe('manual');
});

it('apply ignores unmatched reports (skipped silently)', function () {
    [, , , $import] = makeLiveAnalysisTarget($this->ahmad, $this->tiktok, $this->pic);

    // Unmatched report → no session to apply against.
    $unmatched = TiktokLiveReport::create([
        'import_id' => $import->id,
        'tiktok_creator_id' => '0000000000000000000',
        'launched_time' => now(),
        'gmv_myr' => 42.00,
    ]);

    actingAs($this->pic)
        ->post("/livehost/tiktok-imports/{$import->id}/apply", [
            'report_ids' => [$unmatched->id],
        ])
        ->assertRedirect();
});

it('apply requires report_ids', function () {
    [, , , $import] = makeLiveAnalysisTarget($this->ahmad, $this->tiktok, $this->pic);

    actingAs($this->pic)
        ->post("/livehost/tiktok-imports/{$import->id}/apply", [])
        ->assertSessionHasErrors('report_ids');
});

it('live_host role cannot upload', function () {
    Storage::fake('local');
    Queue::fake();

    $host = User::factory()->create(['role' => 'live_host']);

    actingAs($host)
        ->post('/livehost/tiktok-imports', [
            'report_type' => 'live_analysis',
            'file' => UploadedFile::fake()->create('r.xlsx', 10, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'),
            'period_start' => '2026-04-01',
            'period_end' => '2026-04-30',
        ])
        ->assertForbidden();
});

it('live_host role cannot apply', function () {
    [, , $report] = makeLiveAnalysisTarget($this->ahmad, $this->tiktok, $this->pic);

    $host = User::factory()->create(['role' => 'live_host']);

    actingAs($host)
        ->post("/livehost/tiktok-imports/{$report->import_id}/apply", [
            'report_ids' => [$report->id],
        ])
        ->assertForbidden();
});

it('live_host role cannot access index or show', function () {
    [, , , $import] = makeLiveAnalysisTarget($this->ahmad, $this->tiktok, $this->pic);

    $host = User::factory()->create(['role' => 'live_host']);

    actingAs($host)->get('/livehost/tiktok-imports')->assertForbidden();
    actingAs($host)->get("/livehost/tiktok-imports/{$import->id}")->assertForbidden();
});
