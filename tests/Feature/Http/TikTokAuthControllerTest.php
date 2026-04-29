<?php

use App\Models\Platform;
use App\Models\PlatformApp;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->platform = Platform::factory()->create(['slug' => 'tiktok-shop']);
    $this->user = User::factory()->admin()->create();
});

it('redirects to TikTok with the analytics app key when app=tiktok-analytics-reporting', function () {
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

    $response = $this->actingAs($this->user)
        ->get('/admin/tiktok/connect?app=tiktok-analytics-reporting');

    $response->assertRedirect();
    $location = $response->headers->get('Location');

    expect($location)->toContain('an_key');
    expect($location)->not->toContain('mc_key');
});

it('returns helpful error when requested app is not registered', function () {
    $this->actingAs($this->user)
        ->get('/admin/tiktok/connect?app=tiktok-unregistered-app')
        ->assertRedirect(route('platforms.index'));
});
