<?php

use App\Models\LiveScheduleAssignment;
use App\Models\LiveSession;
use App\Models\PlatformAccount;
use App\Models\SessionReplacementRequest;
use App\Models\User;
use App\Services\LiveHost\Reports\CoverageReport;
use App\Services\LiveHost\Reports\Filters\ReportFilters;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

beforeEach(fn () => CarbonImmutable::setTestNow('2026-04-25 10:00:00'));

it('classifies the four buckets correctly', function () {
    $h1 = User::factory()->create(['role' => 'live_host']);
    $h2 = User::factory()->create(['role' => 'live_host']);
    $account = PlatformAccount::factory()->create();

    // 1. unassigned slot
    LiveScheduleAssignment::factory()->create([
        'platform_account_id' => $account->id,
        'is_template' => false,
        'schedule_date' => '2026-04-07',
        'live_host_id' => null,
    ]);

    // 2. assigned slot — observer creates a 'scheduled' session
    LiveScheduleAssignment::factory()->create([
        'platform_account_id' => $account->id,
        'is_template' => false,
        'schedule_date' => '2026-04-08',
        'live_host_id' => $h1->id,
    ]);

    // 3. missed slot — slot exists, session must be flipped to 'missed'
    $missedSlot = LiveScheduleAssignment::factory()->create([
        'platform_account_id' => $account->id,
        'is_template' => false,
        'schedule_date' => '2026-04-09',
        'live_host_id' => $h1->id,
    ]);
    LiveSession::query()
        ->where('live_schedule_assignment_id', $missedSlot->id)
        ->update(['status' => 'missed']);

    // 4. replaced slot — has an 'assigned' replacement request
    $replacedSlot = LiveScheduleAssignment::factory()->create([
        'platform_account_id' => $account->id,
        'is_template' => false,
        'schedule_date' => '2026-04-10',
        'live_host_id' => $h1->id,
    ]);
    SessionReplacementRequest::factory()->create([
        'live_schedule_assignment_id' => $replacedSlot->id,
        'original_host_id' => $h1->id,
        'replacement_host_id' => $h2->id,
        'scope' => 'one_date',
        'target_date' => '2026-04-10',
        'reason_category' => 'sick',
        'status' => 'assigned',
        'requested_at' => '2026-04-09 09:00:00',
        'assigned_at' => '2026-04-09 10:00:00',
    ]);

    $filters = new ReportFilters(
        CarbonImmutable::parse('2026-04-01'),
        CarbonImmutable::parse('2026-04-30'),
    );
    $result = (new CoverageReport)->run($filters);

    expect($result->kpis['totalSlots'])->toBe(4)
        ->and($result->kpis['unassignedCount'])->toBe(1)
        ->and($result->kpis['replacedCount'])->toBe(1)
        ->and($result->kpis['noShowRate'])->toEqualWithDelta(0.25, 0.01)        // 1 of 4 = missed
        ->and($result->kpis['percentFilled'])->toEqualWithDelta(0.50, 0.01);    // (assigned 1 + replaced 1) / 4
});

it('groups weekly trend by Monday', function () {
    $host = User::factory()->create(['role' => 'live_host']);
    $account = PlatformAccount::factory()->create();

    // April 6 2026 was a Monday. April 13 also a Monday.
    LiveScheduleAssignment::factory()->create([
        'platform_account_id' => $account->id,
        'is_template' => false,
        'schedule_date' => '2026-04-08', // Wed of week starting Apr 6
        'live_host_id' => $host->id,
    ]);
    LiveScheduleAssignment::factory()->create([
        'platform_account_id' => $account->id,
        'is_template' => false,
        'schedule_date' => '2026-04-15', // Wed of week starting Apr 13
        'live_host_id' => $host->id,
    ]);

    $filters = new ReportFilters(CarbonImmutable::parse('2026-04-01'), CarbonImmutable::parse('2026-04-30'));
    $result = (new CoverageReport)->run($filters);

    expect($result->weeklyTrend)->toHaveCount(2);
    $byWeek = collect($result->weeklyTrend)->keyBy('weekStart');
    expect($byWeek)->toHaveKey('2026-04-06')
        ->and($byWeek)->toHaveKey('2026-04-13')
        ->and($byWeek['2026-04-06']['assigned'])->toBe(1)
        ->and($byWeek['2026-04-13']['assigned'])->toBe(1);
});

