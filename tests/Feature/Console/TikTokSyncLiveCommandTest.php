<?php

declare(strict_types=1);

use App\Jobs\SyncTikTokLive;
use App\Models\Platform;
use App\Models\PlatformAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

it('dispatches a job per active TikTok Shop account', function () {
    Queue::fake();

    $platform = Platform::factory()->create(['slug' => 'tiktok-shop']);
    PlatformAccount::factory()->for($platform)->create(['is_active' => true]);
    PlatformAccount::factory()->for($platform)->create(['is_active' => true]);
    PlatformAccount::factory()->for($platform)->create(['is_active' => false]);

    $this->artisan('tiktok:sync-live')->assertSuccessful();

    Queue::assertPushed(SyncTikTokLive::class, 2);
});

it('dispatches for a single account when --account is given', function () {
    Queue::fake();

    $platform = Platform::factory()->create(['slug' => 'tiktok-shop']);
    $a = PlatformAccount::factory()->for($platform)->create(['is_active' => true]);
    PlatformAccount::factory()->for($platform)->create(['is_active' => true]);

    $this->artisan('tiktok:sync-live', ['--account' => $a->id])->assertSuccessful();

    Queue::assertPushed(SyncTikTokLive::class, 1);
});

it('fails when there are no active TikTok Shop accounts', function () {
    Platform::factory()->create(['slug' => 'tiktok-shop']);

    $this->artisan('tiktok:sync-live')->assertFailed();
});

it('fails when the tiktok-shop platform does not exist', function () {
    $this->artisan('tiktok:sync-live')->assertFailed();
});
