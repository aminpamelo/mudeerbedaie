<?php

use App\Models\LiveSession;
use App\Models\PlatformAccount;
use App\Models\User;
use Carbon\CarbonImmutable;

use function Pest\Laravel\actingAs;

beforeEach(fn () => CarbonImmutable::setTestNow('2026-04-25 10:00:00'));

it('forbids unauthorised users', function () {
    $user = User::factory()->create(['role' => 'student']);

    actingAs($user)->get('/livehost/reports/gmv')->assertForbidden();
});

it('renders Inertia page with kpis, accountSeries, hostRows, trend, topSessions, filters', function () {
    $admin = User::factory()->create(['role' => 'admin_livehost']);
    $host = User::factory()->create(['role' => 'live_host', 'name' => 'Sarah Chen']);
    $account = PlatformAccount::factory()->create(['name' => 'Account A']);

    LiveSession::factory()->create([
        'live_host_id' => $host->id,
        'platform_account_id' => $account->id,
        'status' => 'ended',
        'scheduled_start_at' => '2026-04-10 08:00:00',
        'gmv_amount' => 500,
    ]);

    actingAs($admin)
        ->get('/livehost/reports/gmv')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('reports/Gmv', false)
            ->has('kpis.current.totalGmv')
            ->has('kpis.prior.totalGmv')
            ->has('accountSeries', 1)
            ->has('hostRows', 1)
            ->has('trendByAccount')
            ->has('topSessions', 1)
            ->has('filterOptions.hosts')
            ->has('filterOptions.platformAccounts')
            ->where('filters.dateFrom', '2026-04-01')
        );
});

it('honours dateFrom/dateTo query params', function () {
    $admin = User::factory()->create(['role' => 'admin_livehost']);

    actingAs($admin)
        ->get('/livehost/reports/gmv?dateFrom=2026-03-01&dateTo=2026-03-31')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('reports/Gmv', false)
            ->where('filters.dateFrom', '2026-03-01')
            ->where('filters.dateTo', '2026-03-31')
        );
});

it('streams a CSV export with header + one row per host', function () {
    $admin = User::factory()->create(['role' => 'admin_livehost']);
    $host = User::factory()->create(['role' => 'live_host', 'name' => 'Sarah Chen']);
    $account = PlatformAccount::factory()->create();
    LiveSession::factory()->create([
        'live_host_id' => $host->id,
        'platform_account_id' => $account->id,
        'status' => 'ended',
        'scheduled_start_at' => '2026-04-10 08:00:00',
        'gmv_amount' => 500.00,
    ]);

    $response = actingAs($admin)
        ->get('/livehost/reports/gmv/export?dateFrom=2026-04-01&dateTo=2026-04-25')
        ->assertOk();

    expect($response->headers->get('Content-Type'))->toContain('text/csv');
    $content = $response->streamedContent();
    $lines = array_values(array_filter(explode("\n", $content)));
    expect($lines[0])->toContain('Host')->toContain('GMV');
    expect($lines[1])->toContain('Sarah Chen')->toContain('500');
});