it('produces account rows with correct coverage rate', function () {
    $host = User::factory()->create(['role' => 'live_host']);
    $a1 = PlatformAccount::factory()->create(['name' => 'Acc Alpha']);
    $a2 = PlatformAccount::factory()->create(['name' => 'Acc Beta']);

    // a1: 2 slots, both assigned
    foreach (['2026-04-08', '2026-04-09'] as $date) {
        LiveScheduleAssignment::factory()->create([
            'platform_account_id' => $a1->id,
            'is_template' => false,
            'schedule_date' => $date,
            'live_host_id' => $host->id,
        ]);
    }
    // a2: 2 slots, 1 unassigned + 1 assigned
    LiveScheduleAssignment::factory()->create([
        'platform_account_id' => $a2->id,
        'is_template' => false,
        'schedule_date' => '2026-04-10',
        'live_host_id' => null,
    ]);
    LiveScheduleAssignment::factory()->create([
        'platform_account_id' => $a2->id,
        'is_template' => false,
        'schedule_date' => '2026-04-11',
        'live_host_id' => $host->id,
    ]);

    $filters = new ReportFilters(CarbonImmutable::parse('2026-04-01'), CarbonImmutable::parse('2026-04-30'));
    $result = (new CoverageReport)->run($filters);

    expect($result->accountRows)->toHaveCount(2);
    $byName = collect($result->accountRows)->keyBy('name');
    expect($byName['Acc Alpha']['totalSlots'])->toBe(2)
        ->and($byName['Acc Alpha']['coverageRate'])->toBe(1.0)
        ->and($byName['Acc Beta']['totalSlots'])->toBe(2)
        ->and($byName['Acc Beta']['coverageRate'])->toBe(0.5)
        ->and($byName['Acc Beta']['unassigned'])->toBe(1);
});

it('respects host filter', function () {
    $h1 = User::factory()->create(['role' => 'live_host']);
    $h2 = User::factory()->create(['role' => 'live_host']);
    $account = PlatformAccount::factory()->create();

    foreach ([$h1, $h2] as $host) {
        LiveScheduleAssignment::factory()->create([
            'platform_account_id' => $account->id,
            'is_template' => false,
            'schedule_date' => '2026-04-10',
            'live_host_id' => $host->id,
        ]);
    }

    $filters = new ReportFilters(
        CarbonImmutable::parse('2026-04-01'),
        CarbonImmutable::parse('2026-04-30'),
        hostIds: [$h1->id],
    );
    $result = (new CoverageReport)->run($filters);

    expect($result->kpis['totalSlots'])->toBe(1);
});

it('returns zeroed result when no slots exist', function () {
    $filters = new ReportFilters(CarbonImmutable::parse('2026-04-01'), CarbonImmutable::parse('2026-04-25'));
    $result = (new CoverageReport)->run($filters);

    expect($result->kpis['totalSlots'])->toBe(0)
        ->and($result->kpis['percentFilled'])->toBe(0.0)
        ->and($result->kpis['noShowRate'])->toBe(0.0)
        ->and($result->weeklyTrend)->toBe([])
        ->and($result->accountRows)->toBe([]);
});

it('runs in a bounded number of queries', function () {
    $accounts = PlatformAccount::factory()->count(3)->create();
    $hosts = collect();
    for ($i = 0; $i < 10; $i++) {
        $hosts->push(User::factory()->create(['role' => 'live_host']));
    }
    foreach ($hosts as $host) {
        for ($i = 0; $i < 10; $i++) {
            LiveScheduleAssignment::factory()->create([
                'platform_account_id' => $accounts->random()->id,
                'is_template' => false,
                'schedule_date' => fake()->dateTimeBetween('2026-04-01', '2026-04-25')->format('Y-m-d'),
                'live_host_id' => $host->id,
            ]);
        }
    }

    DB::enableQueryLog();
    (new CoverageReport)->run(new ReportFilters(
        CarbonImmutable::parse('2026-04-01'),
        CarbonImmutable::parse('2026-04-25'),
    ));
    $count = count(DB::getQueryLog());
    DB::disableQueryLog();

    // 2 main queries + Carbon parsing has none. Bound ≤ 5 keeps headroom for refactors.
    expect($count)->toBeLessThanOrEqual(5);
});
