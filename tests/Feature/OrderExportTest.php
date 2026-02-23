<?php

declare(strict_types=1);

use App\Models\ProductOrder;
use App\Models\ProductOrderItem;
use App\Models\User;
use Livewire\Volt\Volt;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($this->admin);
});

it('exports orders as CSV file', function () {
    $order = ProductOrder::factory()->create([
        'order_number' => 'ORD-TEST-001',
        'customer_name' => 'Test Customer',
        'status' => 'pending',
    ]);

    ProductOrderItem::factory()->create([
        'order_id' => $order->id,
        'product_name' => 'Test Product',
        'quantity_ordered' => 2,
        'unit_price' => 50.00,
        'total_price' => 100.00,
    ]);

    Volt::test('admin.orders.order-list')
        ->call('exportOrders')
        ->assertFileDownloaded();
});

it('exports only filtered orders by status', function () {
    ProductOrder::factory()->create([
        'order_number' => 'ORD-PENDING-001',
        'status' => 'pending',
    ]);

    ProductOrder::factory()->create([
        'order_number' => 'ORD-SHIPPED-001',
        'status' => 'shipped',
    ]);

    $response = Volt::test('admin.orders.order-list')
        ->set('activeTab', 'pending')
        ->call('exportOrders')
        ->assertFileDownloaded();
});

it('exports only filtered orders by source', function () {
    ProductOrder::factory()->create([
        'order_number' => 'ORD-POS-001',
        'source' => 'pos',
    ]);

    ProductOrder::factory()->create([
        'order_number' => 'ORD-FUNNEL-001',
        'source' => 'funnel',
    ]);

    Volt::test('admin.orders.order-list')
        ->set('sourceTab', 'pos')
        ->call('exportOrders')
        ->assertFileDownloaded();
});

it('exports orders with correct CSV filename format', function () {
    ProductOrder::factory()->create();

    $component = Volt::test('admin.orders.order-list')
        ->call('exportOrders');

    $downloadEffect = data_get($component->effects, 'download');
    $filename = data_get($downloadEffect, 'name');

    expect($filename)->toStartWith('orders-export-')->toEndWith('.csv');
});
