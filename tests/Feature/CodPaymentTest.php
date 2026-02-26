<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
});

test('admin can view COD settings tab', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.settings.payment', ['tab' => 'cod']))
        ->assertSuccessful()
        ->assertSee('Cash on Delivery (COD)');
});

test('COD is disabled by default in settings service', function () {
    $settingsService = app(SettingsService::class);
    expect($settingsService->isCodEnabled())->toBeFalse();
    expect($settingsService->getCodInstructions())->toBe('');
});

test('admin can enable and save COD settings', function () {
    $settingsService = app(SettingsService::class);

    $settingsService->set('enable_cod_payments', true, 'boolean', 'payment');
    $settingsService->set('cod_customer_instructions', 'Please prepare exact change.', 'string', 'payment');

    // Clear cache to get fresh values
    \Illuminate\Support\Facades\Cache::flush();

    expect($settingsService->isCodEnabled())->toBeTrue();
    expect($settingsService->getCodInstructions())->toBe('Please prepare exact change.');
});

test('admin can disable COD payments', function () {
    $settingsService = app(SettingsService::class);

    // Enable first
    $settingsService->set('enable_cod_payments', true, 'boolean', 'payment');
    \Illuminate\Support\Facades\Cache::flush();
    expect($settingsService->isCodEnabled())->toBeTrue();

    // Disable
    $settingsService->set('enable_cod_payments', false, 'boolean', 'payment');
    \Illuminate\Support\Facades\Cache::flush();
    expect($settingsService->isCodEnabled())->toBeFalse();
});

test('COD tab shows enabled badge when active', function () {
    app(SettingsService::class)->set('enable_cod_payments', true, 'boolean', 'payment');
    \Illuminate\Support\Facades\Cache::flush();

    $this->actingAs($this->admin)
        ->get(route('admin.settings.payment', ['tab' => 'cod']))
        ->assertSuccessful()
        ->assertSee('Enabled');
});

test('COD tab shows disabled badge when inactive', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.settings.payment', ['tab' => 'cod']))
        ->assertSuccessful()
        ->assertSee('Disabled');
});

test('COD settings form is shown when enabled', function () {
    app(SettingsService::class)->set('enable_cod_payments', true, 'boolean', 'payment');
    \Illuminate\Support\Facades\Cache::flush();

    $this->actingAs($this->admin)
        ->get(route('admin.settings.payment', ['tab' => 'cod']))
        ->assertSuccessful()
        ->assertSee('Customer Instructions')
        ->assertSee('How COD Works');
});
