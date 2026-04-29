<?php

use App\Exceptions\MissingPlatformAppConnectionException;
use App\Models\Platform;
use App\Models\PlatformAccount;
use App\Models\PlatformApiCredential;
use App\Models\PlatformApp;
use App\Models\User;
use App\Services\TikTok\TikTokClientFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->platform = Platform::factory()->create(['slug' => 'tiktok-shop']);
    $this->user = User::factory()->create();
});

it('builds client using app credentials for the requested category', function () {
    $multiChannelApp = PlatformApp::factory()->multiChannel()->create([
        'platform_id' => $this->platform->id,
        'app_key' => 'mc_key',
    ]);
    $multiChannelApp->setAppSecret('mc_secret');
    $multiChannelApp->save();

    $analyticsApp = PlatformApp::factory()->analytics()->create([
        'platform_id' => $this->platform->id,
        'app_key' => 'an_key',
    ]);
    $analyticsApp->setAppSecret('an_secret');
    $analyticsApp->save();

    $account = PlatformAccount::factory()->create([
        'platform_id' => $this->platform->id,
        'user_id' => $this->user->id,
        'metadata' => ['shop_cipher' => 'cipher123'],
    ]);

    $analyticsCredential = new PlatformApiCredential([
        'platform_id' => $this->platform->id,
        'platform_account_id' => $account->id,
        'platform_app_id' => $analyticsApp->id,
        'credential_type' => 'oauth_token',
        'name' => 'Analytics Token',
        'is_active' => true,
        'expires_at' => now()->addHours(2),
    ]);
    $analyticsCredential->setValue('analytics_token_xyz');
    $analyticsCredential->save();

    $factory = app(TikTokClientFactory::class);
    $client = $factory->createClientForAccount($account, PlatformApp::CATEGORY_ANALYTICS_REPORTING);

    expect($client)->toBeInstanceOf(\EcomPHP\TiktokShop\Client::class);
});

it('throws MissingPlatformAppConnectionException when no credential exists for category', function () {
    PlatformApp::factory()->multiChannel()->create(['platform_id' => $this->platform->id]);
    PlatformApp::factory()->analytics()->create(['platform_id' => $this->platform->id]);

    $account = PlatformAccount::factory()->create([
        'platform_id' => $this->platform->id,
        'user_id' => $this->user->id,
    ]);

    $factory = app(TikTokClientFactory::class);

    expect(fn () => $factory->createClientForAccount($account, PlatformApp::CATEGORY_ANALYTICS_REPORTING))
        ->toThrow(MissingPlatformAppConnectionException::class);
});

it('throws when no PlatformApp exists for the requested category', function () {
    $account = PlatformAccount::factory()->create([
        'platform_id' => $this->platform->id,
        'user_id' => $this->user->id,
    ]);

    $factory = app(TikTokClientFactory::class);

    expect(fn () => $factory->createClientForAccount($account, PlatformApp::CATEGORY_ANALYTICS_REPORTING))
        ->toThrow(MissingPlatformAppConnectionException::class);
});
