<?php

use App\Models\LiveHostPlatformCommissionRate;
use App\Models\Platform;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->pic = User::factory()->create(['role' => 'admin_livehost']);
    $this->host = User::factory()->create(['role' => 'live_host']);
    $this->platform = Platform::factory()->create(['slug' => 'tiktok-shop']);
});

it('PIC can add a TikTok rate of 4 percent and row is active', function () {
    actingAs($this->pic)
        ->post("/livehost/hosts/{$this->host->id}/platform-rates", [
            'platform_id' => $this->platform->id,
            'commission_rate_percent' => 4,
        ])
        ->assertRedirect();

    $rate = LiveHostPlatformCommissionRate::query()
        ->where('user_id', $this->host->id)
        ->where('platform_id', $this->platform->id)
        ->where('is_active', true)
        ->firstOrFail();

    expect((float) $rate->commission_rate_percent)->toBe(4.0);
    expect($rate->effective_from)->not->toBeNull();
});

it('updating a rate deactivates the prior row and creates a new active one', function () {
    $existing = LiveHostPlatformCommissionRate::factory()->create([
        'user_id' => $this->host->id,
        'platform_id' => $this->platform->id,
        'commission_rate_percent' => 4,
        'is_active' => true,
        'effective_from' => now()->subDay(),
    ]);

    actingAs($this->pic)
        ->put("/livehost/hosts/{$this->host->id}/platform-rates/{$existing->id}", [
            'platform_id' => $this->platform->id,
            'commission_rate_percent' => 6,
        ])
        ->assertRedirect();

    $existing->refresh();
    expect($existing->is_active)->toBeFalse();
    expect($existing->effective_to)->not->toBeNull();

    $active = LiveHostPlatformCommissionRate::query()
        ->where('user_id', $this->host->id)
        ->where('platform_id', $this->platform->id)
        ->where('is_active', true)
        ->firstOrFail();
    expect((float) $active->commission_rate_percent)->toBe(6.0);
    expect($active->id)->not->toBe($existing->id);
});

it('live_host role cannot add a platform rate (403)', function () {
    $other = User::factory()->create(['role' => 'live_host']);

    actingAs($other)
        ->post("/livehost/hosts/{$this->host->id}/platform-rates", [
            'platform_id' => $this->platform->id,
            'commission_rate_percent' => 4,
        ])
        ->assertForbidden();

    expect(LiveHostPlatformCommissionRate::where('user_id', $this->host->id)->count())->toBe(0);
});

it('rejects an invalid platform_id with 422', function () {
    actingAs($this->pic)
        ->post("/livehost/hosts/{$this->host->id}/platform-rates", [
            'platform_id' => 999999,
            'commission_rate_percent' => 4,
        ])
        ->assertSessionHasErrors('platform_id');
});

it('rejects commission_rate_percent greater than 100 with 422', function () {
    actingAs($this->pic)
        ->post("/livehost/hosts/{$this->host->id}/platform-rates", [
            'platform_id' => $this->platform->id,
            'commission_rate_percent' => 150,
        ])
        ->assertSessionHasErrors('commission_rate_percent');
});
