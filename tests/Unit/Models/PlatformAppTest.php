<?php

use App\Models\PlatformApp;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

it('encrypts and decrypts app_secret round-trip', function () {
    $app = PlatformApp::factory()->make();
    $app->setAppSecret('super-secret-value');

    expect($app->getAppSecret())->toBe('super-secret-value');
    expect($app->encrypted_app_secret)->not->toBe('super-secret-value');
});

it('returns null when getting unset secret', function () {
    $app = new PlatformApp;

    expect($app->getAppSecret())->toBeNull();
});
