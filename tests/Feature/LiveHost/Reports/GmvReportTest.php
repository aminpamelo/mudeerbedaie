<?php

use App\Models\LiveSession;
use App\Models\PlatformAccount;
use App\Models\User;
use App\Services\LiveHost\Reports\Filters\ReportFilters;
use App\Services\LiveHost\Reports\GmvReport;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    CarbonImmutable::setTestNow('2026-04-25 10:00:00');
});

it('aggregates totalGmv and gmvPerSession over the window', function () {
    $host = User::factory()->create(['role' => 'live_host']);
    $account = PlatformAccount::factory()->create();

    LiveSession::factory()->create([
        'live_host_id' => $host->id,
        'platform_account_id' => $account->id,
        'status' => 'ended',
        'scheduled_start_at' => '2026-04-10 08:00:00',
        'gmv_amount' => 500,
    ]);
    LiveSession::factory()->create([
        'live_host_id' => $host->id,
        'platform_account_id' => $account->id,
        'status' => 'ended',
        'scheduled_start_at' => '2026-04-12 08:00:00',
        'gmv_amount' => 1000,
    ]);
    // out-of-window
    LiveSession::factory()->create([
        'live_host_id' => $host->id,
        'platform_account_id' => $account->id,
        'status' => 'ended',
        'scheduled_start_at' => '2026-03-31 08:00:00',
        'gmv_amount' => 9999,
    ]);

    $filters = new ReportFilters(
        CarbonImmutable::parse('2026-04-01'),
        CarbonImmutable::parse('2026-04-25'),
    );

    $result = (new GmvReport)->run($filters);

    expect($result->kpis['totalGmv'])->toEqualWithDelta(1500.00, 0.01)
        ->and($result->kpis['gmvPerSession'])->toEqualWithDelta(750.00, 0.01);
});

it('identifies top account and top host by GMV', function () {
    $h1 = User::factory()->create(['role' => 'live_host', 'name' => 'H1']);
    $h2 = User::factory()->create(['role' => 'live_host', 'name' => 'H2']);
    $a1 = PlatformAccount::factory()->create(['name' => 'A1']);
    $a2 = PlatformAccount::factory()->create(['name' => 'A2']);

    LiveSession::factory()->create(['live_host_id' => $h1->id, 'platform_account_id' => $a1->id, 'status' => 'ended', 'scheduled_start_at' => '2026-04-10', 'gmv_amount' => 100]);
    LiveSession::factory()->create(['live_host_id' => $h2->id, 'platform_account_id' => $a1->id, 'status' => 'ended', 'scheduled_start_at' => '2026-04-11', 'gmv_amount' => 800]);
    LiveSession::factory()->create(['live_host_id' => $h2->id, 'platform_account_id' => $a2->id, 'status' => 'ended', 'scheduled_start_at' => '2026-04-12', 'gmv_amount' => 50]);

    $filters = new ReportFilters(CarbonImmutable::parse('2026-04-01'), CarbonImmutable::parse('2026-04-25'));
    $result = (new GmvReport)->run($filters);

    expect($result->kpis['topAccountId'])->toBe($a1->id)
        ->and($result->kpis['topAccountGmv'])->toEqualWithDelta(900.00, 0.01)
        ->and($result->kpis['topHostId'])->toBe($h2->id)
        ->and($result->kpis['topHostGmv'])->toEqualWithDelta(850.00, 0.01);
});

it('produces daily trend split by account', function () {
    $host = User::factory()->create(['role' => 'live_host']);
    $a1 = PlatformAccount::factory()->create();
    $a2 = PlatformAccount::factory()->create();

    LiveSession::factory()->create(['live_host_id' => $host->id, 'platform_account_id' => $a1->id, 'status' => 'ended', 'scheduled_start_at' => '2026-04-10 08:00', 'gmv_amount' => 200]);
    LiveSession::factory()->create(['live_host_id' => $host->id, 'platform_account_id' => $a2->id, 'status' => 'ended', 'scheduled_start_at' => '2026-04-10 12:00', 'gmv_amount' => 300]);
    LiveSession::factory()->create(['live_host_id' => $host->id, 'platform_account_id' => $a1->id, 'status' => 'ended', 'scheduled_start_at' => '2026-04-11 08:00', 'gmv_amount' => 50]);

    $filters = new ReportFilters(CarbonImmutable::parse('2026-04-10'), CarbonImmutable::parse('2026-04-11'));
    $result = (new GmvReport)->run($filters);

    expect($result->trendByAccount)->toHaveCount(2);
    $byDate = collect($result->trendByAccount)->keyBy('date');
    expect($byDate['2026-04-10']['series'][$a1->id] ?? null)->toEqualWithDelta(200, 0.01)
        ->and($byDate['2026-04-10']['series'][$a2->id] ?? null)->toEqualWithDelta(300, 0.01)
        ->and($byDate['2026-04-11']['series'][$a1->id] ?? null)->toEqualWithDelta(50, 0.01)
        ->and($byDate['2026-04-11']['series'][$a2->id] ?? null)->toBeNull();
});

