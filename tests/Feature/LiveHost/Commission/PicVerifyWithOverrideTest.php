<?php

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

it('applies gmv_amount_override before verification so snapshot uses overridden GMV', function () {
    $session = makePicVerifySession($this->ahmad, $this->tiktok, [
        'gmv_amount' => 500,
    ]);

    actingAs($this->pic)
        ->post("/livehost/sessions/{$session->id}/verify", [
            'verification_status' => 'verified',
            'gmv_amount_override' => 888,
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $session->refresh();

    expect((float) $session->gmv_amount)->toBe(888.0);
    expect($session->gmv_locked_at)->not->toBeNull();
    expect($session->commission_snapshot_json)->toBeArray();

    $snapshot = $session->commission_snapshot_json;
    expect($snapshot)->toHaveKey('gmv_commission');
    expect((float) $snapshot['net_gmv'])->toBe(888.0);

    $expected = round(888.0 * ((float) $snapshot['platform_rate_percent'] / 100), 2);
    expect((float) $snapshot['gmv_commission'])->toBe($expected);
});

it('preserves existing gmv_amount when override is not provided', function () {
    $session = makePicVerifySession($this->ahmad, $this->tiktok, [
        'gmv_amount' => 1234.56,
    ]);

    actingAs($this->pic)
        ->post("/livehost/sessions/{$session->id}/verify", [
            'verification_status' => 'verified',
        ])
        ->assertRedirect();

    $session->refresh();
    expect((float) $session->gmv_amount)->toBe(1234.56);
    expect($session->gmv_locked_at)->not->toBeNull();
    expect((float) $session->commission_snapshot_json['net_gmv'])->toBe(1234.56);
});

it('allows an explicit zero override for missed or adjusted sessions', function () {
    $session = makePicVerifySession($this->ahmad, $this->tiktok, [
        'gmv_amount' => 1000,
    ]);

    actingAs($this->pic)
        ->post("/livehost/sessions/{$session->id}/verify", [
            'verification_status' => 'verified',
            'gmv_amount_override' => 0,
        ])
        ->assertRedirect();

    $session->refresh();
    expect((float) $session->gmv_amount)->toBe(0.0);
    expect($session->gmv_locked_at)->not->toBeNull();
    expect((float) $session->commission_snapshot_json['net_gmv'])->toBe(0.0);
});

it('forbids live_host from verifying a session with override', function () {
    $host = User::factory()->create(['role' => 'live_host']);
    $session = makePicVerifySession($this->ahmad, $this->tiktok, [
        'gmv_amount' => 500,
    ]);

    actingAs($host)
        ->post("/livehost/sessions/{$session->id}/verify", [
            'verification_status' => 'verified',
            'gmv_amount_override' => 888,
        ])
        ->assertForbidden();

    $session->refresh();
    expect($session->verification_status)->toBe('pending');
    expect((float) $session->gmv_amount)->toBe(500.0);
});

it('rejects negative gmv_amount_override values', function () {
    $session = makePicVerifySession($this->ahmad, $this->tiktok, [
        'gmv_amount' => 500,
    ]);

    actingAs($this->pic)
        ->post("/livehost/sessions/{$session->id}/verify", [
            'verification_status' => 'verified',
            'gmv_amount_override' => -10,
        ])
        ->assertSessionHasErrors('gmv_amount_override');

    $session->refresh();
    expect($session->verification_status)->toBe('pending');
});
