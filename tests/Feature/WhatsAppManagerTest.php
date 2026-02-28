<?php

use App\Contracts\WhatsAppProviderInterface;
use App\Services\SettingsService;
use App\Services\WhatsApp\OnsendProvider;
use App\Services\WhatsApp\WhatsAppManager;

test('resolves onsend provider by default', function () {
    $settings = Mockery::mock(SettingsService::class);
    $settings->shouldReceive('get')
        ->with('whatsapp_provider', 'onsend')
        ->andReturn('onsend');
    $settings->shouldReceive('getWhatsAppConfig')
        ->andReturn([
            'api_token' => 'test-token',
        ]);

    $manager = new WhatsAppManager($settings);
    $provider = $manager->provider();

    expect($provider)
        ->toBeInstanceOf(WhatsAppProviderInterface::class)
        ->toBeInstanceOf(OnsendProvider::class);
});

test('returns same instance on repeated calls (caching)', function () {
    $settings = Mockery::mock(SettingsService::class);
    $settings->shouldReceive('get')
        ->with('whatsapp_provider', 'onsend')
        ->once()
        ->andReturn('onsend');
    $settings->shouldReceive('getWhatsAppConfig')
        ->once()
        ->andReturn([
            'api_token' => 'test-token',
        ]);

    $manager = new WhatsAppManager($settings);
    $first = $manager->provider();
    $second = $manager->provider();

    expect($first)->toBe($second);
});

test('throws InvalidArgumentException for unknown provider', function () {
    $settings = Mockery::mock(SettingsService::class);
    $settings->shouldReceive('get')
        ->with('whatsapp_provider', 'onsend')
        ->andReturn('unknown_provider');

    $manager = new WhatsAppManager($settings);
    $manager->provider();
})->throws(InvalidArgumentException::class, 'Unknown WhatsApp provider: unknown_provider');

test('returns correct provider name', function () {
    $settings = Mockery::mock(SettingsService::class);
    $settings->shouldReceive('get')
        ->with('whatsapp_provider', 'onsend')
        ->andReturn('onsend');
    $settings->shouldReceive('getWhatsAppConfig')
        ->andReturn([
            'api_token' => 'test-token',
        ]);

    $manager = new WhatsAppManager($settings);

    expect($manager->getProviderName())->toBe('onsend');
});

test('creates OnsendProvider with config from SettingsService', function () {
    $settings = Mockery::mock(SettingsService::class);
    $settings->shouldReceive('get')
        ->with('whatsapp_provider', 'onsend')
        ->andReturn('onsend');
    $settings->shouldReceive('getWhatsAppConfig')
        ->andReturn([
            'api_token' => 'my-secret-token',
        ]);

    $manager = new WhatsAppManager($settings);
    $provider = $manager->provider();

    expect($provider)
        ->toBeInstanceOf(OnsendProvider::class)
        ->and($provider->apiToken)->toBe('my-secret-token')
        ->and($provider->apiUrl)->toBe('https://onsend.io/api/v1');
});

test('creates OnsendProvider with custom api_url from config', function () {
    $settings = Mockery::mock(SettingsService::class);
    $settings->shouldReceive('get')
        ->with('whatsapp_provider', 'onsend')
        ->andReturn('onsend');
    $settings->shouldReceive('getWhatsAppConfig')
        ->andReturn([
            'api_token' => 'test-token',
            'api_url' => 'https://custom-onsend.io/api/v2',
        ]);

    $manager = new WhatsAppManager($settings);
    $provider = $manager->provider();

    expect($provider->apiUrl)->toBe('https://custom-onsend.io/api/v2');
});

test('meta provider throws RuntimeException as not yet implemented', function () {
    $settings = Mockery::mock(SettingsService::class);
    $settings->shouldReceive('get')
        ->with('whatsapp_provider', 'onsend')
        ->andReturn('meta');

    $manager = new WhatsAppManager($settings);
    $manager->provider();
})->throws(RuntimeException::class, 'MetaCloudProvider not yet implemented');
