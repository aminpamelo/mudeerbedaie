<?php

declare(strict_types=1);

use App\Models\ActualLiveRecord;
use App\Models\LiveAccount;
use App\Models\PlatformAccount;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->pic = User::factory()->create(['role' => 'admin_livehost']);
});

it('marks the matched live account as linked when a creator is registered', function () {
    $shop = PlatformAccount::factory()->create();
    $host = User::factory()->create(['role' => 'live_host']);

    // An unknown account already exists (auto-discovered from a sync).
    $account = LiveAccount::factory()->unknown()->create([
        'creator_user_id' => '6526684195492729856',
        'nickname' => 'amarmirzabedaie',
        'normalized_handle' => 'amarmirzabedaie',
    ]);

    actingAs($this->pic)
        ->post('/livehost/creators', [
            'user_id' => $host->id,
            'platform_account_id' => $shop->id,
            'creator_handle' => 'amarmirzabedaie',
            'creator_platform_user_id' => '6526684195492729856',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($account->fresh()->account_type)->toBe(LiveAccount::TYPE_LINKED);
});

it('creates a linked live account when none exists for the registered creator', function () {
    $shop = PlatformAccount::factory()->create();
    $host = User::factory()->create(['role' => 'live_host']);

    actingAs($this->pic)
        ->post('/livehost/creators', [
            'user_id' => $host->id,
            'platform_account_id' => $shop->id,
            'creator_handle' => 'newcreatorbedaie',
            'creator_platform_user_id' => '9998887776665554443',
        ])
        ->assertRedirect();

    $account = LiveAccount::where('creator_user_id', '9998887776665554443')->first();
    expect($account)->not->toBeNull();
    expect($account->account_type)->toBe(LiveAccount::TYPE_LINKED);
    expect($account->normalized_handle)->toBe('newcreatorbedaie');
});

it('exposes recently-active unknown creators as unclassified on the index', function () {
    $shop = PlatformAccount::factory()->create();

    LiveAccount::factory()->unknown()->create([
        'creator_user_id' => null, 'nickname' => 'ustamarbedaie', 'normalized_handle' => 'ustamarbedaie',
    ]);
    LiveAccount::factory()->linked()->create([
        'creator_user_id' => null, 'nickname' => 'amarmirzabedaie', 'normalized_handle' => 'amarmirzabedaie',
    ]);

    ActualLiveRecord::factory()->apiSync()->create([
        'platform_account_id' => $shop->id, 'creator_platform_user_id' => null,
        'creator_handle' => 'ustamarbedaie', 'launched_time' => now()->subDay(),
    ]);
    ActualLiveRecord::factory()->apiSync()->create([
        'platform_account_id' => $shop->id, 'creator_platform_user_id' => null,
        'creator_handle' => 'amarmirzabedaie', 'launched_time' => now()->subDay(),
    ]);

    actingAs($this->pic)
        ->get('/livehost/creators')
        ->assertInertia(fn (Assert $p) => $p
            ->component('creators/Index', false)
            ->has('unclassified', 1)
            ->where('unclassified.0.creatorHandle', 'ustamarbedaie'));
});

it('classifies an unknown account as linked by id — no creator id required', function () {
    $account = LiveAccount::factory()->unknown()->create([
        'creator_user_id' => null, 'nickname' => 'ustazamarmirza', 'normalized_handle' => 'ustazamarmirza',
    ]);

    actingAs($this->pic)
        ->post('/livehost/live-accounts/classify', [
            'live_account_id' => $account->id,
            'account_type' => 'linked',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($account->fresh()->account_type)->toBe(LiveAccount::TYPE_LINKED);
});

it('links a creator to the TikTok Shop it went live on', function () {
    $shop = PlatformAccount::factory()->create();
    $account = LiveAccount::factory()->unknown()->create([
        'creator_user_id' => null, 'nickname' => 'ustamarbedaie', 'normalized_handle' => 'ustamarbedaie',
    ]);

    actingAs($this->pic)
        ->post('/livehost/live-accounts/classify', [
            'live_account_id' => $account->id,
            'account_type' => 'linked',
            'shop_ids' => [$shop->id],
        ])
        ->assertRedirect();

    $account->refresh();
    expect($account->account_type)->toBe(LiveAccount::TYPE_LINKED);
    expect($account->shops()->pluck('platform_accounts.id')->all())->toContain($shop->id);
    expect($account->shops()->wherePivot('is_primary', true)->count())->toBe(1);
});

it('filters creators and the unclassified list by TikTok Shop', function () {
    $shopA = PlatformAccount::factory()->create();
    $shopB = PlatformAccount::factory()->create();

    LiveAccount::factory()->unknown()->create(['creator_user_id' => null, 'nickname' => 'creatoraa', 'normalized_handle' => 'creatoraa']);
    LiveAccount::factory()->unknown()->create(['creator_user_id' => null, 'nickname' => 'creatorbb', 'normalized_handle' => 'creatorbb']);

    ActualLiveRecord::factory()->apiSync()->create([
        'platform_account_id' => $shopA->id, 'creator_platform_user_id' => null,
        'creator_handle' => 'creatoraa', 'launched_time' => now()->subDay(),
    ]);
    ActualLiveRecord::factory()->apiSync()->create([
        'platform_account_id' => $shopB->id, 'creator_platform_user_id' => null,
        'creator_handle' => 'creatorbb', 'launched_time' => now()->subDay(),
    ]);

    actingAs($this->pic)
        ->get('/livehost/creators?platform_account='.$shopA->id)
        ->assertInertia(fn (Assert $p) => $p
            ->component('creators/Index', false)
            ->has('unclassified', 1)
            ->where('unclassified.0.creatorHandle', 'creatoraa')
            ->where('unclassified.0.platformAccountId', $shopA->id));
});

it('classifies a creator with no existing account by handle alone', function () {
    actingAs($this->pic)
        ->post('/livehost/live-accounts/classify', [
            'creator_handle' => 'brandnewcreator',
            'account_type' => 'linked',
        ])
        ->assertRedirect();

    $account = LiveAccount::where('normalized_handle', 'brandnewcreator')->first();
    expect($account)->not->toBeNull();
    expect($account->account_type)->toBe(LiveAccount::TYPE_LINKED);
    expect($account->creator_user_id)->toBeNull();
});

it('dismisses a creator as affiliate', function () {
    $account = LiveAccount::factory()->unknown()->create([
        'creator_user_id' => null, 'nickname' => 'hibbun.hq', 'normalized_handle' => 'hibbun.hq',
    ]);

    actingAs($this->pic)
        ->post('/livehost/live-accounts/classify', [
            'live_account_id' => $account->id,
            'account_type' => 'affiliate',
        ])
        ->assertRedirect();

    expect($account->fresh()->account_type)->toBe(LiveAccount::TYPE_AFFILIATE);
});
