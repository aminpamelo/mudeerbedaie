<?php

use App\Models\LiveHostCommissionProfile;
use App\Models\LiveHostPayrollItem;
use App\Models\LiveHostPayrollRun;
use App\Models\LiveHostPlatformCommissionRate;
use App\Models\LiveSession;
use App\Models\LiveSessionGmvAdjustment;
use App\Models\Platform;
use App\Models\PlatformAccount;
use App\Models\TiktokLiveReport;
use App\Models\TiktokOrder;
use App\Models\TiktokReportImport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Model fillable / cast tests (one per new model)
|--------------------------------------------------------------------------
*/

it('LiveHostCommissionProfile model has expected fillable and casts', function () {
    $user = User::factory()->create();

    $profile = LiveHostCommissionProfile::create([
        'user_id' => $user->id,
        'base_salary_myr' => 2000,
        'per_live_rate_myr' => 30,
        'override_rate_l1_percent' => 10,
        'override_rate_l2_percent' => 5,
        'effective_from' => now(),
        'is_active' => true,
        'notes' => 'initial',
    ]);

    $profile->refresh();

    expect($profile->base_salary_myr)->toEqual('2000.00');
    expect($profile->per_live_rate_myr)->toEqual('30.00');
    expect($profile->override_rate_l1_percent)->toEqual('10.00');
    expect($profile->override_rate_l2_percent)->toEqual('5.00');
    expect($profile->is_active)->toBeTrue();
    expect($profile->effective_from)->toBeInstanceOf(Carbon\Carbon::class);
    expect($profile->user->id)->toBe($user->id);
});

it('LiveHostPlatformCommissionRate model has expected fillable and casts', function () {
    $user = User::factory()->create();
    $platform = Platform::factory()->create();

    $rate = LiveHostPlatformCommissionRate::create([
        'user_id' => $user->id,
        'platform_id' => $platform->id,
        'commission_rate_percent' => 7.5,
        'effective_from' => now(),
        'is_active' => true,
    ]);

    $rate->refresh();

    expect($rate->commission_rate_percent)->toEqual('7.50');
    expect($rate->is_active)->toBeTrue();
    expect($rate->effective_from)->toBeInstanceOf(Carbon\Carbon::class);
    expect($rate->user->id)->toBe($user->id);
    expect($rate->platform->id)->toBe($platform->id);
});

it('LiveSessionGmvAdjustment model has expected fillable and casts', function () {
    $user = User::factory()->create();
    $session = makeLiveSession();

    $adjustment = LiveSessionGmvAdjustment::create([
        'live_session_id' => $session->id,
        'amount_myr' => -50.25,
        'reason' => 'Returned item',
        'adjusted_by' => $user->id,
        'adjusted_at' => now(),
    ]);

    $adjustment->refresh();

    expect($adjustment->amount_myr)->toEqual('-50.25');
    expect($adjustment->adjusted_at)->toBeInstanceOf(Carbon\Carbon::class);
    expect($adjustment->liveSession->id)->toBe($session->id);
    expect($adjustment->adjustedBy->id)->toBe($user->id);
});

it('LiveHostPayrollRun model has expected fillable and casts', function () {
    $user = User::factory()->create();

    $run = LiveHostPayrollRun::create([
        'period_start' => '2026-04-01',
        'period_end' => '2026-04-15',
        'cutoff_date' => '2026-04-16',
        'status' => 'draft',
        'locked_at' => now(),
        'locked_by' => $user->id,
        'notes' => 'Q1 run',
    ]);

    $run->refresh();

    expect($run->period_start)->toBeInstanceOf(Carbon\Carbon::class);
    expect($run->period_end)->toBeInstanceOf(Carbon\Carbon::class);
    expect($run->cutoff_date)->toBeInstanceOf(Carbon\Carbon::class);
    expect($run->locked_at)->toBeInstanceOf(Carbon\Carbon::class);
    expect($run->status)->toBe('draft');
});

it('LiveHostPayrollItem model has expected fillable and casts', function () {
    $user = User::factory()->create();
    $run = LiveHostPayrollRun::create([
        'period_start' => '2026-04-01',
        'period_end' => '2026-04-15',
        'cutoff_date' => '2026-04-16',
        'status' => 'draft',
    ]);

    $item = LiveHostPayrollItem::create([
        'payroll_run_id' => $run->id,
        'user_id' => $user->id,
        'base_salary_myr' => 2000,
        'sessions_count' => 10,
        'total_per_live_myr' => 300,
        'total_gmv_myr' => 5000.55,
        'net_gmv_myr' => 4800.55,
        'gmv_commission_myr' => 250,
        'gross_total_myr' => 2550,
        'deductions_myr' => 0,
        'net_payout_myr' => 2550,
        'calculation_breakdown_json' => ['note' => 'ok', 'factor' => 1.5],
    ]);

    $item->refresh();

    expect($item->base_salary_myr)->toEqual('2000.00');
    expect($item->total_gmv_myr)->toEqual('5000.55');
    expect($item->calculation_breakdown_json)->toBe(['note' => 'ok', 'factor' => 1.5]);
    expect($item->payrollRun->id)->toBe($run->id);
    expect($item->user->id)->toBe($user->id);
});

