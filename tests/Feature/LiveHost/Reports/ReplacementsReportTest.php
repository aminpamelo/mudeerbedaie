<?php

use App\Models\LiveScheduleAssignment;
use App\Models\PlatformAccount;
use App\Models\SessionReplacementRequest;
use App\Models\User;
use App\Services\LiveHost\Reports\Filters\ReportFilters;
use App\Services\LiveHost\Reports\ReplacementsReport;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

beforeEach(fn () => CarbonImmutable::setTestNow('2026-04-25 10:00:00'));

function makeReplacement(array $overrides = []): SessionReplacementRequest
{
    $base = [
        'scope' => 'one_date',
        'reason_category' => 'sick',
        'status' => 'pending',
        'requested_at' => '2026-04-10 09:00:00',
        'target_date' => '2026-04-10',
        'expires_at' => '2026-04-10 14:00:00',
    ];

    return SessionReplacementRequest::factory()->create(array_merge($base, $overrides));
}

it('counts total, fulfilled, and expired in window', function () {
    $h1 = User::factory()->create(['role' => 'live_host']);
    $h2 = User::factory()->create(['role' => 'live_host']);
    $account = PlatformAccount::factory()->create();
    $slot = LiveScheduleAssignment::factory()->create([
        'platform_account_id' => $account->id,
        'is_template' => false,
        'schedule_date' => '2026-04-10',
        'live_host_id' => $h1->id,
    ]);

    makeReplacement([
        'live_schedule_assignment_id' => $slot->id,
        'original_host_id' => $h1->id,
        'replacement_host_id' => $h2->id,
        'status' => 'assigned',
        'requested_at' => '2026-04-10 09:00:00',
        'assigned_at' => '2026-04-10 09:30:00',
    ]);
    makeReplacement([
        'live_schedule_assignment_id' => $slot->id,
        'original_host_id' => $h1->id,
        'status' => 'expired',
        'requested_at' => '2026-04-12 09:00:00',
    ]);
    makeReplacement([
        'live_schedule_assignment_id' => $slot->id,
        'original_host_id' => $h1->id,
        'status' => 'pending',
        'requested_at' => '2026-04-15 09:00:00',
    ]);
    // Out of window — must not count.
    makeReplacement([
        'live_schedule_assignment_id' => $slot->id,
        'original_host_id' => $h1->id,
        'status' => 'assigned',
        'requested_at' => '2026-03-30 09:00:00',
        'assigned_at' => '2026-03-30 10:00:00',
    ]);

    $filters = new ReportFilters(
        CarbonImmutable::parse('2026-04-01'),
        CarbonImmutable::parse('2026-04-25'),
    );
    $result = (new ReplacementsReport)->run($filters);

    expect($result->kpis['total'])->toBe(3)
        ->and($result->kpis['fulfilled'])->toBe(1)
        ->and($result->kpis['expired'])->toBe(1);
});

it('computes avgTimeToAssignMinutes correctly', function () {
    $h1 = User::factory()->create(['role' => 'live_host']);
    $h2 = User::factory()->create(['role' => 'live_host']);
    $account = PlatformAccount::factory()->create();
    $slot = LiveScheduleAssignment::factory()->create([
        'platform_account_id' => $account->id,
        'is_template' => false,
        'schedule_date' => '2026-04-10',
        'live_host_id' => $h1->id,
    ]);

    // Assignment took 30 minutes
    makeReplacement([
        'live_schedule_assignment_id' => $slot->id,
        'original_host_id' => $h1->id,
        'replacement_host_id' => $h2->id,
        'status' => 'assigned',
        'requested_at' => '2026-04-10 09:00:00',
        'assigned_at' => '2026-04-10 09:30:00',
    ]);
    // 90 minutes
    makeReplacement([
        'live_schedule_assignment_id' => $slot->id,
        'original_host_id' => $h1->id,
        'replacement_host_id' => $h2->id,
        'status' => 'assigned',
        'requested_at' => '2026-04-12 09:00:00',
        'assigned_at' => '2026-04-12 10:30:00',
    ]);

    $result = (new ReplacementsReport)->run(new ReportFilters(
        CarbonImmutable::parse('2026-04-01'),
        CarbonImmutable::parse('2026-04-25'),
    ));

    expect($result->kpis['avgTimeToAssignMinutes'])->toEqualWithDelta(60.0, 0.1);
});

