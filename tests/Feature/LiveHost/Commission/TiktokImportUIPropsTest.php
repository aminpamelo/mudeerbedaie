<?php

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
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/**
 * Build a verified-ready live session so show() props line up exactly like
 * what the PIC sees after a real upload cycle.
 */
function uiPropsSession(User $host, Platform $platform, array $overrides = []): LiveSession
{
    $account = PlatformAccount::factory()->create([
        'platform_id' => $platform->id,
        'user_id' => $host->id,
    ]);

    $pivot = LiveHostPlatformAccount::create([
        'user_id' => $host->id,
        'platform_account_id' => $account->id,
        'creator_handle' => '@uiprops',
        'creator_platform_user_id' => '9999999999999999999',
        'is_primary' => true,
    ]);

    return LiveSession::factory()->create(array_merge([
        'platform_account_id' => $account->id,
        'live_host_platform_account_id' => $pivot->id,
        'live_host_id' => $host->id,
        'status' => 'ended',
        'scheduled_start_at' => now()->subHour(),
        'actual_start_at' => now()->subHour(),
        'actual_end_at' => now()->subMinutes(10),
        'duration_minutes' => 50,
        'gmv_amount' => 500,
        'gmv_adjustment' => 0,
    ], $overrides));
}

beforeEach(function () {
    $this->pic = User::factory()->create(['role' => 'admin_livehost']);
    $this->host = User::factory()->create(['role' => 'live_host']);
    $this->tiktok = Platform::firstOrCreate(
        ['slug' => 'tiktok-shop'],
        Platform::factory()->make(['slug' => 'tiktok-shop', 'name' => 'TikTok Shop'])->toArray(),
    );
});

it('index page receives paginated imports with counts', function () {
    $import = TiktokReportImport::create([
        'report_type' => 'live_analysis',
        'file_path' => 'tiktok-imports/a.xlsx',
        'uploaded_by' => $this->pic->id,
        'uploaded_at' => now(),
        'period_start' => '2026-04-01',
        'period_end' => '2026-04-30',
        'status' => 'completed',
        'total_rows' => 3,
        'matched_rows' => 2,
        'unmatched_rows' => 1,
    ]);

    // Seed live reports so matched/unmatched counts come out non-trivially.
    TiktokLiveReport::create([
        'import_id' => $import->id,
        'tiktok_creator_id' => '111',
        'launched_time' => now(),
        'gmv_myr' => 100,
        'matched_live_session_id' => null,
    ]);

    $session = uiPropsSession($this->host, $this->tiktok);
    TiktokLiveReport::create([
        'import_id' => $import->id,
        'tiktok_creator_id' => '222',
        'launched_time' => now(),
        'gmv_myr' => 150,
        'matched_live_session_id' => $session->id,
    ]);

    actingAs($this->pic)
        ->get('/livehost/tiktok-imports')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tiktok-imports/Index', false)
            ->has('imports.data', 1)
            ->has('imports.data.0', fn (Assert $row) => $row
                ->where('id', $import->id)
                ->where('report_type', 'live_analysis')
                ->where('status', 'completed')
                ->where('total_rows', 3)
                ->where('matched_rows', 2)
                ->where('unmatched_rows', 1)
                ->where('matched_count', 1)
                ->where('unmatched_count', 1)
                ->has('uploaded_by')
                ->has('uploaded_at')
                ->has('period_start')
                ->has('period_end')
                ->has('file_name')
                ->etc()
            )
        );
});

it('show page for live_analysis includes matched + unmatched reports', function () {
    $import = TiktokReportImport::create([
        'report_type' => 'live_analysis',
        'file_path' => 'tiktok-imports/la.xlsx',
        'uploaded_by' => $this->pic->id,
        'uploaded_at' => now(),
        'period_start' => '2026-04-01',
        'period_end' => '2026-04-30',
        'status' => 'completed',
    ]);

    $session = uiPropsSession($this->host, $this->tiktok, ['gmv_amount' => 600]);

    TiktokLiveReport::create([
        'import_id' => $import->id,
        'tiktok_creator_id' => '9999999999999999999',
        'creator_display_name' => 'UI Host',
        'launched_time' => $session->actual_start_at,
        'duration_seconds' => 3000,
        'gmv_myr' => 750.00,
        'matched_live_session_id' => $session->id,
    ]);

    TiktokLiveReport::create([
        'import_id' => $import->id,
        'tiktok_creator_id' => '0000000000000000000',
        'creator_display_name' => 'Orphan',
        'launched_time' => now(),
        'gmv_myr' => 42.00,
        'matched_live_session_id' => null,
    ]);

    actingAs($this->pic)
        ->get("/livehost/tiktok-imports/{$import->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tiktok-imports/Show', false)
            ->where('import.id', $import->id)
            ->where('import.report_type', 'live_analysis')
            ->has('rows', 2)
            ->has('rows.0', fn (Assert $row) => $row
                ->has('id')
                ->has('tiktok_creator_id')
                ->has('launched_time')
                ->has('gmv_myr')
                ->has('matched_live_session_id')
                ->has('matched_session')
                ->etc()
            )
            ->has('adjustments', 0)
        );
});

it('show page for order_list includes orders + proposed adjustments', function () {
    $import = TiktokReportImport::create([
        'report_type' => 'order_list',
        'file_path' => 'tiktok-imports/ol.xlsx',
        'uploaded_by' => $this->pic->id,
        'uploaded_at' => now(),
        'period_start' => '2026-04-01',
        'period_end' => '2026-04-30',
        'status' => 'completed',
    ]);

    $session = uiPropsSession($this->host, $this->tiktok);

    // Order with a matching session and a proposed adjustment.
    TiktokOrder::create([
        'import_id' => $import->id,
        'tiktok_order_id' => 'ORDER-UI-1',
        'order_status' => 'completed',
        'created_time' => now()->subMinutes(30),
        'order_amount_myr' => 200,
        'order_refund_amount_myr' => 80,
        'matched_live_session_id' => $session->id,
    ]);

    LiveSessionGmvAdjustment::create([
        'live_session_id' => $session->id,
        'amount_myr' => -80,
        'reason' => 'Auto: Order #ORDER-UI-1 refunded/cancelled (RM 80)',
        'status' => 'proposed',
        'adjusted_at' => now(),
    ]);

    // Order without a matching session.
    TiktokOrder::create([
        'import_id' => $import->id,
        'tiktok_order_id' => 'ORDER-UI-2',
        'order_status' => 'completed',
        'created_time' => now()->subMinutes(20),
        'order_amount_myr' => 50,
        'order_refund_amount_myr' => 0,
        'matched_live_session_id' => null,
    ]);

    actingAs($this->pic)
        ->get("/livehost/tiktok-imports/{$import->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tiktok-imports/Show', false)
            ->where('import.report_type', 'order_list')
            ->has('rows', 2)
            ->has('rows.0', fn (Assert $row) => $row
                ->has('id')
                ->has('tiktok_order_id')
                ->has('order_status')
                ->has('order_amount_myr')
                ->has('order_refund_amount_myr')
                ->has('matched_live_session_id')
                ->has('matched_session')
                ->etc()
            )
            ->has('adjustments', 1)
            ->has('adjustments.0', fn (Assert $adj) => $adj
                ->has('id')
                ->where('live_session_id', $session->id)
                ->where('status', 'proposed')
                ->where('amount_myr', -80)
                ->has('reason')
                ->has('session')
                ->etc()
            )
        );
});
