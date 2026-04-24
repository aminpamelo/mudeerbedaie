<?php

use App\Models\LiveScheduleAssignment;
use App\Models\LiveTimeSlot;
use App\Models\PlatformAccount;
use App\Models\User;
use Illuminate\Support\Facades\Route as RouteFacade;

it('can create a livehost_assistant user and detect the role', function () {
    $user = User::factory()->liveHostAssistant()->create();

    expect($user->role)->toBe('livehost_assistant');
    expect($user->isLiveHostAssistant())->toBeTrue();
    expect($user->isAdminLivehost())->toBeFalse();
    expect($user->isLiveHost())->toBeFalse();
});

it('403s when assistant tries to hit admin-only livehost routes', function (string $routeName, string $method, array $params) {
    $assistant = User::factory()->liveHostAssistant()->create();

    $url = route($routeName, $params);
    $response = $this->actingAs($assistant)->call($method, $url);

    expect($response->status())->toBe(403);
})->with([
    ['livehost.live-now', 'GET', []],
    ['livehost.sessions.index', 'GET', []],
    ['livehost.commission.index', 'GET', []],
    ['livehost.commission.export', 'GET', []],
    ['livehost.payroll.index', 'GET', []],
    ['livehost.tiktok-imports.index', 'GET', []],
    ['livehost.recruitment.campaigns.index', 'GET', []],
    ['livehost.hosts.create', 'GET', []],
    ['livehost.schedules.index', 'GET', []],
]);

it('allows assistant to reach shared livehost routes', function (string $routeName) {
    $assistant = User::factory()->liveHostAssistant()->create();

    $response = $this->actingAs($assistant)->get(route($routeName));

    expect($response->status())->toBe(200);
})->with([
    'livehost.dashboard',
    'livehost.hosts.index',
    'livehost.platform-accounts.index',
    'livehost.creators.index',
    'livehost.time-slots.index',
    'livehost.session-slots.index',
    'livehost.session-slots.calendar',
    'livehost.session-slots.table',
]);

it('controller guards 403 the assistant even without role middleware', function () {
    RouteFacade::middleware('auth')->get('__test__/live-now', [
        \App\Http\Controllers\LiveHost\DashboardController::class,
        'liveNowJson',
    ])->name('__test__.live-now');

    $assistant = User::factory()->liveHostAssistant()->create();

    $response = $this->actingAs($assistant)->get('/__test__/live-now');

    expect($response->status())->toBe(403);
});

it('shares a permissions prop on Inertia responses', function () {
    $assistant = User::factory()->liveHostAssistant()->create();

    $response = $this->actingAs($assistant)->get(route('livehost.session-slots.index'));

    $response->assertInertia(fn ($page) => $page
        ->where('auth.user.role', 'livehost_assistant')
        ->where('auth.permissions.canManageHosts', false)
        ->where('auth.permissions.canManagePlatformAccounts', false)
        ->where('auth.permissions.canManageCreators', false)
        ->where('auth.permissions.canSeeSessions', false)
        ->where('auth.permissions.canSeeFinancials', false)
        ->where('auth.permissions.canSeePayroll', false)
        ->where('auth.permissions.canRecruit', false)
        ->where('auth.permissions.canSeeTiktokImports', false)
    );
});

it('grants admin_livehost full permissions in shared prop', function () {
    $pic = User::factory()->create(['role' => 'admin_livehost']);

    $response = $this->actingAs($pic)->get(route('livehost.session-slots.index'));

    $response->assertInertia(fn ($page) => $page
        ->where('auth.permissions.canManageHosts', true)
        ->where('auth.permissions.canManagePlatformAccounts', true)
        ->where('auth.permissions.canManageCreators', true)
        ->where('auth.permissions.canSeeSessions', true)
        ->where('auth.permissions.canSeeFinancials', true)
        ->where('auth.permissions.canSeePayroll', true)
        ->where('auth.permissions.canRecruit', true)
        ->where('auth.permissions.canSeeTiktokImports', true)
    );
});

it('excludes sensitive nav count keys from assistant payload', function () {
    $assistant = User::factory()->liveHostAssistant()->create();

    $response = $this->actingAs($assistant)->get(route('livehost.session-slots.index'));

    $response->assertInertia(fn ($page) => $page
        ->has('navCounts.hosts')
        ->has('navCounts.platformAccounts')
        ->has('navCounts.creators')
        ->missing('navCounts.sessions')
        ->missing('navCounts.schedules')
    );
});

it('renders SchedulerDashboard for livehost_assistant', function () {
    $assistant = User::factory()->liveHostAssistant()->create();

    $response = $this->actingAs($assistant)->get(route('livehost.dashboard'));

    $response->assertInertia(fn ($page) => $page
        ->component('SchedulerDashboard', false)
        ->has('stats.coveragePercent')
        ->has('stats.unassignedCount')
        ->has('stats.activeHosts')
        ->has('stats.platformAccounts')
        ->has('unassignedThisWeek')
        ->has('todaySlots')
    );
});

