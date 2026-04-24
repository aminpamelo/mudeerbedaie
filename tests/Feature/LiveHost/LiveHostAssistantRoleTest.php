<?php

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
        ->where('auth.permissions.canSeeFinancials', true)
        ->where('auth.permissions.canRecruit', true)
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
