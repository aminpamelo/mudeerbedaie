<?php

use App\Models\LiveScheduleAssignment;
use App\Models\PlatformAccount;
use App\Models\User;
use Carbon\CarbonImmutable;

use function Pest\Laravel\actingAs;

beforeEach(fn () => CarbonImmutable::setTestNow('2026-04-25 10:00:00'));

it('forbids unauthorised users', function () {
    $user = User::factory()->create(['role' => 'student']);
    actingAs($user)->get('/livehost/reports/coverage')->assertForbidden();
});

it('renders Inertia page with kpis, weekly trend, account rows, filters', function () {
    $admin = User::factory()->create(['role' => 'admin_livehost']);
    $host = User::factory()->create(['role' => 'live_host']);
    $account = PlatformAccount::factory()->create(['name' => 'Acc A']);

    LiveScheduleAssignment::factory()->create([
        'platform_account_id' => $account->id,
        'is_template' => false,
        'schedule_date' => '2026-04-10',
        'live_host_id' => $host->id,
    ]);

    actingAs($admin)
        ->get('/livehost/reports/coverage')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('reports/Coverage', false)
            ->has('kpis.current.totalSlots')
            ->has('kpis.prior.totalSlots')
            ->has('weeklyTrend', 1)
            ->has('accountRows', 1)
            ->has('filterOptions.hosts')
            ->has('filterOptions.platformAccounts')
            ->where('filters.dateFrom', '2026-04-01')
        );
});

it('honours dateFrom/dateTo query params', function () {
    $admin = User::factory()->create(['role' => 'admin_livehost']);

    actingAs($admin)
        ->get('/livehost/reports/coverage?dateFrom=2026-03-01&dateTo=2026-03-31')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('reports/Coverage', false)
            ->where('filters.dateFrom', '2026-03-01')
            ->where('filters.dateTo', '2026-03-31')
        );
});

it('streams a CSV export with header + one row per account', function () {
    $admin = User::factory()->create(['role' => 'admin_livehost']);
    $host = User::factory()->create(['role' => 'live_host']);
    $account = PlatformAccount::factory()->create(['name' => 'TheAccount']);

    LiveScheduleAssignment::factory()->create([
        'platform_account_id' => $account->id,
        'is_template' => false,
        'schedule_date' => '2026-04-10',
        'live_host_id' => $host->id,
    ]);

    $response = actingAs($admin)
        ->get('/livehost/reports/coverage/export?dateFrom=2026-04-01&dateTo=2026-04-25')
        ->assertOk();

    expect($response->headers->get('Content-Type'))->toContain('text/csv');
    $content = $response->streamedContent();
    $lines = array_values(array_filter(explode("\n", $content)));
    expect($lines[0])->toContain('Account')->toContain('Coverage %');
    expect($lines[1])->toContain('TheAccount');
});
