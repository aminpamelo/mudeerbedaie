<?php

use App\Models\LiveSession;
use App\Models\LiveSessionAttachment;
use App\Models\PlatformAccount;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

/**
 * Browser coverage for the Live Host Pocket recap form (React page
 * `SessionDetail.jsx`).
 *
 * GMV, TikTok Shop screenshot, and Analytics inputs were removed from the
 * host-facing recap form: those metrics will be pulled from the TikTok API
 * in a later phase rather than collected manually. These tests pin that
 * absence and preserve coverage of the path switch + legacy attachment
 * badge rendering.
 */
it('omits GMV, TikTok Shop, and Analytics sections from the went-live recap form', function () {
    Storage::fake('public');

    $host = User::factory()->create(['role' => 'live_host']);
    $session = LiveSession::factory()
        ->for(PlatformAccount::factory())
        ->create([
            'live_host_id' => $host->id,
            'status' => 'scheduled',
            'scheduled_start_at' => now()->subHours(3),
            'title' => 'Evening stream',
        ]);

    $this->actingAs($host);

    // ?recap=yes pre-seeds the went_live branch — equivalent to the host
    // tapping "Yes, I went live" from the Sessions list card.
    $page = visit("/live-host/sessions/{$session->id}?recap=yes");

    $page
        ->assertNoJavascriptErrors()
        ->assertSee('Did you go live?')
        ->assertSee('Proof of live')
        ->assertSee('Timing')
        ->assertDontSee('GMV (RM)')
        ->assertDontSee('TikTok Shop screenshot')
        ->assertDontSee('We use this to verify your GMV number.')
        ->assertDontSee('Analytics')
        ->assertDontSee('Estimated earnings');
});

it('hides the went-live form entirely when recap=no seeds the missed path', function () {
    Storage::fake('public');

    $host = User::factory()->create(['role' => 'live_host']);
    $session = LiveSession::factory()
        ->for(PlatformAccount::factory())
        ->create([
            'live_host_id' => $host->id,
            'status' => 'scheduled',
            'scheduled_start_at' => now()->subHours(3),
            'title' => 'Evening stream',
        ]);

    $this->actingAs($host);

    $page = visit("/live-host/sessions/{$session->id}?recap=no");

    $page
        ->assertNoJavascriptErrors()
        ->assertSee("Why didn't you go live?")
        ->assertDontSee('Proof of live')
        ->assertDontSee('GMV (RM)')
        ->assertDontSee('TikTok Shop screenshot');
});

it('shows the TikTok Shop Screenshot badge on legacy attachments tagged as tiktok_shop_screenshot', function () {
    Storage::fake('public');

    $host = User::factory()->create(['role' => 'live_host']);
    $session = LiveSession::factory()
        ->for(PlatformAccount::factory())
        ->create([
            'live_host_id' => $host->id,
            'status' => 'scheduled',
            'scheduled_start_at' => now()->subHours(3),
        ]);

    LiveSessionAttachment::factory()
        ->tiktokShopScreenshot()
        ->create([
            'live_session_id' => $session->id,
            'uploaded_by' => $host->id,
            'file_name' => 'tiktok-shop-backend.png',
        ]);

    $this->actingAs($host);

    $page = visit("/live-host/sessions/{$session->id}?recap=yes");

    $page
        ->assertNoJavascriptErrors()
        ->assertSee('TikTok Shop Screenshot');
});
