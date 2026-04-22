<?php

use App\Models\LiveSession;
use App\Models\PlatformAccount;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('host can mark a past-scheduled session as missed from the Sessions list', function () {
    Storage::fake('public');

    $host = User::factory()->create(['role' => 'live_host']);
    $session = LiveSession::factory()
        ->for(PlatformAccount::factory())
        ->create([
            'live_host_id' => $host->id,
            'status' => 'scheduled',
            'scheduled_start_at' => now()->subHours(3),
            'title' => 'Tonight stream',
        ]);

    $this->actingAs($host);

    // Entry point: Upcoming tab surfaces past-scheduled sessions as
    // "RECAP PENDING" with a "Didn't go live" CTA link. Clicking it
    // navigates to the session detail with ?recap=no, which pre-seeds
    // the missed-path form.
    $page = visit('/live-host/sessions?filter=upcoming');

    $page
        ->assertNoJavascriptErrors()
        ->assertSee('Tonight stream')
        ->assertSee('RECAP PENDING')
        ->click("Didn't go live")
        ->assertSee("Why didn't you go live?")
        ->click('Tech / connection issue')
        ->assertRadioSelected('missed_reason_code', 'tech_issue')
        ->press('Mark as missed')
        // Wait for Inertia's XHR POST + redirect + re-render to land before
        // asserting on the persisted row. Without this, the fresh() lookup
        // races the still-in-flight request.
        ->wait(2);

    expect($session->fresh()->status)->toBe('missed');
    expect($session->fresh()->missed_reason_code)->toBe('tech_issue');
});

it('host cannot save went_live recap without an image/video attachment', function () {
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

    // Entry point: ?recap=yes pre-seeds the went_live path, which
    // renders the Proof section. Without an image/video attachment
    // the `hasVisualProof` guard keeps the Save button disabled.
    $page = visit("/live-host/sessions/{$session->id}?recap=yes");

    $page
        ->assertNoJavascriptErrors()
        ->assertSee('Proof')
        ->assertSee('Proof required')
        ->assertButtonDisabled('Save recap');
});
