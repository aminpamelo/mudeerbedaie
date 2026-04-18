<?php

use App\Models\LiveSession;
use App\Models\PlatformAccount;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->pic = User::factory()->create(['role' => 'admin_livehost']);
});

it('exposes dashboard stats via Inertia props', function () {
    User::factory()->count(3)->create(['role' => 'live_host', 'status' => 'active']);
    $accounts = PlatformAccount::factory()->count(2)->create();
    LiveSession::factory()->create([
        'status' => 'live',
        'platform_account_id' => $accounts->first()->id,
    ]);

    actingAs($this->pic)
        ->get('/livehost')
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard', false)
            ->has('stats', fn (Assert $s) => $s
                ->where('totalHosts', 3)
                ->where('activeHosts', 3)
                ->where('platformAccounts', 2)
                ->where('liveNow', 1)
                ->etc())
            ->has('liveNow', 1)
            ->has('upcoming')
            ->has('recentActivity')
            ->has('topHosts')
            ->has('navCounts'));
});

it('counts only live_host role users, not admins', function () {
    User::factory()->count(2)->create(['role' => 'live_host']);
    User::factory()->count(5)->create(['role' => 'admin']);
    User::factory()->count(3)->create(['role' => 'student']);

    actingAs($this->pic)
        ->get('/livehost')
        ->assertInertia(fn (Assert $page) => $page
            ->where('stats.totalHosts', 2));
});

it('counts only live sessions as liveNow', function () {
    LiveSession::factory()->count(3)->create(['status' => 'live']);
    LiveSession::factory()->count(5)->create(['status' => 'ended']);
    LiveSession::factory()->count(2)->create(['status' => 'scheduled']);

    actingAs($this->pic)
        ->get('/livehost')
        ->assertInertia(fn (Assert $p) => $p->where('stats.liveNow', 3));
});
