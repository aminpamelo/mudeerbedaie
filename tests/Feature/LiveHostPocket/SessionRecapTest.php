<?php

use App\Models\LiveAnalytics;
use App\Models\LiveSession;
use App\Models\LiveSessionAttachment;
use App\Models\PlatformAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');

    $this->host = User::factory()->create(['role' => 'live_host']);
    $this->session = LiveSession::factory()
        ->for(PlatformAccount::factory())
        ->create([
            'live_host_id' => $this->host->id,
            'status' => 'scheduled',
            'scheduled_start_at' => now()->subHours(2),
        ]);
});

it('rejects went_live=true without any image or video attachment', function () {
    actingAs($this->host);

    $response = postJson("/live-host/sessions/{$this->session->id}/recap", [
        'went_live' => true,
        'actual_start_at' => now()->subHours(2)->toIso8601String(),
        'actual_end_at' => now()->subHour()->toIso8601String(),
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['proof']);
    expect($this->session->fresh()->status)->toBe('scheduled');
});

it('accepts went_live=true with an image attachment and flips status to ended', function () {
    actingAs($this->host);

    LiveSessionAttachment::factory()->tiktokShopScreenshot()->create([
        'live_session_id' => $this->session->id,
    ]);

    $response = postJson("/live-host/sessions/{$this->session->id}/recap", [
        'went_live' => true,
        'gmv_amount' => 750.00,
        'actual_start_at' => now()->subHours(2)->toIso8601String(),
        'actual_end_at' => now()->subHour()->toIso8601String(),
        'viewers_peak' => 42,
    ]);

    $response->assertRedirect();
    expect($this->session->fresh()->status)->toBe('ended');
});

it('accepts went_live=false with a valid reason code and flips status to missed', function () {
    actingAs($this->host);

    $response = postJson("/live-host/sessions/{$this->session->id}/recap", [
        'went_live' => false,
        'missed_reason_code' => 'tech_issue',
        'missed_reason_note' => 'Internet dropped at start time.',
    ]);

    $response->assertRedirect();
    $fresh = $this->session->fresh();
    expect($fresh->status)->toBe('missed');
    expect($fresh->missed_reason_code)->toBe('tech_issue');
    expect($fresh->missed_reason_note)->toBe('Internet dropped at start time.');
});

it('rejects went_live=false without a reason code', function () {
    actingAs($this->host);

    $response = postJson("/live-host/sessions/{$this->session->id}/recap", [
        'went_live' => false,
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['missed_reason_code']);
});

it('rejects went_live=false with an invalid reason code', function () {
    actingAs($this->host);

    $response = postJson("/live-host/sessions/{$this->session->id}/recap", [
        'went_live' => false,
        'missed_reason_code' => 'bogus',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['missed_reason_code']);
});

it('preserves analytics when flipping from missed back to went_live', function () {
    actingAs($this->host);

    // First, mark as missed.
    postJson("/live-host/sessions/{$this->session->id}/recap", [
        'went_live' => false,
        'missed_reason_code' => 'sick',
    ])->assertRedirect();

    // Seed analytics + attachment directly (simulating prior recap data kept on row).
    LiveSessionAttachment::factory()->create([
        'live_session_id' => $this->session->id,
        'file_type' => 'video/mp4',
    ]);
    LiveSessionAttachment::factory()->tiktokShopScreenshot()->create([
        'live_session_id' => $this->session->id,
    ]);
    LiveAnalytics::factory()->for($this->session, 'liveSession')->create(['viewers_peak' => 100]);

    // Now flip to went_live=true.
    postJson("/live-host/sessions/{$this->session->id}/recap", [
        'went_live' => true,
        'gmv_amount' => 320.00,
        'actual_start_at' => now()->subHours(2)->toIso8601String(),
        'actual_end_at' => now()->subHour()->toIso8601String(),
        'viewers_peak' => 150,
    ])->assertRedirect();

    $fresh = $this->session->fresh();
    expect($fresh->status)->toBe('ended');
    expect($fresh->missed_reason_code)->toBeNull();
    expect($fresh->missed_reason_note)->toBeNull();
    expect($fresh->analytics->viewers_peak)->toBe(150);
});

it('resets a submitted recap back to scheduled and wipes attachments, analytics and entered data', function () {
    actingAs($this->host);

    $this->session->update([
        'status' => 'ended',
        'gmv_source' => 'manual',
        'gmv_amount' => 500,
        'remarks' => 'Went well',
        'actual_start_at' => now()->subHours(2),
        'actual_end_at' => now()->subHour(),
        'uploaded_by' => $this->host->id,
        'uploaded_at' => now(),
    ]);

    $attachment = LiveSessionAttachment::factory()->tiktokShopScreenshot()->create([
        'live_session_id' => $this->session->id,
    ]);
    Storage::disk('public')->put($attachment->file_path, 'x');
    LiveAnalytics::factory()->for($this->session, 'liveSession')->create(['viewers_peak' => 99]);

    $response = $this->delete("/live-host/sessions/{$this->session->id}/recap");

    $response->assertRedirect();
    $fresh = $this->session->fresh();
    expect($fresh->status)->toBe('scheduled');
    expect($fresh->gmv_amount)->toBeNull();
    expect($fresh->remarks)->toBeNull();
    expect($fresh->actual_start_at)->toBeNull();
    expect($fresh->uploaded_by)->toBeNull();
    expect(LiveSessionAttachment::where('live_session_id', $this->session->id)->count())->toBe(0);
    expect(LiveAnalytics::where('live_session_id', $this->session->id)->count())->toBe(0);
    Storage::disk('public')->assertMissing($attachment->file_path);
});

it('clears PIC verification when resetting a verified recap', function () {
    actingAs($this->host);

    $this->session->update([
        'status' => 'ended',
        'gmv_source' => 'manual',
        'gmv_locked_at' => now(),
        'verified_at' => now(),
        'verified_by' => $this->host->id,
    ]);

    $this->delete("/live-host/sessions/{$this->session->id}/recap")->assertRedirect();

    $fresh = $this->session->fresh();
    expect($fresh->gmv_locked_at)->toBeNull();
    expect($fresh->verified_at)->toBeNull();
    expect($fresh->verified_by)->toBeNull();
});

it('refuses to reset a TikTok auto-recorded session', function () {
    actingAs($this->host);

    $this->session->update(['status' => 'ended', 'gmv_source' => 'tiktok_actual']);

    $this->delete("/live-host/sessions/{$this->session->id}/recap")->assertForbidden();
    expect($this->session->fresh()->status)->toBe('ended');
});

it('forbids resetting another host session', function () {
    $other = User::factory()->create(['role' => 'live_host']);
    actingAs($other);

    $this->session->update(['status' => 'ended', 'gmv_source' => 'manual']);

    $this->delete("/live-host/sessions/{$this->session->id}/recap")->assertForbidden();
});

it('exposes canReset and isVerified on the schedule month grid recap action', function () {
    actingAs($this->host);

    $this->session->update([
        'status' => 'ended',
        'gmv_source' => 'manual',
        'gmv_locked_at' => now(),
        'scheduled_start_at' => now()->startOfMonth()->addDays(2)->setTime(20, 0),
    ]);
    LiveSessionAttachment::factory()->tiktokShopScreenshot()->create([
        'live_session_id' => $this->session->id,
    ]);

    $response = $this->get('/live-host/schedule?month='.now()->format('Y-m'));
    $response->assertOk();

    $itemsByDate = $response->viewData('page')['props']['monthGrid']['itemsByDate'];
    $item = collect($itemsByDate)->flatten(1)->firstWhere('sessionId', $this->session->id);

    expect($item)->not->toBeNull();
    expect($item['recapAction']['session']['canReset'])->toBeTrue();
    expect($item['recapAction']['session']['isVerified'])->toBeTrue();
});

it('exposes canRecap=true on scheduled sessions past their start time', function () {
    actingAs($this->host);

    $response = $this->get('/live-host/sessions?filter=upcoming');
    $response->assertOk();

    $props = $response->viewData('page')['props'];
    $row = collect($props['sessions']['data'])->firstWhere('id', $this->session->id);

    expect($row)->not->toBeNull();
    expect($row['canRecap'])->toBeTrue();
});

it('exposes canRecap=false on scheduled sessions still in the future', function () {
    actingAs($this->host);

    $future = LiveSession::factory()
        ->for(PlatformAccount::factory())
        ->create([
            'live_host_id' => $this->host->id,
            'status' => 'scheduled',
            'scheduled_start_at' => now()->addHours(5),
        ]);

    $response = $this->get('/live-host/sessions?filter=upcoming');
    $props = $response->viewData('page')['props'];
    $row = collect($props['sessions']['data'])->firstWhere('id', $future->id);

    expect($row['canRecap'])->toBeFalse();
});