it('returns top sessions ordered by GMV desc, capped at 10', function () {
    $host = User::factory()->create(['role' => 'live_host']);
    $account = PlatformAccount::factory()->create();
    for ($i = 1; $i <= 12; $i++) {
        LiveSession::factory()->create([
            'live_host_id' => $host->id,
            'platform_account_id' => $account->id,
            'status' => 'ended',
            'scheduled_start_at' => '2026-04-10 08:00:00',
            'gmv_amount' => $i * 100,
        ]);
    }

    $filters = new ReportFilters(CarbonImmutable::parse('2026-04-01'), CarbonImmutable::parse('2026-04-25'));
    $result = (new GmvReport)->run($filters);

    expect($result->topSessions)->toHaveCount(10)
        ->and($result->topSessions[0]['gmv'])->toEqualWithDelta(1200, 0.01)
        ->and($result->topSessions[9]['gmv'])->toEqualWithDelta(300, 0.01);
});

it('respects host and account filters', function () {
    $h1 = User::factory()->create(['role' => 'live_host']);
    $h2 = User::factory()->create(['role' => 'live_host']);
    $account = PlatformAccount::factory()->create();
    foreach ([$h1, $h2] as $h) {
        LiveSession::factory()->create([
            'live_host_id' => $h->id,
            'platform_account_id' => $account->id,
            'status' => 'ended',
            'scheduled_start_at' => '2026-04-10 08:00:00',
            'gmv_amount' => 500,
        ]);
    }

    $filters = new ReportFilters(
        CarbonImmutable::parse('2026-04-01'),
        CarbonImmutable::parse('2026-04-25'),
        hostIds: [$h1->id],
    );
    $result = (new GmvReport)->run($filters);

    expect($result->kpis['totalGmv'])->toEqualWithDelta(500.00, 0.01)
        ->and($result->hostRows)->toHaveCount(1);
});

it('returns zeroed result when no ended sessions exist', function () {
    $filters = new ReportFilters(CarbonImmutable::parse('2026-04-01'), CarbonImmutable::parse('2026-04-25'));
    $result = (new GmvReport)->run($filters);

    expect($result->kpis['totalGmv'])->toBe(0.0)
        ->and($result->kpis['topAccountId'])->toBeNull()
        ->and($result->kpis['topHostId'])->toBeNull()
        ->and($result->trendByAccount)->toBe([])
        ->and($result->hostRows)->toBe([])
        ->and($result->topSessions)->toBe([]);
});

it('runs in a bounded number of queries', function () {
    $accounts = PlatformAccount::factory()->count(3)->create();
    $hosts = collect();
    for ($i = 0; $i < 20; $i++) {
        $hosts->push(User::factory()->create(['role' => 'live_host']));
    }
    foreach ($hosts as $host) {
        LiveSession::factory()->count(10)->create([
            'live_host_id' => $host->id,
            'platform_account_id' => $accounts->random()->id,
            'status' => fake()->randomElement(['ended', 'missed', 'cancelled']),
            'scheduled_start_at' => fake()->dateTimeBetween('2026-04-01', '2026-04-25'),
            'gmv_amount' => fake()->randomFloat(2, 0, 500),
        ]);
    }

    DB::enableQueryLog();
    (new GmvReport)->run(new ReportFilters(
        CarbonImmutable::parse('2026-04-01'),
        CarbonImmutable::parse('2026-04-25'),
    ));
    $count = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($count)->toBeLessThanOrEqual(5);
});
