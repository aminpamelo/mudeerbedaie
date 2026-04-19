<?php

use App\Models\LiveHostPlatformAccount;
use App\Models\PlatformAccount;
use App\Models\User;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->pic = User::factory()->create(['role' => 'admin_livehost']);
    $this->host = User::factory()->create(['role' => 'live_host']);
    $this->account = PlatformAccount::factory()->create();
});

it('admin can attach a host to a platform account with creator identity', function () {
    actingAs($this->pic)
        ->post("/livehost/hosts/{$this->host->id}/platform-accounts/{$this->account->id}", [
            'creator_handle' => '@amar',
            'creator_platform_user_id' => '6526684195492729856',
            'is_primary' => true,
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $pivot = LiveHostPlatformAccount::query()
        ->where('user_id', $this->host->id)
        ->where('platform_account_id', $this->account->id)
        ->firstOrFail();

    expect($pivot->creator_handle)->toBe('@amar')
        ->and($pivot->creator_platform_user_id)->toBe('6526684195492729856')
        ->and($pivot->is_primary)->toBeTrue();
});

it('re-attaching the same (host, platform_account) pair updates the existing pivot in place', function () {
    actingAs($this->pic)
        ->post("/livehost/hosts/{$this->host->id}/platform-accounts/{$this->account->id}", [
            'creator_handle' => '@old',
            'creator_platform_user_id' => '111',
            'is_primary' => false,
        ])
        ->assertRedirect();

    actingAs($this->pic)
        ->post("/livehost/hosts/{$this->host->id}/platform-accounts/{$this->account->id}", [
            'creator_handle' => '@new',
            'creator_platform_user_id' => '222',
            'is_primary' => true,
        ])
        ->assertRedirect();

    expect(LiveHostPlatformAccount::query()
        ->where('user_id', $this->host->id)
        ->where('platform_account_id', $this->account->id)
        ->count())->toBe(1);

    $pivot = LiveHostPlatformAccount::query()
        ->where('user_id', $this->host->id)
        ->where('platform_account_id', $this->account->id)
        ->firstOrFail();
    expect($pivot->creator_handle)->toBe('@new')
        ->and($pivot->creator_platform_user_id)->toBe('222')
        ->and($pivot->is_primary)->toBeTrue();
});

it('setting is_primary=true on one pivot demotes other primary pivots for the same host', function () {
    $accountB = PlatformAccount::factory()->create();

    $pivotA = LiveHostPlatformAccount::create([
        'user_id' => $this->host->id,
        'platform_account_id' => $this->account->id,
        'creator_handle' => '@a',
        'is_primary' => true,
    ]);
    $pivotB = LiveHostPlatformAccount::create([
        'user_id' => $this->host->id,
        'platform_account_id' => $accountB->id,
        'creator_handle' => '@b',
        'is_primary' => false,
    ]);

    actingAs($this->pic)
        ->patch("/livehost/hosts/{$this->host->id}/platform-accounts/{$accountB->id}", [
            'is_primary' => true,
        ])
        ->assertRedirect();

    expect($pivotA->fresh()->is_primary)->toBeFalse()
        ->and($pivotB->fresh()->is_primary)->toBeTrue();
});

it('admin can detach a host from a platform account', function () {
    LiveHostPlatformAccount::create([
        'user_id' => $this->host->id,
        'platform_account_id' => $this->account->id,
        'creator_handle' => '@x',
    ]);

    actingAs($this->pic)
        ->delete("/livehost/hosts/{$this->host->id}/platform-accounts/{$this->account->id}")
        ->assertRedirect();

    expect(LiveHostPlatformAccount::query()
        ->where('user_id', $this->host->id)
        ->where('platform_account_id', $this->account->id)
        ->exists())->toBeFalse();
});

it('admin can update creator fields without touching is_primary', function () {
    $pivot = LiveHostPlatformAccount::create([
        'user_id' => $this->host->id,
        'platform_account_id' => $this->account->id,
        'creator_handle' => '@old',
        'creator_platform_user_id' => '111',
        'is_primary' => true,
    ]);

    actingAs($this->pic)
        ->patch("/livehost/hosts/{$this->host->id}/platform-accounts/{$this->account->id}", [
            'creator_handle' => '@renamed',
            'creator_platform_user_id' => '999',
        ])
        ->assertRedirect();

    $fresh = $pivot->fresh();
    expect($fresh->creator_handle)->toBe('@renamed')
        ->and($fresh->creator_platform_user_id)->toBe('999')
        ->and($fresh->is_primary)->toBeTrue();
});

it('live_host role cannot attach a host to a platform account', function () {
    $other = User::factory()->create(['role' => 'live_host']);

    actingAs($other)
        ->post("/livehost/hosts/{$this->host->id}/platform-accounts/{$this->account->id}", [
            'creator_handle' => '@amar',
            'is_primary' => true,
        ])
        ->assertForbidden();

    expect(LiveHostPlatformAccount::query()
        ->where('user_id', $this->host->id)
        ->where('platform_account_id', $this->account->id)
        ->exists())->toBeFalse();
});

it('live_host role cannot update pivot fields', function () {
    LiveHostPlatformAccount::create([
        'user_id' => $this->host->id,
        'platform_account_id' => $this->account->id,
        'creator_handle' => '@existing',
    ]);

    $other = User::factory()->create(['role' => 'live_host']);

    actingAs($other)
        ->patch("/livehost/hosts/{$this->host->id}/platform-accounts/{$this->account->id}", [
            'creator_handle' => '@hijack',
        ])
        ->assertForbidden();
});

it('returns 404 when attaching to a user who is not a live_host', function () {
    $teacher = User::factory()->create(['role' => 'teacher']);

    actingAs($this->pic)
        ->post("/livehost/hosts/{$teacher->id}/platform-accounts/{$this->account->id}", [
            'creator_handle' => '@nope',
        ])
        ->assertNotFound();
});
