<?php

declare(strict_types=1);

use App\Models\LiveAccount;
use App\Models\LiveHostPlatformAccount;
use App\Models\LiveScheduleAssignment;
use App\Models\LiveSession;
use App\Models\PlatformAccount;
use App\Models\User;
use App\Services\LiveHost\LiveAccountResolver;

use function Pest\Laravel\artisan;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

it('resolves a slot via its pivot creator id', function () {
    $shop = PlatformAccount::factory()->create();
    $account = LiveAccount::factory()->create(['creator_user_id' => '6526684195492729856']);

    $pivot = LiveHostPlatformAccount::factory()->create([
        'platform_account_id' => $shop->id,
        'creator_platform_user_id' => '6526684195492729856',
    ]);

    $slot = LiveScheduleAssignment::factory()->create([
        'platform_account_id' => $shop->id,
        'live_host_platform_account_id' => $pivot->id,
        'live_account_id' => null,
    ]);

    artisan('livehost:backfill-schedule-accounts')->assertSuccessful();

    expect($slot->fresh()->live_account_id)->toBe($account->id);
});

it('resolves a slot via a unique host+shop pairing when no pivot exists', function () {
    $shop = PlatformAccount::factory()->create();
    $host = User::factory()->create(['role' => 'live_host']);
    $account = LiveAccount::factory()->create();
    $account->shops()->attach($shop->id);
    $account->hosts()->attach($host->id);

    $slot = LiveScheduleAssignment::factory()->create([
        'platform_account_id' => $shop->id,
        'live_host_id' => $host->id,
        'live_host_platform_account_id' => null,
        'live_account_id' => null,
    ]);

    artisan('livehost:backfill-schedule-accounts')->assertSuccessful();

    expect($slot->fresh()->live_account_id)->toBe($account->id);
});

it('leaves a slot null when the host+shop pairing is ambiguous', function () {
    $shop = PlatformAccount::factory()->create();
    $host = User::factory()->create(['role' => 'live_host']);

    foreach (range(1, 2) as $i) {
        $a = LiveAccount::factory()->create();
        $a->shops()->attach($shop->id);
        $a->hosts()->attach($host->id);
    }

    $slot = LiveScheduleAssignment::factory()->create([
        'platform_account_id' => $shop->id,
        'live_host_id' => $host->id,
        'live_host_platform_account_id' => null,
        'live_account_id' => null,
    ]);

    artisan('livehost:backfill-schedule-accounts')->assertSuccessful();

    expect($slot->fresh()->live_account_id)->toBeNull();
});

it('backfills live sessions too and is idempotent', function () {
    $shop = PlatformAccount::factory()->create();
    $account = LiveAccount::factory()->create(['creator_user_id' => '999']);
    $pivot = LiveHostPlatformAccount::factory()->create([
        'platform_account_id' => $shop->id,
        'creator_platform_user_id' => '999',
    ]);

    $session = LiveSession::factory()->create([
        'platform_account_id' => $shop->id,
        'live_host_platform_account_id' => $pivot->id,
        'live_account_id' => null,
    ]);

    artisan('livehost:backfill-schedule-accounts')->assertSuccessful();
    artisan('livehost:backfill-schedule-accounts')->assertSuccessful();

    expect($session->fresh()->live_account_id)->toBe($account->id);
});

it('resolver prefers creator id over handle', function () {
    $byId = LiveAccount::factory()->create(['creator_user_id' => '123', 'normalized_handle' => 'shared']);
    LiveAccount::factory()->create(['creator_user_id' => '456', 'normalized_handle' => 'shared']);

    $pivot = LiveHostPlatformAccount::factory()->make([
        'creator_platform_user_id' => '123',
        'creator_handle' => 'shared',
    ]);

    $resolved = app(LiveAccountResolver::class)->fromPivot($pivot);

    expect($resolved->id)->toBe($byId->id);
});