it('TiktokReportImport model has expected fillable and casts', function () {
    $user = User::factory()->create();

    $import = TiktokReportImport::create([
        'report_type' => 'live_analysis',
        'file_path' => 'imports/live.xlsx',
        'uploaded_by' => $user->id,
        'uploaded_at' => now(),
        'period_start' => '2026-04-01',
        'period_end' => '2026-04-15',
        'status' => 'pending',
        'total_rows' => 0,
        'matched_rows' => 0,
        'unmatched_rows' => 0,
        'error_log_json' => ['warnings' => []],
    ]);

    $import->refresh();

    expect($import->uploaded_at)->toBeInstanceOf(Carbon\Carbon::class);
    expect($import->period_start)->toBeInstanceOf(Carbon\Carbon::class);
    expect($import->error_log_json)->toBe(['warnings' => []]);
    expect($import->uploadedBy->id)->toBe($user->id);
});

it('TiktokLiveReport model has expected fillable and casts', function () {
    $user = User::factory()->create();
    $import = TiktokReportImport::create([
        'report_type' => 'live_analysis',
        'file_path' => 'imports/live.xlsx',
        'uploaded_by' => $user->id,
        'uploaded_at' => now(),
        'period_start' => '2026-04-01',
        'period_end' => '2026-04-15',
    ]);

    $report = TiktokLiveReport::create([
        'import_id' => $import->id,
        'tiktok_creator_id' => 'tt_123',
        'creator_nickname' => 'Creator One',
        'launched_time' => now(),
        'duration_seconds' => 3600,
        'gmv_myr' => 1200.50,
        'live_attributed_gmv_myr' => 1000.50,
        'avg_price_myr' => 25.25,
        'click_to_order_rate' => 3.50,
        'ctr' => 2.25,
        'raw_row_json' => ['col_a' => 'value'],
    ]);

    $report->refresh();

    expect($report->launched_time)->toBeInstanceOf(Carbon\Carbon::class);
    expect($report->gmv_myr)->toEqual('1200.50');
    expect($report->click_to_order_rate)->toEqual('3.50');
    expect($report->ctr)->toEqual('2.25');
    expect($report->raw_row_json)->toBe(['col_a' => 'value']);
    expect($report->import->id)->toBe($import->id);
});

it('TiktokOrder model has expected fillable and casts', function () {
    $user = User::factory()->create();
    $import = TiktokReportImport::create([
        'report_type' => 'orders',
        'file_path' => 'imports/orders.xlsx',
        'uploaded_by' => $user->id,
        'uploaded_at' => now(),
        'period_start' => '2026-04-01',
        'period_end' => '2026-04-15',
    ]);

    $order = TiktokOrder::create([
        'import_id' => $import->id,
        'tiktok_order_id' => 'TT-ORD-001',
        'order_status' => 'completed',
        'created_time' => now(),
        'paid_time' => now(),
        'order_amount_myr' => 150.75,
        'order_refund_amount_myr' => 0,
        'raw_row_json' => ['status' => 'ok'],
    ]);

    $order->refresh();

    expect($order->created_time)->toBeInstanceOf(Carbon\Carbon::class);
    expect($order->paid_time)->toBeInstanceOf(Carbon\Carbon::class);
    expect($order->order_amount_myr)->toEqual('150.75');
    expect($order->raw_row_json)->toBe(['status' => 'ok']);
    expect($order->import->id)->toBe($import->id);
});

/*
|--------------------------------------------------------------------------
| Relationship tests
|--------------------------------------------------------------------------
*/

it('User commissionProfile returns the active profile', function () {
    $user = User::factory()->create();

    LiveHostCommissionProfile::create([
        'user_id' => $user->id,
        'base_salary_myr' => 2500,
        'per_live_rate_myr' => 40,
        'effective_from' => now(),
        'is_active' => true,
    ]);

    LiveHostCommissionProfile::create([
        'user_id' => $user->id,
        'base_salary_myr' => 1500,
        'per_live_rate_myr' => 20,
        'effective_from' => now()->subYear(),
        'effective_to' => now()->subDay(),
        'is_active' => false,
    ]);

    expect($user->refresh()->commissionProfile->base_salary_myr)->toEqual('2500.00');
});