it('returns null avgTimeToAssign when no fulfilled requests', function () {
    $h1 = User::factory()->create(['role' => 'live_host']);
    $account = PlatformAccount::factory()->create();
    $slot = LiveScheduleAssignment::factory()->create([
        'platform_account_id' => $account->id,
        'is_template' => false,
        'schedule_date' => '2026-04-10',
        'live_host_id' => $h1->id,
    ]);
    makeReplacement([
        'live_schedule_assignment_id' => $slot->id,
        'original_host_id' => $h1->id,
        'status' => 'pending',
        'requested_at' => '2026-04-10 09:00:00',
    ]);

    $result = (new ReplacementsReport)->run(new ReportFilters(
        CarbonImmutable::parse('2026-04-01'),
        CarbonImmutable::parse('2026-04-25'),
    ));

    expect($result->kpis['avgTimeToAssignMinutes'])->toBeNull();
});

it('groups daily request and fulfilled counts', function () {
    $h1 = User::factory()->create(['role' => 'live_host']);
    $h2 = User::factory()->create(['role' => 'live_host']);
    $account = PlatformAccount::factory()->create();
    $slot = LiveScheduleAssignment::factory()->create([
        'platform_account_id' => $account->id,
        'is_template' => false,
        'schedule_date' => '2026-04-10',
        'live_host_id' => $h1->id,
    ]);

    makeReplacement([
        'live_schedule_assignment_id' => $slot->id,
        'original_host_id' => $h1->id,
        'replacement_host_id' => $h2->id,
        'status' => 'assigned',
        'requested_at' => '2026-04-10 09:00:00',
        'assigned_at' => '2026-04-10 10:00:00',
    ]);
    makeReplacement([
        'live_schedule_assignment_id' => $slot->id,
        'original_host_id' => $h1->id,
        'status' => 'pending',
        'requested_at' => '2026-04-10 14:00:00',
    ]);
    makeReplacement([
        'live_schedule_assignment_id' => $slot->id,
        'original_host_id' => $h1->id,
        'status' => 'expired',
        'requested_at' => '2026-04-11 09:00:00',
    ]);

    $result = (new ReplacementsReport)->run(new ReportFilters(
        CarbonImmutable::parse('2026-04-01'),
        CarbonImmutable::parse('2026-04-25'),
    ));

    expect($result->trend)->toHaveCount(2);
    $byDate = collect($result->trend)->keyBy('date');
    expect($byDate['2026-04-10']['requested'])->toBe(2)
        ->and($byDate['2026-04-10']['fulfilled'])->toBe(1)
        ->and($byDate['2026-04-11']['requested'])->toBe(1)
        ->and($byDate['2026-04-11']['fulfilled'])->toBe(0);
});

it('produces top requesters with reason breakdown', function () {
    $h1 = User::factory()->create(['role' => 'live_host', 'name' => 'Alice']);
    $h2 = User::factory()->create(['role' => 'live_host', 'name' => 'Bob']);
    $account = PlatformAccount::factory()->create();
    $slot = LiveScheduleAssignment::factory()->create([
        'platform_account_id' => $account->id,
        'is_template' => false,
        'schedule_date' => '2026-04-10',
        'live_host_id' => $h1->id,
    ]);

    // Alice — 2 sick + 1 family
    makeReplacement(['live_schedule_assignment_id' => $slot->id, 'original_host_id' => $h1->id, 'reason_category' => 'sick', 'requested_at' => '2026-04-10 09:00']);
    makeReplacement(['live_schedule_assignment_id' => $slot->id, 'original_host_id' => $h1->id, 'reason_category' => 'sick', 'requested_at' => '2026-04-11 09:00']);
    makeReplacement(['live_schedule_assignment_id' => $slot->id, 'original_host_id' => $h1->id, 'reason_category' => 'family', 'requested_at' => '2026-04-12 09:00']);
    // Bob — 1 personal
    makeReplacement(['live_schedule_assignment_id' => $slot->id, 'original_host_id' => $h2->id, 'reason_category' => 'personal', 'requested_at' => '2026-04-13 09:00']);

    $result = (new ReplacementsReport)->run(new ReportFilters(
        CarbonImmutable::parse('2026-04-01'),
        CarbonImmutable::parse('2026-04-25'),
    ));

    expect($result->topRequesters)->toHaveCount(2);
    $byName = collect($result->topRequesters)->keyBy('hostName');
    expect($byName['Alice']['requestCount'])->toBe(3)
        ->and($byName['Alice']['reasons']['sick'])->toBe(2)
        ->and($byName['Alice']['reasons']['family'])->toBe(1)
        ->and($byName['Alice']['reasons']['personal'])->toBe(0)
        ->and($byName['Alice']['reasons']['other'])->toBe(0)
        ->and($byName['Bob']['requestCount'])->toBe(1)
        ->and($byName['Bob']['reasons']['personal'])->toBe(1);
});

