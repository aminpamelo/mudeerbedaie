<?php

use App\Models\LiveSession;
use App\Models\PlatformAccount;
use App\Models\User;
use Carbon\CarbonImmutable;

use function Pest\Laravel\actingAs;

beforeEach(fn () => CarbonImmutable::setTestNow('2026-04-25 10:00:00'));

it('forbids unauthorised users', function () {
    $user = User::factory()->create(['role' => 'student']);

    actingAs($user)->get('/livehost/reports/host-scorecard')->assertForbidden();
});

it('renders Inertia page with kpis, rows, trend, filter options', function () {
    $admin = User::factory()->create(['role' => 'admin_livehost']);
    $host = User::factory()->create(['role' => 'live_host', 'name' => 'Sarah Chen']);
    $account = PlatformAccount::factory()->create();

    LiveSession::factory()->create([
        'live_host_id' => $host->id,
        'platform_account_id' => $account->id,
        'status' => 'ended',
        'scheduled_start_at' => '2026-04-10 08:00:00',
        'duration_minutes' => 60,
        'gmv_amount' => 100,
    ]);

    actingAs($admin)
        ->get('/livehost/reports/host-scorecard')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('reports/HostScorecard', false)
            ->has('kpis')
            ->has('rows', 1)
            ->has('trend')
            ->has('filterOptions.hosts')
            ->has('filterOptions.platformAccounts')
            ->has('filters')
        );
});

it('honours dateFrom/dateTo query params', function () {
    $admin = User::factory()->create(['role' => 'admin_livehost']);

    actingAs($admin)
        ->get('/livehost/reports/host-scorecard?dateFrom=2026-03-01&dateTo=2026-03-31')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('reports/HostScorecard', false)
            ->where('filters.dateFrom', '2026-03-01')
            ->where('filters.dateTo', '2026-03-31')
        );
});
