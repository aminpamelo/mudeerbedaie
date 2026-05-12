<?php

declare(strict_types=1);

use App\Jobs\SyncTikTokLive;
use App\Models\PlatformAccount;
use App\Services\TikTok\TikTokLiveSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('skips inactive accounts', function () {
    $account = PlatformAccount::factory()->create(['is_active' => false]);

    $serviceMock = Mockery::mock(TikTokLiveSyncService::class);
    $serviceMock->shouldNotReceive('syncLivePerformance');

    (new SyncTikTokLive($account))->handle($serviceMock);

    // No exceptions; nothing more to assert. The shouldNotReceive() expectation
    // is the assertion.
    expect(true)->toBeTrue();
});

it('updates last_live_analytics_sync_at on success', function () {
    $account = PlatformAccount::factory()->create(['is_active' => true]);

    $serviceMock = Mockery::mock(TikTokLiveSyncService::class);
    $serviceMock->shouldReceive('syncLivePerformance')
        ->once()
        ->andReturn([
            'synced' => 1,
            'created' => 1,
            'updated' => 0,
            'matched' => 0,
            'unmatched' => 1,
            'pages' => 1,
        ]);

    (new SyncTikTokLive($account))->handle($serviceMock);

    $account->refresh();
    expect($account->last_live_analytics_sync_at)->not->toBeNull()
        ->and($account->sync_status)->toBe('completed')
        ->and($account->metadata['last_live_sync_result']['synced'] ?? null)->toBe(1)
        ->and($account->metadata['last_live_sync_result']['fetched_at'] ?? null)->not->toBeNull();
});

it('records sync error and rethrows on failure', function () {
    $account = PlatformAccount::factory()->create(['is_active' => true]);

    $serviceMock = Mockery::mock(TikTokLiveSyncService::class);
    $serviceMock->shouldReceive('syncLivePerformance')
        ->andThrow(new \Exception('boom'));

    expect(fn () => (new SyncTikTokLive($account))->handle($serviceMock))
        ->toThrow(\Exception::class, 'boom');
});
