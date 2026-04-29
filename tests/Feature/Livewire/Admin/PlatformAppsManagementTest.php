<?php

use App\Models\Platform;
use App\Models\PlatformApp;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;

uses(RefreshDatabase::class);

it('registers a new platform app with encrypted secret', function () {
    $platform = Platform::factory()->create(['slug' => 'tiktok-shop']);
    $admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($admin);

    Volt::test('admin.platform-apps.edit', ['platform' => $platform])
        ->set('slug', 'tiktok-analytics-reporting')
        ->set('name', 'TikTok Analytics & Reporting')
        ->set('category', 'analytics_reporting')
        ->set('app_key', 'an_key_xyz')
        ->set('app_secret', 'an_secret_abc')
        ->call('save');

    $app = PlatformApp::where('slug', 'tiktok-analytics-reporting')->firstOrFail();

    expect($app->getAppSecret())->toBe('an_secret_abc');
    expect($app->encrypted_app_secret)->not->toBe('an_secret_abc');
});
