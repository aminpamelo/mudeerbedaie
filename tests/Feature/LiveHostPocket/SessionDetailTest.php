<?php

use App\Models\LiveAnalytics;
use App\Models\LiveSession;
use App\Models\LiveSessionAttachment;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->host = User::factory()->create(['role' => 'live_host']);
    $this->session = LiveSession::factory()->create([
        'live_host_id' => $this->host->id,
        'status' => 'ended',
        'title' => 'Morning Live — Skincare',
    ]);
});

it('renders the session detail page for the owning host', function () {
    actingAs($this->host)
        ->get("/live-host/sessions/{$this->session->id}")
        ->assertSuccessful()
        ->assertInertia(fn (Assert $p) => $p
            ->component('SessionDetail', false)
            ->where('session.id', $this->session->id)
            ->where('session.title', 'Morning Live — Skincare')
            ->where('analytics', null)
            ->has('attachments', 0));
});

it('forbids another host from viewing the session detail', function () {
    $other = User::factory()->create(['role' => 'live_host']);

    actingAs($other)
        ->get("/live-host/sessions/{$this->session->id}")
        ->assertForbidden();
});

it('includes analytics + attachments in DTO when present', function () {
    LiveAnalytics::factory()->create([
        'live_session_id' => $this->session->id,
        'viewers_peak' => 387,
        'viewers_avg' => 214,
        'total_likes' => 1284,
        'gifts_value' => 86.50,
    ]);
    LiveSessionAttachment::factory()->create([
        'live_session_id' => $this->session->id,
        'uploaded_by' => $this->host->id,
        'file_name' => 'screen-recap.png',
    ]);

    actingAs($this->host)
        ->get("/live-host/sessions/{$this->session->id}")
        ->assertInertia(fn (Assert $p) => $p
            ->where('analytics.viewersPeak', 387)
            ->where('analytics.totalLikes', 1284)
            ->where('analytics.giftsValue', 86.5)
            ->has('attachments', 1)
            ->where('attachments.0.fileName', 'screen-recap.png'));
});

it('saves recap + upserts analytics for the owning host', function () {
    LiveSessionAttachment::factory()->create([
        'live_session_id' => $this->session->id,
        'uploaded_by' => $this->host->id,
        'file_type' => 'image/png',
    ]);

    actingAs($this->host)
        ->post("/live-host/sessions/{$this->session->id}/recap", [
            'went_live' => true,
            'remarks' => 'Strong engagement on the Hydro range.',
            'viewers_peak' => 412,
            'viewers_avg' => 210,
            'total_likes' => 1300,
            'total_comments' => 80,
            'total_shares' => 12,
            'gifts_value' => 99.99,
        ])
        ->assertRedirect("/live-host/sessions/{$this->session->id}");

    $this->session->refresh();
    expect($this->session->remarks)->toBe('Strong engagement on the Hydro range.');
    expect($this->session->uploaded_at)->not->toBeNull();
    expect($this->session->uploaded_by)->toBe($this->host->id);

    $analytics = LiveAnalytics::where('live_session_id', $this->session->id)->first();
    expect($analytics->viewers_peak)->toBe(412);
    expect($analytics->total_likes)->toBe(1300);
    expect((float) $analytics->gifts_value)->toBe(99.99);
});

it('upserts existing analytics rather than duplicating', function () {
    LiveAnalytics::factory()->create([
        'live_session_id' => $this->session->id,
        'viewers_peak' => 100,
    ]);
    LiveSessionAttachment::factory()->create([
        'live_session_id' => $this->session->id,
        'uploaded_by' => $this->host->id,
        'file_type' => 'image/png',
    ]);

    actingAs($this->host)
        ->post("/live-host/sessions/{$this->session->id}/recap", [
            'went_live' => true,
            'viewers_peak' => 500,
        ]);

    expect(LiveAnalytics::where('live_session_id', $this->session->id)->count())->toBe(1);
    expect(LiveAnalytics::where('live_session_id', $this->session->id)->first()->viewers_peak)->toBe(500);
});

it('forbids another host from saving a recap', function () {
    $other = User::factory()->create(['role' => 'live_host']);

    actingAs($other)
        ->post("/live-host/sessions/{$this->session->id}/recap", [
            'went_live' => false,
            'missed_reason_code' => 'other',
        ])
        ->assertForbidden();
});

