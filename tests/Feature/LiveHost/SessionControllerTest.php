<?php

use App\Models\LiveAnalytics;
use App\Models\LiveSession;
use App\Models\LiveSessionAttachment;
use App\Models\PlatformAccount;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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

it('updates editable session fields (title, description, host, status)', function () {
    $host = User::factory()->create(['role' => 'live_host']);
    $session = LiveSession::factory()->create([
        'title' => 'Old title',
        'description' => 'Old description',
        'live_host_id' => null,
        'status' => 'scheduled',
    ]);

    actingAs($this->pic)
        ->put("/livehost/sessions/{$session->id}", [
            'title' => 'New title',
            'description' => 'Updated notes',
            'live_host_id' => $host->id,
            'status' => 'ended',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($session->fresh())
        ->title->toBe('New title')
        ->description->toBe('Updated notes')
        ->live_host_id->toBe($host->id)
        ->status->toBe('ended');
});

it('allows clearing the host assignment to unassigned', function () {
    $host = User::factory()->create(['role' => 'live_host']);
    $session = LiveSession::factory()->create(['live_host_id' => $host->id]);

    actingAs($this->pic)
        ->put("/livehost/sessions/{$session->id}", [
            'title' => 'x',
            'description' => null,
            'live_host_id' => null,
            'status' => 'scheduled',
        ])
        ->assertRedirect();

    expect($session->fresh()->live_host_id)->toBeNull();
});

it('rejects session update with invalid status', function () {
    $session = LiveSession::factory()->create();

    actingAs($this->pic)
        ->put("/livehost/sessions/{$session->id}", [
            'status' => 'bogus',
        ])
        ->assertSessionHasErrors('status');
});

it('includes attachments array and attachmentCount on the sessions index', function () {
    $session = LiveSession::factory()->create();
    LiveSessionAttachment::factory()->count(2)->create([
        'live_session_id' => $session->id,
        'uploaded_by' => $this->pic->id,
    ]);

    actingAs($this->pic)
        ->get('/livehost/sessions')
        ->assertInertia(fn (Assert $p) => $p
            ->has('sessions.data', 1)
            ->where('sessions.data.0.attachmentCount', 2)
            ->has('sessions.data.0.attachments', 2));
});

it('uploads an attachment to a session', function () {
    Storage::fake('public');
    $session = LiveSession::factory()->create();
    $file = UploadedFile::fake()->create('notes.pdf', 100, 'application/pdf');

    actingAs($this->pic)
        ->post("/livehost/sessions/{$session->id}/attachments", [
            'file' => $file,
            'description' => 'Sponsor brief',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect(LiveSessionAttachment::where('live_session_id', $session->id)->count())->toBe(1);
    $attachment = LiveSessionAttachment::where('live_session_id', $session->id)->first();
    expect($attachment->file_name)->toBe('notes.pdf');
    expect($attachment->description)->toBe('Sponsor brief');
    expect($attachment->uploaded_by)->toBe($this->pic->id);
    Storage::disk('public')->assertExists($attachment->file_path);
});

it('rejects attachment upload with no file', function () {
    $session = LiveSession::factory()->create();

    actingAs($this->pic)
        ->post("/livehost/sessions/{$session->id}/attachments", [
            'description' => 'No file',
        ])
        ->assertSessionHasErrors('file');
});

it('forbids live_host from uploading a session attachment via admin route', function () {
    $host = User::factory()->create(['role' => 'live_host']);
    $session = LiveSession::factory()->create();
    $file = UploadedFile::fake()->create('notes.pdf', 100, 'application/pdf');

    actingAs($host)
        ->post("/livehost/sessions/{$session->id}/attachments", [
            'file' => $file,
        ])
        ->assertForbidden();
});

it('deletes an attachment from a session', function () {
    Storage::fake('public');
    $session = LiveSession::factory()->create();
    $path = UploadedFile::fake()->create('x.pdf', 10, 'application/pdf')
        ->store('live-sessions/'.$session->id.'/attachments', 'public');
    $attachment = LiveSessionAttachment::factory()->create([
        'live_session_id' => $session->id,
        'file_path' => $path,
        'uploaded_by' => $this->pic->id,
    ]);

    actingAs($this->pic)
        ->delete("/livehost/sessions/{$session->id}/attachments/{$attachment->id}")
        ->assertRedirect()
        ->assertSessionHas('success');

    expect(LiveSessionAttachment::find($attachment->id))->toBeNull();
    Storage::disk('public')->assertMissing($path);
});

it('rejects deleting an attachment that belongs to a different session', function () {
    Storage::fake('public');
    $sessionA = LiveSession::factory()->create();
    $sessionB = LiveSession::factory()->create();
    $attachment = LiveSessionAttachment::factory()->create([
        'live_session_id' => $sessionB->id,
        'uploaded_by' => $this->pic->id,
    ]);

    actingAs($this->pic)
        ->delete("/livehost/sessions/{$sessionA->id}/attachments/{$attachment->id}")
        ->assertNotFound();
});

it('marks a session as verified with notes and records the verifier', function () {
    $session = LiveSession::factory()->create(['verification_status' => 'pending']);

    actingAs($this->pic)
        ->post("/livehost/sessions/{$session->id}/verify", [
            'verification_status' => 'verified',
            'verification_notes' => 'Looked clean, signing off.',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $session->refresh();
    expect($session->verification_status)->toBe('verified');
    expect($session->verification_notes)->toBe('Looked clean, signing off.');
    expect($session->verified_by)->toBe($this->pic->id);
    expect($session->verified_at)->not->toBeNull();
});

it('marks a session as rejected', function () {
    $session = LiveSession::factory()->create(['verification_status' => 'pending']);

    actingAs($this->pic)
        ->post("/livehost/sessions/{$session->id}/verify", [
            'verification_status' => 'rejected',
            'verification_notes' => 'Host missed call time.',
        ])
        ->assertRedirect();

    expect($session->fresh()->verification_status)->toBe('rejected');
});

it('resets verification back to pending and clears verifier', function () {
    $session = LiveSession::factory()->create([
        'verification_status' => 'verified',
        'verified_by' => $this->pic->id,
        'verified_at' => now(),
    ]);

    actingAs($this->pic)
        ->post("/livehost/sessions/{$session->id}/verify", [
            'verification_status' => 'pending',
        ])
        ->assertRedirect();

    $session->refresh();
    expect($session->verification_status)->toBe('pending');
    expect($session->verified_by)->toBeNull();
    expect($session->verified_at)->toBeNull();
});

it('rejects verify with an invalid verification_status', function () {
    $session = LiveSession::factory()->create();

    actingAs($this->pic)
        ->post("/livehost/sessions/{$session->id}/verify", [
            'verification_status' => 'bogus',
        ])
        ->assertSessionHasErrors('verification_status');
});

it('forbids live_host from verifying a session', function () {
    $host = User::factory()->create(['role' => 'live_host']);
    $session = LiveSession::factory()->create();

    actingAs($host)
        ->post("/livehost/sessions/{$session->id}/verify", [
            'verification_status' => 'verified',
        ])
        ->assertForbidden();
});

it('filters sessions by verification_status via the verified tab', function () {
    LiveSession::factory()->count(2)->create(['verification_status' => 'pending']);
    LiveSession::factory()->count(3)->create(['verification_status' => 'verified']);

    actingAs($this->pic)
        ->get('/livehost/sessions?tab=verified')
        ->assertInertia(fn (Assert $p) => $p->has('sessions.data', 3));
});

it('needs_review tab returns only ended sessions that are still pending verification', function () {
    // Matches: ended + pending.
    LiveSession::factory()->count(2)->create([
        'status' => 'ended',
        'verification_status' => 'pending',
    ]);
    // Excluded: verified, rejected, still-scheduled, missed.
    LiveSession::factory()->create(['status' => 'ended', 'verification_status' => 'verified']);
    LiveSession::factory()->create(['status' => 'ended', 'verification_status' => 'rejected']);
    LiveSession::factory()->create(['status' => 'scheduled', 'verification_status' => 'pending']);
    LiveSession::factory()->create(['status' => 'missed', 'verification_status' => 'pending']);

    actingAs($this->pic)
        ->get('/livehost/sessions?tab=needs_review')
        ->assertInertia(fn (Assert $p) => $p
            ->has('sessions.data', 2)
            ->where('filters.tab', 'needs_review'));
});

it('exposes per-tab counts for the tab strip badges', function () {
    LiveSession::factory()->count(2)->create([
        'status' => 'ended',
        'verification_status' => 'pending',
    ]);
    LiveSession::factory()->create(['verification_status' => 'verified']);
    LiveSession::factory()->count(3)->create(['verification_status' => 'rejected']);

    actingAs($this->pic)
        ->get('/livehost/sessions')
        ->assertInertia(fn (Assert $p) => $p
            ->where('tabCounts.all', 6)
            ->where('tabCounts.needs_review', 2)
            ->where('tabCounts.verified', 1)
            ->where('tabCounts.rejected', 3));
});

it('forbids live_host from updating a session', function () {
    $host = User::factory()->create(['role' => 'live_host']);
    $session = LiveSession::factory()->create();

    actingAs($host)
        ->put("/livehost/sessions/{$session->id}", [
            'status' => 'ended',
        ])
        ->assertForbidden();
});
