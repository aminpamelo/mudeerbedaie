<?php

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

it('accepts recap with went_live=true, GMV, and tiktok_shop_screenshot attachment', function () {
    LiveSessionAttachment::factory()->create([
        'live_session_id' => $this->session->id,
        'uploaded_by' => $this->host->id,
        'file_type' => 'image/png',
        'attachment_type' => LiveSessionAttachment::TYPE_TIKTOK_SHOP_SCREENSHOT,
    ]);

    actingAs($this->host);

    $response = postJson("/live-host/sessions/{$this->session->id}/recap", [
        'went_live' => true,
        'gmv_amount' => 1500.50,
        'actual_start_at' => now()->subHours(2)->toIso8601String(),
        'actual_end_at' => now()->subHour()->toIso8601String(),
    ]);

    $response->assertRedirect();
    $response->assertSessionHasNoErrors();

    $fresh = $this->session->fresh();
    expect((float) $fresh->gmv_amount)->toEqual(1500.50);
    expect($fresh->gmv_source)->toBe('manual');
    expect($fresh->gmv_locked_at)->toBeNull();
    expect($fresh->status)->toBe('ended');
});

it('accepts recap when went_live=true and gmv_amount is omitted', function () {
    // GMV is no longer collected from the host — it will be pulled from the
    // TikTok API in a later phase. Visual proof is still required.
    LiveSessionAttachment::factory()->create([
        'live_session_id' => $this->session->id,
        'uploaded_by' => $this->host->id,
        'file_type' => 'image/png',
        'attachment_type' => null,
    ]);

    actingAs($this->host);

    $response = postJson("/live-host/sessions/{$this->session->id}/recap", [
        'went_live' => true,
    ]);

    $response->assertRedirect();
    $response->assertSessionHasNoErrors();

    $fresh = $this->session->fresh();
    expect($fresh->gmv_amount)->toBeNull();
    expect($fresh->status)->toBe('ended');
});

it('accepts recap when went_live=true without a tiktok_shop_screenshot attachment', function () {
    // The TikTok Shop backend screenshot is no longer collected — generic
    // visual proof is all that's needed.
    LiveSessionAttachment::factory()->create([
        'live_session_id' => $this->session->id,
        'uploaded_by' => $this->host->id,
        'file_type' => 'image/png',
        'attachment_type' => null,
    ]);

    actingAs($this->host);

    $response = postJson("/live-host/sessions/{$this->session->id}/recap", [
        'went_live' => true,
        'gmv_amount' => 500,
    ]);

    $response->assertRedirect();
    $response->assertSessionHasNoErrors();
});

it('forces gmv_amount=0 when went_live=false regardless of input', function () {
    actingAs($this->host);

    $response = postJson("/live-host/sessions/{$this->session->id}/recap", [
        'went_live' => false,
        'gmv_amount' => 9999,
        'missed_reason_code' => 'sick',
        'missed_reason_note' => 'Got sick',
    ]);

    $response->assertRedirect();
    $response->assertSessionHasNoErrors();

    $fresh = $this->session->fresh();
    expect((float) $fresh->gmv_amount)->toEqual(0.00);
    expect($fresh->gmv_source)->toBe('manual');
    expect($fresh->gmv_locked_at)->toBeNull();
    expect($fresh->status)->toBe('missed');
});

it('rejects negative gmv_amount', function () {
    LiveSessionAttachment::factory()->create([
        'live_session_id' => $this->session->id,
        'uploaded_by' => $this->host->id,
        'file_type' => 'image/png',
        'attachment_type' => LiveSessionAttachment::TYPE_TIKTOK_SHOP_SCREENSHOT,
    ]);

    actingAs($this->host);

    $response = postJson("/live-host/sessions/{$this->session->id}/recap", [
        'went_live' => true,
        'gmv_amount' => -50,
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('gmv_amount');
});

it('rejects gmv_amount above the sanity ceiling', function () {
    LiveSessionAttachment::factory()->create([
        'live_session_id' => $this->session->id,
        'uploaded_by' => $this->host->id,
        'file_type' => 'image/png',
        'attachment_type' => LiveSessionAttachment::TYPE_TIKTOK_SHOP_SCREENSHOT,
    ]);

    actingAs($this->host);

    $response = postJson("/live-host/sessions/{$this->session->id}/recap", [
        'went_live' => true,
        'gmv_amount' => 10_000_000, // above 9999999.99
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('gmv_amount');
});

it('rejects non-numeric gmv_amount', function () {
    LiveSessionAttachment::factory()->create([
        'live_session_id' => $this->session->id,
        'uploaded_by' => $this->host->id,
        'file_type' => 'image/png',
        'attachment_type' => LiveSessionAttachment::TYPE_TIKTOK_SHOP_SCREENSHOT,
    ]);

    actingAs($this->host);

    $response = postJson("/live-host/sessions/{$this->session->id}/recap", [
        'went_live' => true,
        'gmv_amount' => 'not-a-number',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('gmv_amount');
});

it('another host cannot submit recap for a session they do not own', function () {
    $otherHost = User::factory()->create(['role' => 'live_host']);
    LiveSessionAttachment::factory()->create([
        'live_session_id' => $this->session->id,
        'uploaded_by' => $this->host->id,
        'file_type' => 'image/png',
        'attachment_type' => LiveSessionAttachment::TYPE_TIKTOK_SHOP_SCREENSHOT,
    ]);

    actingAs($otherHost);

    $response = postJson("/live-host/sessions/{$this->session->id}/recap", [
        'went_live' => true,
        'gmv_amount' => 200,
    ]);

    $response->assertForbidden();

    $fresh = $this->session->fresh();
    expect($fresh->gmv_amount)->toBeNull();
    expect($fresh->status)->toBe('scheduled');
});