it('still renders Dashboard for admin_livehost', function () {
    $pic = User::factory()->create(['role' => 'admin_livehost']);

    $response = $this->actingAs($pic)->get(route('livehost.dashboard'));

    $response->assertInertia(fn ($page) => $page->component('Dashboard', false));
});

it('every non-shared /livehost route returns 403 for the assistant', function () {
    $assistant = User::factory()->liveHostAssistant()->create();

    $shared = [
        'livehost.dashboard',
        'livehost.hosts.index',
        'livehost.hosts.show',
        'livehost.platform-accounts.index',
        'livehost.platform-accounts.show',
        'livehost.creators.index',
        'livehost.time-slots.index',
        'livehost.time-slots.create',
        'livehost.time-slots.store',
        'livehost.time-slots.edit',
        'livehost.time-slots.update',
        'livehost.time-slots.destroy',
        'livehost.session-slots.index',
        'livehost.session-slots.calendar',
        'livehost.session-slots.table',
        'livehost.session-slots.preview',
        'livehost.session-slots.create',
        'livehost.session-slots.store',
        'livehost.session-slots.show',
        'livehost.session-slots.edit',
        'livehost.session-slots.update',
        'livehost.session-slots.destroy',
    ];

    $livehostRoutes = collect(RouteFacade::getRoutes())
        ->filter(fn ($r) => str_starts_with($r->getName() ?? '', 'livehost.'))
        ->filter(fn ($r) => ! in_array($r->getName(), $shared, true));

    $failures = [];

    foreach ($livehostRoutes as $route) {
        // Skip routes that require model binding params we can't satisfy in a smoke test.
        if (str_contains($route->uri(), '{')) {
            continue;
        }

        $methods = array_diff($route->methods(), ['HEAD']);
        foreach ($methods as $method) {
            $response = $this->actingAs($assistant)->call($method, $route->uri());
            if ($response->status() !== 403) {
                $failures[] = "{$method} {$route->uri()} returned {$response->status()}";
            }
        }
    }

    expect($failures)->toBe([]);
});

it('assistant can create a time-slot template', function () {
    $assistant = User::factory()->liveHostAssistant()->create();
    $platformAccount = PlatformAccount::factory()->create();

    $response = $this->actingAs($assistant)->post(route('livehost.time-slots.store'), [
        'platform_account_id' => $platformAccount->id,
        'day_of_week' => 1,
        'start_time' => '09:00',
        'end_time' => '11:00',
        'is_active' => true,
    ]);

    expect(in_array($response->status(), [200, 201, 302], true))->toBeTrue();
    expect(LiveTimeSlot::query()->where('platform_account_id', $platformAccount->id)->exists())->toBeTrue();
});

it('assistant can update a session-slot to assign a host', function () {
    $assistant = User::factory()->liveHostAssistant()->create();
    $host = User::factory()->create(['role' => 'live_host']);
    $assignment = LiveScheduleAssignment::factory()->create();

    $response = $this->actingAs($assistant)->put(
        route('livehost.session-slots.update', $assignment),
        [
            'platform_account_id' => $assignment->platform_account_id,
            'time_slot_id' => $assignment->time_slot_id,
            'live_host_id' => $host->id,
            'day_of_week' => (int) $assignment->day_of_week,
            'is_template' => true,
            'status' => 'scheduled',
        ]
    );

    expect($response->status())->not->toBe(403);
    expect(in_array($response->status(), [200, 201, 302], true))->toBeTrue();
    expect(LiveScheduleAssignment::query()->where('id', $assignment->id)->value('live_host_id'))
        ->toBe($host->id);
});

it('hides commission and session data from assistant on host detail', function () {
    $host = User::factory()->create(['role' => 'live_host']);
    $assistant = User::factory()->liveHostAssistant()->create();

    $response = $this->actingAs($assistant)->get(route('livehost.hosts.show', $host));

    $response->assertInertia(fn ($page) => $page
        ->where('commissionProfile', null)
        ->where('platformCommissionRates', [])
        ->where('commissionProfiles', [])
        ->where('commissionTiers', [])
        ->where('recentSessions', [])
    );
});

it('still shows commission data structure to PIC on host detail', function () {
    $host = User::factory()->create(['role' => 'live_host']);
    $pic = User::factory()->create(['role' => 'admin_livehost']);

    $response = $this->actingAs($pic)->get(route('livehost.hosts.show', $host));

    $response->assertInertia(fn ($page) => $page
        ->has('platformCommissionRates')
        ->has('commissionTiers')
        ->has('recentSessions')
    );
});
