<?php

declare(strict_types=1);

use App\Models\ProductOrder;
use App\Models\User;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;

uses(RefreshDatabase::class);

function epSettings(): SettingsService
{
    return app(SettingsService::class);
}

function configureEasyParcelCredentials(): void
{
    epSettings()->set('easyparcel_client_id', 'CID', 'encrypted', 'shipping');
    epSettings()->set('easyparcel_client_secret', 'SECRET', 'encrypted', 'shipping');
}

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($this->admin);
    $this->order = ProductOrder::factory()->create(['status' => 'processing']);
});

it('hides the EasyParcel section and shows no hint when nothing is configured', function () {
    Volt::test('admin.orders.order-show', ['order' => $this->order])
        ->assertDontSee('Get Rates')
        ->assertDontSee('EasyParcel not available');
});

it('shows a "not connected" hint when credentials are entered but the account is not linked', function () {
    configureEasyParcelCredentials(); // configured, but no refresh token

    Volt::test('admin.orders.order-show', ['order' => $this->order])
        ->assertDontSee('Get Rates')
        ->assertSee('EasyParcel not available')
        ->assertSee('Account not connected');
});

it('shows a "switched off" hint when configured and connected but the toggle is off', function () {
    configureEasyParcelCredentials();
    epSettings()->setEasyParcelTokens('ACCESS', 'REFRESH', now()->addHours(10)->toIso8601String());
    epSettings()->set('enable_easyparcel_shipping', false, 'boolean', 'shipping');

    Volt::test('admin.orders.order-show', ['order' => $this->order])
        ->assertDontSee('Get Rates')
        ->assertSee('EasyParcel not available')
        ->assertSee('Switched off');
});

it('shows the Get Rates box and no hint when fully enabled', function () {
    configureEasyParcelCredentials();
    epSettings()->setEasyParcelTokens('ACCESS', 'REFRESH', now()->addHours(10)->toIso8601String());
    epSettings()->set('enable_easyparcel_shipping', true, 'boolean', 'shipping');

    Volt::test('admin.orders.order-show', ['order' => $this->order])
        ->assertSee('Get Rates')
        ->assertDontSee('EasyParcel not available');
});

it('does not show the hint for orders that are not confirmed or processing', function () {
    configureEasyParcelCredentials(); // configured but not connected

    $pendingOrder = ProductOrder::factory()->create(['status' => 'pending']);

    Volt::test('admin.orders.order-show', ['order' => $pendingOrder])
        ->assertDontSee('EasyParcel not available');
});
