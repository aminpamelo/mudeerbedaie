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

it('allows adding a second creator with a different creator_platform_user_id on the same (host, platform account) pair', function () {
    actingAs($this->pic)
        ->post(route('livehost.creators.store'), [
            'user_id' => $this->host->id,
            'platform_account_id' => $this->account->id,
            'creator_handle' => 'amar',
            'creator_platform_user_id' => '111',
            'is_primary' => true,
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    actingAs($this->pic)
        ->post(route('livehost.creators.store'), [
            'user_id' => $this->host->id,
            'platform_account_id' => $this->account->id,
            'creator_handle' => 'amar-alt',
            'creator_platform_user_id' => '222',
            'is_primary' => false,
        ])
        ->assertRedirect()
        ->assertSessionHas('success')
        ->assertSessionHasNoErrors();

    expect(LiveHostPlatformAccount::query()
        ->where('user_id', $this->host->id)
        ->where('platform_account_id', $this->account->id)
        ->count())->toBe(2);
});

it('allows adding two creators with the same creator_platform_user_id on the same (host, platform account) pair', function () {
    actingAs($this->pic)
        ->post(route('livehost.creators.store'), [
            'user_id' => $this->host->id,
            'platform_account_id' => $this->account->id,
            'creator_handle' => 'amar',
            'creator_platform_user_id' => '111',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    actingAs($this->pic)
        ->post(route('livehost.creators.store'), [
            'user_id' => $this->host->id,
            'platform_account_id' => $this->account->id,
            'creator_handle' => 'amar-dup',
            'creator_platform_user_id' => '111',
        ])
        ->assertRedirect()
        ->assertSessionHas('success')
        ->assertSessionHasNoErrors();

    expect(LiveHostPlatformAccount::query()
        ->where('user_id', $this->host->id)
        ->where('platform_account_id', $this->account->id)
        ->count())->toBe(2);
});

it('marking a new creator as primary demotes other primaries for the same host across all platform accounts', function () {
    $existing = LiveHostPlatformAccount::create([
        'user_id' => $this->host->id,
        'platform_account_id' => $this->account->id,
        'creator_handle' => 'amar-main',
        'creator_platform_user_id' => '111',
        'is_primary' => true,
    ]);

    actingAs($this->pic)
        ->post(route('livehost.creators.store'), [
            'user_id' => $this->host->id,
            'platform_account_id' => $this->account->id,
            'creator_handle' => 'amar-alt',
            'creator_platform_user_id' => '222',
            'is_primary' => true,
        ])
        ->assertRedirect();

    expect($existing->fresh()->is_primary)->toBeFalse();
    expect(LiveHostPlatformAccount::query()
        ->where('user_id', $this->host->id)
        ->where('platform_account_id', $this->account->id)
        ->where('creator_platform_user_id', '222')
        ->value('is_primary'))->toBeTrue();
});
