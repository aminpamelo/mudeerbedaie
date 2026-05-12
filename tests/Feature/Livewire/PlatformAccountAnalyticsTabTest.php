<?php

declare(strict_types=1);

use App\Jobs\SyncTikTokLive;
use App\Models\Platform;
use App\Models\PlatformAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Volt\Volt;

uses(RefreshDatabase::class);

it('dispatches SyncTikTokLive when the LIVE sync button is clicked', function () {
    Queue::fake();

    $admin = User::factory()->admin()->create();
    $platform = Platform::factory()->create(['slug' => 'tiktok-shop']);
    $account = PlatformAccount::factory()->for($platform)->create(['is_active' => true]);

    $this->actingAs($admin);

    Volt::test('admin.platforms.accounts.show', ['platform' => $platform, 'account' => $account])
        ->call('syncLiveNow')
        ->assertHasNoErrors();

    Queue::assertPushed(SyncTikTokLive::class, function ($job) use ($account) {
        return $job->account->is($account);
    });
});

it('refuses to dispatch when the account is inactive', function () {
    Queue::fake();

    $admin = User::factory()->admin()->create();
    $platform = Platform::factory()->create(['slug' => 'tiktok-shop']);
    $account = PlatformAccount::factory()->for($platform)->create(['is_active' => false]);

    $this->actingAs($admin);

    Volt::test('admin.platforms.accounts.show', ['platform' => $platform, 'account' => $account])
        ->call('syncLiveNow');

    Queue::assertNotPushed(SyncTikTokLive::class);
});

it('refuses to dispatch when the account is not a TikTok Shop', function () {
    Queue::fake();

    $admin = User::factory()->admin()->create();
    $platform = Platform::factory()->create(['slug' => 'shopify']);
    $account = PlatformAccount::factory()->for($platform)->create(['is_active' => true]);

    $this->actingAs($admin);

    Volt::test('admin.platforms.accounts.show', ['platform' => $platform, 'account' => $account])
        ->call('syncLiveNow');

    Queue::assertNotPushed(SyncTikTokLive::class);
});
