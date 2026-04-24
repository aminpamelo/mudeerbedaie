<?php

use App\Models\ActualLiveRecord;
use App\Models\LiveHostPlatformAccount;
use App\Models\LiveSession;
use App\Models\Platform;
use App\Models\PlatformAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/**
 * Build a pending session wired to a seeded host so the observer can snapshot
 * against a real platform commission rate when verified.
 *
 * @param  array<string, mixed>  $overrides
 */
function makePicVerifySession(User $host, Platform $platform, array $overrides = []): LiveSession
{
    $account = PlatformAccount::factory()->create([
        'platform_id' => $platform->id,
        'user_id' => $host->id,
    ]);

    $pivot = LiveHostPlatformAccount::create([
        'user_id' => $host->id,
        'platform_account_id' => $account->id,
        'is_primary' => true,
    ]);

    return LiveSession::factory()->create(array_merge([
        'platform_account_id' => $account->id,
        'live_host_platform_account_id' => $pivot->id,
        'live_host_id' => $host->id,
        'status' => 'ended',
        'verification_status' => 'pending',
        'gmv_locked_at' => null,
        'commission_snapshot_json' => null,
        'scheduled_start_at' => now()->subHour(),
        'actual_start_at' => now()->subHour(),
        'actual_end_at' => now()->subMinutes(10),
        'duration_minutes' => 50,
        'gmv_adjustment' => 0,
    ], $overrides));
}

beforeEach(function () {
    $this->seed(\Database\Seeders\LiveHostCommissionSeeder::class);
    $this->ahmad = User::where('email', 'ahmad@livehost.com')->first();
    $this->tiktok = Platform::where('slug', 'tiktok-shop')->firstOrFail();
    $this->pic = User::factory()->create(['role' => 'admin_livehost']);
});

it('blocks status=verified on the verify endpoint — PIC must use verify-link', function () {
    $session = makePicVerifySession($this->ahmad, $this->tiktok, [
        'gmv_amount' => 500,
    ]);

    actingAs($this->pic)
        ->post("/livehost/sessions/{$session->id}/verify", [
            'verification_status' => 'verified',
        ])
        ->assertStatus(422);

    $session->refresh();
    expect($session->verification_status)->toBe('pending');
    expect($session->gmv_locked_at)->toBeNull();
    expect($session->commission_snapshot_json)->toBeNull();
});

it('locks GMV against the linked record when verified via verify-link', function () {
    $session = makePicVerifySession($this->ahmad, $this->tiktok, [
        'gmv_amount' => 500,
    ]);
    $pivot = $session->liveHostPlatformAccount;
    $record = ActualLiveRecord::factory()->create([
        'platform_account_id' => $session->platform_account_id,
        'creator_platform_user_id' => $pivot->creator_platform_user_id,
        'live_attributed_gmv_myr' => 888,
    ]);

    actingAs($this->pic)
        ->post("/livehost/sessions/{$session->id}/verify-link", [
            'actual_live_record_id' => $record->id,
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $session->refresh();

    expect($session->verification_status)->toBe('verified');
    expect((float) $session->gmv_amount)->toBe(888.0);
    expect($session->gmv_source)->toBe('tiktok_actual');
    expect($session->gmv_locked_at)->not->toBeNull();
    expect($session->matched_actual_live_record_id)->toBe($record->id);
});

it('forbids live_host from verifying a session via verify endpoint', function () {
    $host = User::factory()->create(['role' => 'live_host']);
    $session = makePicVerifySession($this->ahmad, $this->tiktok, [
        'gmv_amount' => 500,
    ]);

    actingAs($host)
        ->post("/livehost/sessions/{$session->id}/verify", [
            'verification_status' => 'rejected',
        ])
        ->assertForbidden();

    $session->refresh();
    expect($session->verification_status)->toBe('pending');
    expect((float) $session->gmv_amount)->toBe(500.0);
});

it('forbids live_host from verifying a session via verify-link', function () {
    $host = User::factory()->create(['role' => 'live_host']);
    $session = makePicVerifySession($this->ahmad, $this->tiktok, [
        'gmv_amount' => 500,
    ]);
    $pivot = $session->liveHostPlatformAccount;
    $record = ActualLiveRecord::factory()->create([
        'platform_account_id' => $session->platform_account_id,
        'creator_platform_user_id' => $pivot->creator_platform_user_id,
        'live_attributed_gmv_myr' => 888,
    ]);

    actingAs($host)
        ->post("/livehost/sessions/{$session->id}/verify-link", [
            'actual_live_record_id' => $record->id,
        ])
        ->assertForbidden();

    $session->refresh();
    expect($session->verification_status)->toBe('pending');
});
