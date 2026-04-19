<?php

use App\Models\LiveHostCommissionProfile;
use App\Models\LiveHostPlatformCommissionRate;
use App\Models\LiveSession;
use App\Models\LiveSessionAttachment;
use App\Models\Platform;
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

it('shows a real earnings estimate when commission props are present and GMV is entered', function () {
    // Task 11 shipped the commission block via Inertia shared props. With a
    // configured host and a GMV value, the preview should render the real
    // computed number (GMV * rate% + per-live) rather than the fallback.
    Storage::fake('public');

    $host = User::factory()->create(['role' => 'live_host']);
    LiveHostCommissionProfile::factory()->for($host)->create([
        'per_live_rate_myr' => 30.00,
    ]);
    $platform = Platform::where('slug', 'tiktok-shop')->first()
        ?? Platform::factory()->create(['slug' => 'tiktok-shop', 'name' => 'TikTok Shop']);
    LiveHostPlatformCommissionRate::factory()->for($host)->create([
        'platform_id' => $platform->id,
        'commission_rate_percent' => 4.00,
    ]);

    $session = LiveSession::factory()
        ->for(PlatformAccount::factory())
        ->create([
            'live_host_id' => $host->id,
            'status' => 'scheduled',
            'scheduled_start_at' => now()->subHours(3),
        ]);

    $this->actingAs($host);

    $page = visit("/live-host/sessions/{$session->id}?recap=yes");

    // Without a GMV value yet, we should see the "enter your GMV" nudge
    // (hasCommission=true path, but gmv <= 0).
    $page
        ->assertNoJavascriptErrors()
        ->assertSee('Enter your GMV above to see an earnings estimate.')
        ->assertDontSee('Earnings estimate unavailable');

    // Type a GMV value — with rate=4% and per-live=30, a GMV of 1000 should
    // produce 1000 * 0.04 + 30 = 70.00 total (RM 40.00 commission, RM 30.00
    // per-live).
    $page
        ->fill('gmv_amount', '1000')
        ->assertNoJavascriptErrors()
        ->assertSee('Estimated earnings')
        ->assertSee('RM 70.00')
        ->assertSee('RM 40.00 commission')
        ->assertSee('RM 30.00 per-live');
});
