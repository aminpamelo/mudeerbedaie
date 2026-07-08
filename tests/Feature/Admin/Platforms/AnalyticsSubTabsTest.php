<?php

declare(strict_types=1);

use App\Models\PendingPlatformProduct;
use App\Models\Platform;
use App\Models\PlatformAccount;
use App\Models\PlatformSkuMapping;
use App\Models\Product;
use App\Models\TiktokLiveReport;
use App\Models\TiktokProductPerformance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;

uses(RefreshDatabase::class);

function makeTikTokAccount(): PlatformAccount
{
    $platform = Platform::factory()->create(['slug' => 'tiktok-shop']);

    return PlatformAccount::factory()->create(['platform_id' => $platform->id]);
}

it('lists individual LIVE sessions under the LIVE Performance sub-tab', function () {
    $admin = User::factory()->admin()->create();
    $account = makeTikTokAccount();

    TiktokLiveReport::create([
        'platform_account_id' => $account->id,
        'tiktok_live_id' => 'LIVE_1',
        'source' => 'api',
        'creator_nickname' => 'muminah.sulpa',
        'launched_time' => now()->subDays(2),
        'duration_seconds' => 3661,
        'gmv_myr' => 1234.50,
        'live_attributed_gmv_myr' => 1000.00,
        'viewers' => 1500,
        'items_sold' => 42,
        'synced_at' => now(),
    ]);

    $this->actingAs($admin);

    Volt::test('admin.platforms.accounts.show', ['platform' => $account->platform, 'account' => $account])
        ->set('activeTab', 'analytics')
        ->set('analyticsSubTab', 'live')
        ->assertSee('LIVE Sessions')
        ->assertSee('muminah.sulpa')
        ->assertSee('1h 1m')       // 3661s formatted
        ->assertSee('Unmatched');  // no matched_live_session_id
});

it('resolves product performance rows to internal name, TikTok title, or raw id', function () {
    $admin = User::factory()->admin()->create();
    $account = makeTikTokAccount();
    $platform = $account->platform;

    // (1) Mapped to an internal product → shows the internal name
    $product = Product::factory()->create(['name' => 'Quran Digital Pen']);
    PlatformSkuMapping::create([
        'platform_id' => $platform->id,
        'platform_account_id' => $account->id,
        'product_id' => $product->id,
        'platform_sku' => 'SKU-MAPPED',
        'platform_product_name' => 'TikTok Pen Listing',
        'is_active' => true,
        'mapping_metadata' => ['platform_product_id' => 'PID_MAPPED'],
    ]);

    // (2) Synced from TikTok but not linked → shows TikTok title + Unmapped
    PendingPlatformProduct::create([
        'platform_id' => $platform->id,
        'platform_account_id' => $account->id,
        'platform_product_id' => 'PID_PENDING',
        'name' => 'Unlinked TikTok Product',
        'status' => 'pending',
        'fetched_at' => now(),
    ]);

    // Performance rows for all three id kinds (third is totally unknown)
    foreach (['PID_MAPPED', 'PID_PENDING', 'PID_UNKNOWN'] as $pid) {
        TiktokProductPerformance::create([
            'platform_account_id' => $account->id,
            'tiktok_product_id' => $pid,
            'impressions' => 100,
            'clicks' => 10,
            'orders' => 5,
            'gmv' => 500,
            'buyers' => 4,
            'conversion_rate' => 5,
            'raw_response' => [],
            'fetched_at' => now(),
        ]);
    }

    $this->actingAs($admin);

    Volt::test('admin.platforms.accounts.show', ['platform' => $platform, 'account' => $account])
        ->set('activeTab', 'analytics')
        ->set('analyticsSubTab', 'products')
        ->assertSee('Quran Digital Pen')        // (1) internal name wins
        ->assertSee('Unlinked TikTok Product')  // (2) TikTok title
        ->assertSee('Unmapped')                 // (2) + (3) badge
        ->assertSee('PID_UNKNOWN');             // (3) raw id fallback
});