it('User platformCommissionRates returns only active rates', function () {
    $user = User::factory()->create();
    $platformA = Platform::factory()->create();
    $platformB = Platform::factory()->create();

    LiveHostPlatformCommissionRate::create([
        'user_id' => $user->id,
        'platform_id' => $platformA->id,
        'commission_rate_percent' => 10,
        'effective_from' => now(),
        'is_active' => true,
    ]);

    LiveHostPlatformCommissionRate::create([
        'user_id' => $user->id,
        'platform_id' => $platformB->id,
        'commission_rate_percent' => 5,
        'effective_from' => now()->subYear(),
        'effective_to' => now()->subDay(),
        'is_active' => false,
    ]);

    $activeRates = $user->refresh()->platformCommissionRates;
    expect($activeRates)->toHaveCount(1);
    expect($activeRates->first()->platform_id)->toBe($platformA->id);
});

it('User upline accessor returns upline user from profile', function () {
    $ahmad = User::factory()->create();
    $sarah = User::factory()->create();

    LiveHostCommissionProfile::create([
        'user_id' => $ahmad->id,
        'base_salary_myr' => 0,
        'per_live_rate_myr' => 0,
        'effective_from' => now(),
        'is_active' => true,
    ]);

    LiveHostCommissionProfile::create([
        'user_id' => $sarah->id,
        'upline_user_id' => $ahmad->id,
        'base_salary_myr' => 0,
        'per_live_rate_myr' => 0,
        'effective_from' => now(),
        'is_active' => true,
    ]);

    expect($sarah->refresh()->upline->id)->toBe($ahmad->id);
    expect($ahmad->refresh()->upline)->toBeNull();
});

it('User directDownlines returns hosts with this user as upline', function () {
    $ahmad = User::factory()->create();
    $sarah = User::factory()->create();
    $amin = User::factory()->create();

    LiveHostCommissionProfile::create([
        'user_id' => $ahmad->id,
        'base_salary_myr' => 0,
        'per_live_rate_myr' => 0,
        'effective_from' => now(),
        'is_active' => true,
    ]);
    LiveHostCommissionProfile::create([
        'user_id' => $sarah->id,
        'upline_user_id' => $ahmad->id,
        'base_salary_myr' => 0,
        'per_live_rate_myr' => 0,
        'effective_from' => now(),
        'is_active' => true,
    ]);
    LiveHostCommissionProfile::create([
        'user_id' => $amin->id,
        'upline_user_id' => $sarah->id,
        'base_salary_myr' => 0,
        'per_live_rate_myr' => 0,
        'effective_from' => now(),
        'is_active' => true,
    ]);

    $ahmadDirect = $ahmad->directDownlines()->get()->pluck('id')->all();
    expect($ahmadDirect)->toBe([$sarah->id]);
});

it('User l2Downlines returns hosts 2 levels below', function () {
    $ahmad = User::factory()->create();
    $sarah = User::factory()->create();
    $amin = User::factory()->create();

    LiveHostCommissionProfile::create([
        'user_id' => $ahmad->id,
        'base_salary_myr' => 0,
        'per_live_rate_myr' => 0,
        'effective_from' => now(),
        'is_active' => true,
    ]);
    LiveHostCommissionProfile::create([
        'user_id' => $sarah->id,
        'upline_user_id' => $ahmad->id,
        'base_salary_myr' => 0,
        'per_live_rate_myr' => 0,
        'effective_from' => now(),
        'is_active' => true,
    ]);
    LiveHostCommissionProfile::create([
        'user_id' => $amin->id,
        'upline_user_id' => $sarah->id,
        'base_salary_myr' => 0,
        'per_live_rate_myr' => 0,
        'effective_from' => now(),
        'is_active' => true,
    ]);

    $l2 = $ahmad->l2Downlines()->get()->pluck('id')->all();
    expect($l2)->toBe([$amin->id]);

    // A host with no direct downlines returns no L2 downlines.
    $sarah->refresh();
    $sarahL2 = $sarah->l2Downlines()->get()->pluck('id')->all();
    expect($sarahL2)->toBe([]);
});

it('LiveSession gmvAdjustments relationship works', function () {
    $user = User::factory()->create();
    $session = makeLiveSession();

    LiveSessionGmvAdjustment::create([
        'live_session_id' => $session->id,
        'amount_myr' => 100,
        'reason' => 'Return A',
        'adjusted_by' => $user->id,
        'adjusted_at' => now(),
    ]);
    LiveSessionGmvAdjustment::create([
        'live_session_id' => $session->id,
        'amount_myr' => -25,
        'reason' => 'Return B',
        'adjusted_by' => $user->id,
        'adjusted_at' => now(),
    ]);

    expect($session->refresh()->gmvAdjustments)->toHaveCount(2);
});

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

function makeLiveSession(): LiveSession
{
    $platformAccount = PlatformAccount::factory()->create();

    return LiveSession::create([
        'platform_account_id' => $platformAccount->id,
        'title' => 'Test session',
        'status' => 'scheduled',
        'scheduled_start_at' => now()->addHour(),
    ]);
}
