<?php

use App\Models\User;
use App\Services\SettingsService;
use Livewire\Volt\Volt;

test('shipping settings page requires admin authentication', function () {
    $this->get('/admin/settings/shipping')
        ->assertRedirect('/login');
});

test('non-admin users cannot access shipping settings', function () {
    $user = User::factory()->student()->create();
    $this->actingAs($user);

    $this->get('/admin/settings/shipping')
        ->assertForbidden();
});

test('admin can access shipping settings page', function () {
    $user = User::factory()->admin()->create();
    $this->actingAs($user);

    $this->get('/admin/settings/shipping')
        ->assertSuccessful();
});

test('admin can save JNT settings', function () {
    $user = User::factory()->admin()->create();
    $this->actingAs($user);

    Volt::test('admin.settings-shipping')
        ->set('jnt_customer_code', 'ITTEST0001')
        ->set('jnt_private_key', 'Sfx6H8d4')
        ->set('jnt_password', 'AA7EDDC3B82704CA3717E88E67A3CAF1')
        ->set('jnt_sandbox', '1')
        ->set('enable_jnt_shipping', true)
        ->set('jnt_default_service_type', 'EZ')
        ->call('saveJnt')
        ->assertHasNoErrors()
        ->assertDispatched('settings-saved');

    $settings = app(SettingsService::class);
    expect($settings->get('jnt_customer_code'))->toBe('ITTEST0001');
    expect((bool) $settings->get('enable_jnt_shipping'))->toBeTrue();
    expect($settings->get('jnt_default_service_type'))->toBe('EZ');
});

test('admin can save sender defaults', function () {
    $user = User::factory()->admin()->create();
    $this->actingAs($user);

    Volt::test('admin.settings-shipping')
        ->set('sender_name', 'Test Company')
        ->set('sender_phone', '0123456789')
        ->set('sender_address', '123 Test Street')
        ->set('sender_city', 'Shah Alam')
        ->set('sender_state', 'Selangor')
        ->set('sender_postal_code', '40000')
        ->call('saveSenderDefaults')
        ->assertHasNoErrors()
        ->assertDispatched('settings-saved');

    $settings = app(SettingsService::class);
    expect($settings->get('shipping_sender_name'))->toBe('Test Company');
    expect($settings->get('shipping_sender_phone'))->toBe('0123456789');
    expect($settings->get('shipping_sender_city'))->toBe('Shah Alam');
    expect($settings->get('shipping_sender_postal_code'))->toBe('40000');
});

test('jnt settings validation rejects invalid service type', function () {
    $user = User::factory()->admin()->create();
    $this->actingAs($user);

    Volt::test('admin.settings-shipping')
        ->set('jnt_default_service_type', 'INVALID')
        ->call('saveJnt')
        ->assertHasErrors(['jnt_default_service_type']);
});

test('tab switching works', function () {
    $user = User::factory()->admin()->create();
    $this->actingAs($user);

    Volt::test('admin.settings-shipping')
        ->assertSet('activeTab', 'jnt')
        ->call('switchTab', 'sender')
        ->assertSet('activeTab', 'sender')
        ->call('switchTab', 'jnt')
        ->assertSet('activeTab', 'jnt');
});
