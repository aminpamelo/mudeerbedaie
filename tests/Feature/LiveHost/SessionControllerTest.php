<?php

use App\Models\LiveAnalytics;
use App\Models\LiveSession;
use App\Models\LiveSessionAttachment;
use App\Models\PlatformAccount;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->pic = User::factory()->create(['role' => 'admin_livehost']);
});

it('lists sessions with pagination (15 per page)', function () {
    LiveSession::factory()->count(20)->create();

    actingAs($this->pic)
        ->get('/livehost/sessions')
        ->assertInertia(fn (Assert $p) => $p
            ->component('sessions/Index', false)
            ->has('sessions.data', 15)
            ->has('sessions.links')
            ->has('filters')
            ->has('hosts')
            ->has('platformAccounts'));
});

it('maps session DTO fields with formatted session id', function () {
    $host = User::factory()->create(['role' => 'live_host', 'name' => 'Host One']);
    $account = PlatformAccount::factory()->create(['name' => 'TikTok Main']);

    $session = LiveSession::factory()->create([
        'live_host_id' => $host->id,
        'platform_account_id' => $account->id,
        'status' => 'live',
        'actual_start_at' => now()->subMinutes(30),
    ]);

    actingAs($this->pic)
        ->get('/livehost/sessions')
        ->assertInertia(fn (Assert $p) => $p
            ->has('sessions.data', 1)
            ->where('sessions.data.0.id', $session->id)
            ->where('sessions.data.0.sessionId', 'LS-'.str_pad((string) $session->id, 5, '0', STR_PAD_LEFT))
            ->where('sessions.data.0.hostName', 'Host One')
            ->where('sessions.data.0.platformAccount', 'TikTok Main')
            ->where('sessions.data.0.status', 'live')
            ->where('sessions.data.0.viewers', 0)
            ->etc());
});

it('filters sessions by status', function () {
    LiveSession::factory()->count(2)->create(['status' => 'live']);
    LiveSession::factory()->count(3)->create(['status' => 'ended']);
    LiveSession::factory()->count(1)->create(['status' => 'cancelled']);

    actingAs($this->pic)
        ->get('/livehost/sessions?status=ended')
        ->assertInertia(fn (Assert $p) => $p
            ->has('sessions.data', 3)
            ->where('filters.status', 'ended'));
});

it('filters sessions by platform_account', function () {
    $accountA = PlatformAccount::factory()->create();
    $accountB = PlatformAccount::factory()->create();
    LiveSession::factory()->count(2)->create(['platform_account_id' => $accountA->id]);
    LiveSession::factory()->count(4)->create(['platform_account_id' => $accountB->id]);

    actingAs($this->pic)
        ->get("/livehost/sessions?platform_account={$accountA->id}")
        ->assertInertia(fn (Assert $p) => $p->has('sessions.data', 2));
});

it('filters sessions by host', function () {
    $hostA = User::factory()->create(['role' => 'live_host']);
    $hostB = User::factory()->create(['role' => 'live_host']);
    LiveSession::factory()->count(3)->create(['live_host_id' => $hostA->id]);
    LiveSession::factory()->count(2)->create(['live_host_id' => $hostB->id]);

    actingAs($this->pic)
        ->get("/livehost/sessions?host={$hostA->id}")
        ->assertInertia(fn (Assert $p) => $p->has('sessions.data', 3));
});

it('filters sessions by date range', function () {
    LiveSession::factory()->create(['scheduled_start_at' => '2026-04-10 10:00:00']);
    LiveSession::factory()->create(['scheduled_start_at' => '2026-04-15 10:00:00']);
    LiveSession::factory()->create(['scheduled_start_at' => '2026-04-20 10:00:00']);
    LiveSession::factory()->create(['scheduled_start_at' => '2026-04-25 10:00:00']);

    actingAs($this->pic)
        ->get('/livehost/sessions?from=2026-04-12&to=2026-04-22')
        ->assertInertia(fn (Assert $p) => $p
            ->has('sessions.data', 2)
            ->where('filters.from', '2026-04-12')
            ->where('filters.to', '2026-04-22'));
});

it('shows a session with analytics and attachments', function () {
    $session = LiveSession::factory()->create(['status' => 'ended']);
    LiveAnalytics::factory()->create([
        'live_session_id' => $session->id,
        'viewers_peak' => 1200,
        'viewers_avg' => 700,
        'total_likes' => 300,
        'total_comments' => 80,
        'total_shares' => 20,
    ]);
    LiveSessionAttachment::factory()->count(2)->create([
        'live_session_id' => $session->id,
    ]);

    actingAs($this->pic)
        ->get("/livehost/sessions/{$session->id}")
        ->assertInertia(fn (Assert $p) => $p
            ->component('sessions/Show', false)
            ->where('session.id', $session->id)
            ->where('session.status', 'ended')
            ->where('analytics.viewersPeak', 1200)
            ->where('analytics.viewersAvg', 700)
            ->where('analytics.totalLikes', 300)
            ->has('attachments', 2)
            ->has('attachments.0.fileName')
            ->has('attachments.0.fileUrl'));
});

it('returns null analytics and empty attachments when none exist', function () {
    $session = LiveSession::factory()->create();

    actingAs($this->pic)
        ->get("/livehost/sessions/{$session->id}")
        ->assertInertia(fn (Assert $p) => $p
            ->component('sessions/Show', false)
            ->where('session.id', $session->id)
            ->where('analytics', null)
            ->has('attachments', 0));
});

it('returns 404 for non-existent session', function () {
    actingAs($this->pic)
        ->get('/livehost/sessions/999999')
        ->assertNotFound();
});

it('forbids live_host from accessing the sessions index', function () {
    $host = User::factory()->create(['role' => 'live_host']);

    $this->actingAs($host)
        ->get('/livehost/sessions')
        ->assertForbidden();
});

it('forbids live_host from viewing a session detail', function () {
    $host = User::factory()->create(['role' => 'live_host']);
    $session = LiveSession::factory()->create();

    $this->actingAs($host)
        ->get("/livehost/sessions/{$session->id}")
        ->assertForbidden();
});

it('allows admin role to view sessions index', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    LiveSession::factory()->count(3)->create();

    actingAs($admin)
        ->get('/livehost/sessions')
        ->assertOk()
        ->assertInertia(fn (Assert $p) => $p->has('sessions.data', 3));
});
