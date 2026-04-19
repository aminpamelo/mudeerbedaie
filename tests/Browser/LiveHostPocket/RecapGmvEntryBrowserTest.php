<?php

use App\Models\LiveSession;
use App\Models\LiveSessionAttachment;
use App\Models\PlatformAccount;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

/**
 * Browser coverage for Task 10 — GMV field + TikTok Shop screenshot upload in
 * the Live Host Pocket recap form (React page `SessionDetail.jsx`).
 *
 * Task 9 landed the backend (SaveRecapRequest validates `gmv_amount`, the
 * after-hook requires a `tiktok_shop_screenshot` attachment, and the
 * attachments table gained the `attachment_type` column). This test exercises
 * the UI layer: the Yes/No path switch, conditional GMV input, dedicated
 * TikTok Shop dropzone, and the TikTok Shop Screenshot badge on listed
 * attachments.
 */
it('renders GMV input and TikTok Shop dropzone when recap=yes seeds the went-live path', function () {
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

    // ?recap=yes pre-seeds the went_live branch so the GMV + screenshot
    // sections render without an additional click — equivalent to the host
    // tapping "Yes, I went live" from the Sessions list card.
    $page = visit("/live-host/sessions/{$session->id}?recap=yes");

    $page
        ->assertNoJavascriptErrors()
        ->assertSee('Did you go live?')
        ->assertSee('GMV (RM)')
        ->assertSee('TikTok Shop screenshot')
        ->assertSee('We use this to verify your GMV number.');
});

it('hides GMV input and TikTok Shop dropzone when recap=no seeds the missed path', function () {
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

    // ?recap=no pre-seeds the missed branch — mirrors the host tapping
    // "Didn't go live" from the Sessions list card.
    $page = visit("/live-host/sessions/{$session->id}?recap=no");

    $page
        ->assertNoJavascriptErrors()
        ->assertSee("Why didn't you go live?")
        ->assertDontSee('GMV (RM)')
        ->assertDontSee('TikTok Shop screenshot');
});

it('shows the TikTok Shop Screenshot badge on attachments tagged as tiktok_shop_screenshot', function () {
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

it('shows a graceful fallback for earnings estimate when commission props are missing', function () {
    // Task 11 will ship commission rate + per-live rate in Inertia shared
    // props. Until then, the preview should soft-fail with the "ask PIC"
    // copy rather than throwing.
    Storage::fake('public');

    $host = User::factory()->create(['role' => 'live_host']);
    $session = LiveSession::factory()
        ->for(PlatformAccount::factory())
        ->create([
            'live_host_id' => $host->id,
            'status' => 'scheduled',
            'scheduled_start_at' => now()->subHours(3),
        ]);

    $this->actingAs($host);

    $page = visit("/live-host/sessions/{$session->id}?recap=yes");

    $page
        ->assertNoJavascriptErrors()
        ->assertSee('Earnings estimate unavailable');
});