it('produces top coverers (only assigned requests with replacement host)', function () {
    $h1 = User::factory()->create(['role' => 'live_host', 'name' => 'Original']);
    $h2 = User::factory()->create(['role' => 'live_host', 'name' => 'Coverer']);
    $account = PlatformAccount::factory()->create();
    $slot = LiveScheduleAssignment::factory()->create([
        'platform_account_id' => $account->id,
        'is_template' => false,
        'schedule_date' => '2026-04-10',
        'live_host_id' => $h1->id,
    ]);

    makeReplacement([
        'live_schedule_assignment_id' => $slot->id,
        'original_host_id' => $h1->id,
        'replacement_host_id' => $h2->id,
        'status' => 'assigned',
        'requested_at' => '2026-04-10 09:00:00',
        'assigned_at' => '2026-04-10 09:30:00',
    ]);
    // Pending — has replacement_host_id assigned but status not 'assigned'. Should NOT count.
    makeReplacement([
        'live_schedule_assignment_id' => $slot->id,
        'original_host_id' => $h1->id,
        'replacement_host_id' => $h2->id,
        'status' => 'pending',
        'requested_at' => '2026-04-11 09:00:00',
    ]);

    $result = (new ReplacementsReport)->run(new ReportFilters(
        CarbonImmutable::parse('2026-04-01'),
        CarbonImmutable::parse('2026-04-25'),
    ));

    expect($result->topCoverers)->toHaveCount(1)
        ->and($result->topCoverers[0]['hostName'])->toBe('Coverer')
        ->and($result->topCoverers[0]['coverCount'])->toBe(1);
});

it('returns empty result when no requests exist', function () {
    $result = (new ReplacementsReport)->run(new ReportFilters(
        CarbonImmutable::parse('2026-04-01'),
        CarbonImmutable::parse('2026-04-25'),
    ));

    expect($result->kpis['total'])->toBe(0)
        ->and($result->kpis['avgTimeToAssignMinutes'])->toBeNull()
        ->and($result->trend)->toBe([])
        ->and($result->topRequesters)->toBe([])
        ->and($result->topCoverers)->toBe([]);
});

it('runs in a bounded number of queries', function () {
    $hosts = collect();
    for ($i = 0; $i < 10; $i++) {
        $hosts->push(User::factory()->create(['role' => 'live_host']));
    }
    $account = PlatformAccount::factory()->create();
    $slot = LiveScheduleAssignment::factory()->create([
        'platform_account_id' => $account->id,
        'is_template' => false,
        'schedule_date' => '2026-04-10',
        'live_host_id' => $hosts->first()->id,
    ]);

    foreach ($hosts as $i => $host) {
        for ($j = 0; $j < 10; $j++) {
            makeReplacement([
                'live_schedule_assignment_id' => $slot->id,
                'original_host_id' => $host->id,
                'replacement_host_id' => $hosts->random()->id,
                'status' => fake()->randomElement(['pending', 'assigned', 'expired']),
                'reason_category' => fake()->randomElement(['sick', 'family', 'personal', 'other']),
                'requested_at' => fake()->dateTimeBetween('2026-04-01', '2026-04-25'),
                'assigned_at' => fake()->boolean() ? fake()->dateTimeBetween('2026-04-01', '2026-04-25') : null,
            ]);
        }
    }

    DB::enableQueryLog();
    (new ReplacementsReport)->run(new ReportFilters(
        CarbonImmutable::parse('2026-04-01'),
        CarbonImmutable::parse('2026-04-25'),
    ));
    $count = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($count)->toBeLessThanOrEqual(5);
});