it('validates recap: rejects negative viewers_peak', function () {
    actingAs($this->host)
        ->post("/live-host/sessions/{$this->session->id}/recap", [
            'went_live' => true,
            'viewers_peak' => -1,
        ])
        ->assertSessionHasErrors('viewers_peak');
});

it('validates recap: rejects actual_end_at before actual_start_at', function () {
    actingAs($this->host)
        ->post("/live-host/sessions/{$this->session->id}/recap", [
            'went_live' => true,
            'actual_start_at' => '2026-04-18 12:00:00',
            'actual_end_at' => '2026-04-18 11:00:00',
        ])
        ->assertSessionHasErrors('actual_end_at');
});

it('stores uploaded cover image on public disk', function () {
    Storage::fake('public');

    LiveSessionAttachment::factory()->create([
        'live_session_id' => $this->session->id,
        'uploaded_by' => $this->host->id,
        'file_type' => 'image/png',
    ]);

    actingAs($this->host)
        ->post("/live-host/sessions/{$this->session->id}/recap", [
            'went_live' => true,
            'cover_image' => UploadedFile::fake()->image('cover.jpg'),
        ])
        ->assertRedirect();

    $this->session->refresh();
    expect($this->session->image_path)->not->toBeNull();
    Storage::disk('public')->assertExists($this->session->image_path);
});

it('adds an attachment and stores the file on public disk', function () {
    Storage::fake('public');

    $file = UploadedFile::fake()->create('notes.pdf', 100, 'application/pdf');

    actingAs($this->host)
        ->post("/live-host/sessions/{$this->session->id}/attachments", [
            'file' => $file,
            'description' => 'Post-session notes.',
        ])
        ->assertRedirect("/live-host/sessions/{$this->session->id}");

    $attachment = LiveSessionAttachment::where('live_session_id', $this->session->id)->first();
    expect($attachment)->not->toBeNull();
    expect($attachment->file_name)->toBe('notes.pdf');
    expect($attachment->file_type)->toBe('application/pdf');
    expect($attachment->description)->toBe('Post-session notes.');
    Storage::disk('public')->assertExists($attachment->file_path);
});

it('forbids another host from uploading an attachment', function () {
    Storage::fake('public');
    $other = User::factory()->create(['role' => 'live_host']);

    actingAs($other)
        ->post("/live-host/sessions/{$this->session->id}/attachments", [
            'file' => UploadedFile::fake()->create('x.pdf', 10),
        ])
        ->assertForbidden();
});

it('deletes an attachment and removes the file', function () {
    Storage::fake('public');

    $file = UploadedFile::fake()->create('notes.pdf', 100, 'application/pdf');
    actingAs($this->host)->post("/live-host/sessions/{$this->session->id}/attachments", [
        'file' => $file,
    ]);

    $attachment = LiveSessionAttachment::where('live_session_id', $this->session->id)->firstOrFail();
    $path = $attachment->file_path;

    actingAs($this->host)
        ->delete("/live-host/sessions/{$this->session->id}/attachments/{$attachment->id}")
        ->assertRedirect();

    expect(LiveSessionAttachment::find($attachment->id))->toBeNull();
    Storage::disk('public')->assertMissing($path);
});

it('returns 404 when deleting an attachment that belongs to another session', function () {
    $otherSession = LiveSession::factory()->create([
        'live_host_id' => $this->host->id,
        'status' => 'ended',
    ]);
    $attachment = LiveSessionAttachment::factory()->create([
        'live_session_id' => $otherSession->id,
        'uploaded_by' => $this->host->id,
    ]);

    actingAs($this->host)
        ->delete("/live-host/sessions/{$this->session->id}/attachments/{$attachment->id}")
        ->assertNotFound();
});

it('forbids another host from deleting an attachment', function () {
    $other = User::factory()->create(['role' => 'live_host']);
    $attachment = LiveSessionAttachment::factory()->create([
        'live_session_id' => $this->session->id,
        'uploaded_by' => $this->host->id,
    ]);

    actingAs($other)
        ->delete("/live-host/sessions/{$this->session->id}/attachments/{$attachment->id}")
        ->assertForbidden();
});

it('requires auth to view session detail', function () {
    $this->get("/live-host/sessions/{$this->session->id}")
        ->assertRedirect('/login');
});
