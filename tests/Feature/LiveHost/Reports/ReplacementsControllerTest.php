<?php

use App\Models\LiveScheduleAssignment;
use App\Models\PlatformAccount;
use App\Models\SessionReplacementRequest;
use App\Models\User;
use Carbon\CarbonImmutable;

use function Pest\Laravel\actingAs;

beforeEach(fn () => CarbonImmutable::setTestNow('2026-04-25 10:00:00'));

it('forbids unauthorised users', function () {
    $user = User::factory()->create(['role' => 'student']);
    actingAs($user)->get('/livehost/reports/replacements')->assertForbidden();
});

it('renders Inertia page with kpis, trend, top requesters and coverers, filters', function () {
    $admin = User::factory()->create(['role' => 'admin_livehost']);
    $h1 = User::factory()->create(['role' => 'live_host', 'name' => 'Alice']);
    $h2 = User::factory()->create(['role' => 'live_host', 'name' => 'Bob']);
    $account = PlatformAccount::factory()->create();
    $slot = LiveScheduleAssignment::factory()->create([
        'platform_account_id' => $account->id,
        'is_template' => false,
        'schedule_date' => '2026-04-10',
        'live_host_id' => $h1->id,
    ]);

    SessionReplacementRequest::factory()->create([
        'live_schedule_assignment_id' => $slot->id,
        'original_host_id' => $h1->id,
        'replacement_host_id' => $h2->id,
        'scope' => 'one_date',
        'reason_category' => 'sick',
        'status' => 'assigned',
        'requested_at' => '2026-04-10 09:00:00',
        'assigned_at' => '2026-04-10 09:30:00',
        'target_date' => '2026-04-10',
        'expires_at' => '2026-04-10 14:00:00',
    ]);

    actingAs($admin)
        ->get('/livehost/reports/replacements')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('reports/Replacements', false)
            ->has('kpis.current.total')
            ->has('kpis.prior.total')
            ->has('trend', 1)
            ->has('topRequesters', 1)
            ->has('topCoverers', 1)
            ->has('filterOptions.hosts')
            ->has('filterOptions.platformAccounts')
            ->where('filters.dateFrom', '2026-04-01')
        );
});

it('honours dateFrom/dateTo query params', function () {
    $admin = User::factory()->create(['role' => 'admin_livehost']);

    actingAs($admin)
        ->get('/livehost/reports/replacements?dateFrom=2026-03-01&dateTo=2026-03-31')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('reports/Replacements', false)
            ->where('filters.dateFrom', '2026-03-01')
            ->where('filters.dateTo', '2026-03-31')
        );
});

it('streams a CSV export with both requesters and coverers', function () {
    $admin = User::factory()->create(['role' => 'admin_livehost']);
    $h1 = User::factory()->create(['role' => 'live_host', 'name' => 'Alice']);
    $h2 = User::factory()->create(['role' => 'live_host', 'name' => 'Bob']);
    $account = PlatformAccount::factory()->create();
    $slot = LiveScheduleAssignment::factory()->create([
        'platform_account_id' => $account->id,
        'is_template' => false,
        'schedule_date' => '2026-04-10',
        'live_host_id' => $h1->id,
    ]);

    SessionReplacementRequest::factory()->create([
        'live_schedule_assignment_id' => $slot->id,
        'original_host_id' => $h1->id,
        'replacement_host_id' => $h2->id,
        'scope' => 'one_date',
        'reason_category' => 'sick',
        'status' => 'assigned',
        'requested_at' => '2026-04-10 09:00:00',
        'assigned_at' => '2026-04-10 09:30:00',
        'target_date' => '2026-04-10',
        'expires_at' => '2026-04-10 14:00:00',
    ]);

    $response = actingAs($admin)
        ->get('/livehost/reports/replacements/export?dateFrom=2026-04-01&dateTo=2026-04-25')
        ->assertOk();

    expect($response->headers->get('Content-Type'))->toContain('text/csv');
    $content = $response->streamedContent();
    $lines = array_values(array_filter(explode("\n", $content)));
    expect($lines[0])->toContain('Type')->toContain('Host')->toContain('Reasons');

    $body = implode("\n", array_slice($lines, 1));
    expect($body)->toContain('Requester')->toContain('Alice')->toContain('sick')
        ->and($body)->toContain('Coverer')->toContain('Bob');
});
