<?php

declare(strict_types=1);

use App\Models\ProductOrder;
use App\Models\User;
use Livewire\Volt\Volt;

/**
 * The bulk "Send WhatsApp" button on the orders list was admin-only. Staff on
 * the `employee` role now get it too (accountants stay excluded), gated in both
 * the view and the server-side ensureCanBlast() action.
 */
it('shows the bulk Send WhatsApp button to an employee when orders are selected', function () {
    $employee = User::factory()->create(['role' => 'employee']);
    $order = ProductOrder::factory()->create(['customer_phone' => '60123456789']);

    $this->actingAs($employee);

    Volt::test('admin.orders.order-list')
        ->set('selectedOrderIds', [$order->id])
        ->assertSee('openBulkWhatsAppModal');
});

it('lets an employee open the bulk WhatsApp modal without a 403', function () {
    $employee = User::factory()->create(['role' => 'employee']);
    $order = ProductOrder::factory()->create(['customer_phone' => '60123456789']);

    $this->actingAs($employee);

    Volt::test('admin.orders.order-list')
        ->set('selectedOrderIds', [$order->id])
        ->call('openBulkWhatsAppModal')
        ->assertSet('showWhatsAppModal', true);
});

it('still shows the button to an admin', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $order = ProductOrder::factory()->create(['customer_phone' => '60123456789']);

    $this->actingAs($admin);

    Volt::test('admin.orders.order-list')
        ->set('selectedOrderIds', [$order->id])
        ->assertSee('openBulkWhatsAppModal');
});

it('hides the button from an accountant', function () {
    $accountant = User::factory()->create(['role' => 'accountant']);
    $order = ProductOrder::factory()->create(['customer_phone' => '60123456789']);

    $this->actingAs($accountant);

    Volt::test('admin.orders.order-list')
        ->set('selectedOrderIds', [$order->id])
        ->assertDontSee('openBulkWhatsAppModal');
});

it('blocks an accountant from opening the bulk WhatsApp modal server-side', function () {
    $accountant = User::factory()->create(['role' => 'accountant']);
    $order = ProductOrder::factory()->create(['customer_phone' => '60123456789']);

    $this->actingAs($accountant);

    // ensureCanBlast() aborts 403 before the modal flag is set, so it stays closed
    // even if the request is crafted without the (hidden) button.
    Volt::test('admin.orders.order-list')
        ->set('selectedOrderIds', [$order->id])
        ->call('openBulkWhatsAppModal')
        ->assertSet('showWhatsAppModal', false);
});
