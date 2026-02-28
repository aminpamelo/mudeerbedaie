<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\SettingsService;
use App\Services\WhatsAppService;
use Livewire\Volt\Volt;

beforeEach(function () {
    // Mock WhatsAppService to avoid real API calls during mount
    $whatsApp = Mockery::mock(WhatsAppService::class);
    $whatsApp->shouldReceive('checkDeviceStatus')->andReturn([
        'success' => true,
        'status' => 'not_configured',
        'message' => 'Not configured',
    ]);
    $whatsApp->shouldReceive('getTodayStats')->andReturn([
        'message_count' => 0,
        'success_count' => 0,
        'failure_count' => 0,
        'is_unlimited' => true,
        'remaining' => 0,
    ]);
    app()->instance(WhatsAppService::class, $whatsApp);

    $this->admin = User::factory()->admin()->create();
    $this->actingAs($this->admin);
});

it('loads default provider as onsend', function () {
    Volt::test('admin.settings-whatsapp')
        ->assertSet('provider', 'onsend');
});

it('loads provider from settings when saved', function () {
    $settingsService = app(SettingsService::class);
    $settingsService->set('whatsapp_provider', 'meta', 'string', 'whatsapp');

    Volt::test('admin.settings-whatsapp')
        ->assertSet('provider', 'meta');
});

it('saves meta provider settings', function () {
    Volt::test('admin.settings-whatsapp')
        ->set('provider', 'meta')
        ->set('metaPhoneNumberId', '123456789')
        ->set('metaAccessToken', 'test-access-token')
        ->set('metaWabaId', 'waba-123')
        ->set('metaAppSecret', 'test-secret')
        ->set('metaVerifyToken', 'verify-123')
        ->set('metaApiVersion', 'v21.0')
        ->call('save')
        ->assertHasNoErrors();

    $settings = app(SettingsService::class);
    expect($settings->get('whatsapp_provider'))->toBe('meta');
    expect($settings->get('meta_phone_number_id'))->toBe('123456789');
    expect($settings->get('meta_access_token'))->toBe('test-access-token');
    expect($settings->get('meta_waba_id'))->toBe('waba-123');
    expect($settings->get('meta_api_version'))->toBe('v21.0');
});

it('saves onsend provider settings', function () {
    Volt::test('admin.settings-whatsapp')
        ->set('provider', 'onsend')
        ->set('apiToken', 'onsend-test-token')
        ->call('save')
        ->assertHasNoErrors();

    $settings = app(SettingsService::class);
    expect($settings->get('whatsapp_provider'))->toBe('onsend');
    expect($settings->get('whatsapp_api_token'))->toBe('onsend-test-token');
});

it('validates required meta fields when meta provider is selected', function () {
    Volt::test('admin.settings-whatsapp')
        ->set('provider', 'meta')
        ->set('metaPhoneNumberId', '')
        ->set('metaAccessToken', '')
        ->call('save')
        ->assertHasErrors(['metaPhoneNumberId', 'metaAccessToken']);
});

it('does not validate meta fields when onsend provider is selected', function () {
    Volt::test('admin.settings-whatsapp')
        ->set('provider', 'onsend')
        ->set('metaPhoneNumberId', '')
        ->set('metaAccessToken', '')
        ->call('save')
        ->assertHasNoErrors(['metaPhoneNumberId', 'metaAccessToken']);
});

it('hides anti-ban settings when meta provider is selected', function () {
    Volt::test('admin.settings-whatsapp')
        ->set('provider', 'meta')
        ->assertDontSee('Tetapan Anti-Ban');
});

it('shows anti-ban settings when onsend provider is selected', function () {
    Volt::test('admin.settings-whatsapp')
        ->set('provider', 'onsend')
        ->assertSee('Tetapan Anti-Ban');
});

it('shows unofficial api warning when onsend provider is selected', function () {
    Volt::test('admin.settings-whatsapp')
        ->set('provider', 'onsend')
        ->assertSee('API Tidak Rasmi');
});

it('shows official meta banner when meta provider is selected', function () {
    Volt::test('admin.settings-whatsapp')
        ->set('provider', 'meta')
        ->assertSee('Meta Cloud API (Rasmi)');
});

it('validates test phone number is required', function () {
    Volt::test('admin.settings-whatsapp')
        ->set('testPhoneNumber', '')
        ->call('sendTestMessage')
        ->assertHasErrors(['testPhoneNumber']);
});

it('does not save meta settings when onsend provider is selected', function () {
    Volt::test('admin.settings-whatsapp')
        ->set('provider', 'onsend')
        ->set('metaPhoneNumberId', 'should-not-save')
        ->set('apiToken', 'onsend-token')
        ->call('save')
        ->assertHasNoErrors();

    $settings = app(SettingsService::class);
    expect($settings->exists('meta_phone_number_id'))->toBeFalse();
});
