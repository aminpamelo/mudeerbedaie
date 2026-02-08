<?php

use App\Contracts\Shipping\ShippingProvider;
use App\Services\SettingsService;
use App\Services\Shipping\JntShippingService;
use App\Services\Shipping\ShippingManager;

test('JntShippingService implements ShippingProvider interface', function () {
    $service = app(JntShippingService::class);

    expect($service)->toBeInstanceOf(ShippingProvider::class);
});

test('JntShippingService returns correct provider info', function () {
    $service = app(JntShippingService::class);

    expect($service->getProviderName())->toBe('J&T Express');
    expect($service->getProviderSlug())->toBe('jnt');
});

test('JntShippingService is not configured without settings', function () {
    $service = app(JntShippingService::class);

    expect($service->isConfigured())->toBeFalse();
    expect($service->isEnabled())->toBeFalse();
});

test('JntShippingService reports configured when settings exist', function () {
    $settings = app(SettingsService::class);
    $settings->set('jnt_customer_code', 'ITTEST0001', 'string', 'shipping');
    $settings->set('jnt_private_key', 'Sfx6H8d4', 'encrypted', 'shipping');
    $settings->set('jnt_password', 'AA7EDDC3B82704CA3717E88E67A3CAF1', 'encrypted', 'shipping');
    $settings->set('enable_jnt_shipping', true, 'boolean', 'shipping');

    $service = app(JntShippingService::class);

    expect($service->isConfigured())->toBeTrue();
    expect($service->isEnabled())->toBeTrue();
});

test('JntShippingService defaults to sandbox mode', function () {
    $service = app(JntShippingService::class);

    expect($service->isSandbox())->toBeTrue();
});

test('ShippingManager registers and retrieves providers', function () {
    $manager = app(ShippingManager::class);

    $provider = $manager->getProvider('jnt');
    expect($provider)->toBeInstanceOf(JntShippingService::class);
    expect($provider->getProviderSlug())->toBe('jnt');
});

test('ShippingManager throws exception for unknown provider', function () {
    $manager = app(ShippingManager::class);

    $manager->getProvider('unknown');
})->throws(InvalidArgumentException::class);

test('ShippingManager returns empty enabled providers when none configured', function () {
    $manager = app(ShippingManager::class);

    expect($manager->getEnabledProviders())->toBeEmpty();
});

test('ShippingManager returns enabled providers when configured', function () {
    $settings = app(SettingsService::class);
    $settings->set('jnt_customer_code', 'ITTEST0001', 'string', 'shipping');
    $settings->set('jnt_private_key', 'Sfx6H8d4', 'encrypted', 'shipping');
    $settings->set('enable_jnt_shipping', true, 'boolean', 'shipping');

    $manager = app(ShippingManager::class);
    $enabled = $manager->getEnabledProviders();

    expect($enabled)->toHaveCount(1);
    expect(array_key_first($enabled))->toBe('jnt');
});

test('SettingsService JNT helpers work correctly', function () {
    $settings = app(SettingsService::class);

    expect($settings->isJntConfigured())->toBeFalse();
    expect($settings->isJntEnabled())->toBeFalse();

    $settings->set('jnt_customer_code', 'ITTEST0001', 'string', 'shipping');
    $settings->set('jnt_private_key', 'Sfx6H8d4', 'encrypted', 'shipping');

    expect($settings->isJntConfigured())->toBeTrue();
    expect($settings->isJntEnabled())->toBeFalse();

    $settings->set('enable_jnt_shipping', true, 'boolean', 'shipping');
    expect($settings->isJntEnabled())->toBeTrue();
});

test('SettingsService sender defaults are empty initially', function () {
    $settings = app(SettingsService::class);
    $defaults = $settings->getShippingSenderDefaults();

    expect($defaults['name'])->toBe('');
    expect($defaults['phone'])->toBe('');
    expect($defaults['city'])->toBe('');
});
