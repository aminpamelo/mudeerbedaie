<?php

declare(strict_types=1);

use App\Models\ActualLiveRecord;
use App\Models\LiveAccount;
use App\Models\LiveHostPlatformAccount;
use App\Models\PlatformAccount;
use App\Models\TiktokLiveReport;
use App\Models\User;

use function Pest\Laravel\artisan;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

it('merges one creator id seen across multiple shops into a single account', function () {
    $shopA = PlatformAccount::factory()->create();
    $shopB = PlatformAccount::factory()->create();

    ActualLiveRecord::factory()->create([
        'platform_account_id' => $shopA->id,
        'creator_platform_user_id' => '6526684195492729856',
        'creator_handle' => 'amarmirzabedaie',
    ]);
    ActualLiveRecord::factory()->create([
        'platform_account_id' => $shopB->id,
        'creator_platform_user_id' => '6526684195492729856',
        'creator_handle' => 'amarmirzabedaie',
    ]);

    artisan('livehost:consolidate-live-accounts')->assertSuccessful();

    expect(LiveAccount::where('creator_user_id', '6526684195492729856')->count())->toBe(1);

    $account = LiveAccount::where('creator_user_id', '6526684195492729856')->first();
    expect($account->nickname)->toBe('amarmirzabedaie')
        ->and($account->normalized_handle)->toBe('amarmirzabedaie')
        ->and($account->needs_review)->toBeFalse()
        ->and($account->shops()->pluck('platform_accounts.id')->sort()->values()->all())
        ->toEqual(collect([$shopA->id, $shopB->id])->sort()->values()->all());
});

it('folds a handle-only sighting into the matching creator-id account', function () {
    $shop = PlatformAccount::factory()->create();

    // Row that carries both id + handle teaches the handle->id mapping.
    ActualLiveRecord::factory()->create([
        'platform_account_id' => $shop->id,
        'creator_platform_user_id' => '7144303359985681434',
        'creator_handle' => 'ustazamarbedaie',
    ]);
    // Same handle but no id (CSV-only) — must resolve to the same account.
    TiktokLiveReport::create([
        'platform_account_id' => $shop->id,
        'tiktok_creator_id' => null,
        'creator_nickname' => 'UstazAmarBeDaie',
        'creator_display_name' => 'Ustaz Amar BeDaie',
        'launched_time' => now(),
        'source' => 'csv',
    ]);

    artisan('livehost:consolidate-live-accounts')->assertSuccessful();

    expect(LiveAccount::count())->toBe(1);
    $account = LiveAccount::first();
    expect($account->creator_user_id)->toBe('7144303359985681434')
        ->and($account->needs_review)->toBeFalse();
});

it('creates a separate review-flagged account for an unresolvable handle', function () {
    $shop = PlatformAccount::factory()->create();

    ActualLiveRecord::factory()->create([
        'platform_account_id' => $shop->id,
        'creator_platform_user_id' => null,
        'creator_handle' => 'ustamarbedaie', // typo variant, no id to merge on
    ]);

    artisan('livehost:consolidate-live-accounts')->assertSuccessful();

    $account = LiveAccount::where('normalized_handle', 'ustamarbedaie')->first();
    expect($account)->not->toBeNull()
        ->and($account->creator_user_id)->toBeNull()
        ->and($account->needs_review)->toBeTrue()
        ->and($account->metadata['review_reason'] ?? null)->toBe('no_creator_id');
});

it('links the operating host from the pivot to the account', function () {
    $shop = PlatformAccount::factory()->create();
    $host = User::factory()->create(['role' => 'live_host']);

    LiveHostPlatformAccount::factory()->create([
        'user_id' => $host->id,
        'platform_account_id' => $shop->id,
        'creator_handle' => 'amarmirzabedaie',
        'creator_platform_user_id' => '6526684195492729856',
    ]);

    artisan('livehost:consolidate-live-accounts')->assertSuccessful();

    $account = LiveAccount::where('creator_user_id', '6526684195492729856')->first();
    expect($account->hosts()->pluck('users.id')->all())->toContain($host->id);
});

it('is idempotent across repeated runs', function () {
    $shop = PlatformAccount::factory()->create();
    ActualLiveRecord::factory()->count(3)->create([
        'platform_account_id' => $shop->id,
        'creator_platform_user_id' => '6526684195492729856',
        'creator_handle' => 'amarmirzabedaie',
    ]);

    artisan('livehost:consolidate-live-accounts')->assertSuccessful();
    artisan('livehost:consolidate-live-accounts')->assertSuccessful();

    expect(LiveAccount::count())->toBe(1)
        ->and(LiveAccount::first()->shops()->count())->toBe(1);
});

it('writes nothing on a dry run', function () {
    $shop = PlatformAccount::factory()->create();
    ActualLiveRecord::factory()->create([
        'platform_account_id' => $shop->id,
        'creator_platform_user_id' => '6526684195492729856',
        'creator_handle' => 'amarmirzabedaie',
    ]);

    artisan('livehost:consolidate-live-accounts --dry-run')->assertSuccessful();

    expect(LiveAccount::count())->toBe(0);
});
