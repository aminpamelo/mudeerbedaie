<?php

use App\Models\LiveHostPayrollItem;
use App\Models\LiveHostPayrollRun;
use App\Models\LiveSession;
use App\Models\PlatformAccount;
use App\Models\User;
use App\Services\LiveHost\Reports\Filters\ReportFilters;
use App\Services\LiveHost\Reports\HostScorecardReport;
use Carbon\CarbonImmutable;

beforeEach(function () {
    CarbonImmutable::setTestNow('2026-04-25 10:00:00');
});

it('aggregates KPI totals for the filter window', function () {
    $host = User::factory()->create(['role' => 'live_host']);
    $account = PlatformAccount::factory()->create();

    LiveSession::factory()->create([
        'live_host_id' => $host->id,
        'platform_account_id' => $account->id,
        'status' => 'ended',
        'scheduled_start_at' => '2026-04-10 08:00:00',
        'actual_start_at' => '2026-04-10 08:00:00',
        'actual_end_at' => '2026-04-10 10:00:00',
        'duration_minutes' => 120,
        'gmv_amount' => 500.00,
        'gmv_adjustment' => 0,
    ]);

    LiveSession::factory()->create([
        'live_host_id' => $host->id,
        'platform_account_id' => $account->id,
        'status' => 'missed',
        'scheduled_start_at' => '2026-04-12 08:00:00',
        'duration_minutes' => 0,
        'gmv_amount' => 0,
    ]);

    // Out-of-window session — must not be counted.
    LiveSession::factory()->create([
        'live_host_id' => $host->id,
        'platform_account_id' => $account->id,
        'status' => 'ended',
        'scheduled_start_at' => '2026-03-30 08:00:00',
        'duration_minutes' => 60,
        'gmv_amount' => 999.00,
    ]);

    $run = LiveHostPayrollRun::create([
        'period_start' => '2026-04-01',
        'period_end' => '2026-04-30',
        'cutoff_date' => '2026-04-30',
        'status' => 'draft',
    ]);
    LiveHostPayrollItem::create([
        'payroll_run_id' => $run->id,
        'user_id' => $host->id,
        'base_salary_myr' => 0,
        'sessions_count' => 1,
        'total_per_live_myr' => 0,
        'total_gmv_myr' => 500,
        'total_gmv_adjustment_myr' => 0,
        'net_gmv_myr' => 500,
        'gmv_commission_myr' => 80,
        'override_l1_myr' => 0,
        'override_l2_myr' => 0,
        'gross_total_myr' => 80.00,
        'deductions_myr' => 0,
        'net_payout_myr' => 80,
    ]);

    $filters = new ReportFilters(
        CarbonImmutable::parse('2026-04-01'),
        CarbonImmutable::parse('2026-04-25'),
    );
    $report = (new HostScorecardReport)->run($filters);

    expect($report->kpis['totalHours'])->toBe(2.0)
        ->and($report->kpis['totalGmv'])->toEqualWithDelta(500.00, 0.01)
        ->and($report->kpis['totalCommission'])->toEqualWithDelta(80.00, 0.01)
        ->and($report->kpis['attendanceRate'])->toEqualWithDelta(0.5, 0.001);
});

it('returns zeroed KPIs when no sessions exist in window', function () {
    $filters = new ReportFilters(
        CarbonImmutable::parse('2026-04-01'),
        CarbonImmutable::parse('2026-04-25'),
    );

    $report = (new HostScorecardReport)->run($filters);

    expect($report->kpis['totalHours'])->toBe(0.0)
        ->and($report->kpis['totalGmv'])->toBe(0.0)
        ->and($report->kpis['totalCommission'])->toBe(0.0)
        ->and($report->kpis['attendanceRate'])->toBe(0.0);
});

it('respects host filter', function () {
    $h1 = User::factory()->create(['role' => 'live_host']);
    $h2 = User::factory()->create(['role' => 'live_host']);
    $account = PlatformAccount::factory()->create();

    foreach ([$h1, $h2] as $h) {
        LiveSession::factory()->create([
            'live_host_id' => $h->id,
            'platform_account_id' => $account->id,
            'status' => 'ended',
            'scheduled_start_at' => '2026-04-10 08:00:00',
            'duration_minutes' => 60,
            'gmv_amount' => 100.00,
        ]);
    }

    $filters = new ReportFilters(
        CarbonImmutable::parse('2026-04-01'),
        CarbonImmutable::parse('2026-04-25'),
        hostIds: [$h1->id],
    );

    $report = (new HostScorecardReport)->run($filters);

    expect($report->kpis['totalGmv'])->toEqualWithDelta(100.00, 0.01);
});

it('produces per-host rows with all metrics', function () {
    $host = User::factory()->create(['role' => 'live_host', 'name' => 'Sarah Chen']);
    $account = PlatformAccount::factory()->create();

    LiveSession::factory()->count(2)->create([
        'live_host_id' => $host->id,
        'platform_account_id' => $account->id,
        'status' => 'ended',
        'scheduled_start_at' => '2026-04-10 08:00:00',
        'actual_start_at' => '2026-04-10 08:00:00',
        'duration_minutes' => 60,
        'gmv_amount' => 200.00,
    ]);

    LiveSession::factory()->create([
        'live_host_id' => $host->id,
        'platform_account_id' => $account->id,
        'status' => 'missed',
        'scheduled_start_at' => '2026-04-15 08:00:00',
        'duration_minutes' => 0,
    ]);

    $filters = new ReportFilters(
        CarbonImmutable::parse('2026-04-01'),
        CarbonImmutable::parse('2026-04-25'),
    );

    $rows = (new HostScorecardReport)->run($filters)->rows;

    expect($rows)->toHaveCount(1);
    $row = $rows[0];
    expect($row['hostId'])->toBe($host->id)
        ->and($row['hostName'])->toBe('Sarah Chen')
        ->and($row['sessionsScheduled'])->toBe(3)
        ->and($row['sessionsEnded'])->toBe(2)
        ->and($row['hoursLive'])->toBe(2.0)
        ->and($row['gmv'])->toEqualWithDelta(400.00, 0.01)
        ->and($row['noShows'])->toBe(1)
        ->and($row['attendanceRate'])->toEqualWithDelta(2 / 3, 0.001);
});

it('produces a daily trend bucket for ended/missed', function () {
    $host = User::factory()->create(['role' => 'live_host']);
    $account = PlatformAccount::factory()->create();

    LiveSession::factory()->create([
        'live_host_id' => $host->id,
        'platform_account_id' => $account->id,
        'status' => 'ended',
        'scheduled_start_at' => '2026-04-10 08:00:00',
        'duration_minutes' => 60,
    ]);
    LiveSession::factory()->create([
        'live_host_id' => $host->id,
        'platform_account_id' => $account->id,
        'status' => 'missed',
        'scheduled_start_at' => '2026-04-10 14:00:00',
        'duration_minutes' => 0,
    ]);

    $filters = new ReportFilters(
        CarbonImmutable::parse('2026-04-10'),
        CarbonImmutable::parse('2026-04-10'),
    );

    $trend = (new HostScorecardReport)->run($filters)->trend;

    expect($trend)->toHaveCount(1)
        ->and($trend[0]['date'])->toBe('2026-04-10')
        ->and($trend[0]['ended'])->toBe(1)
        ->and($trend[0]['missed'])->toBe(1);
});
