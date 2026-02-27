<?php

declare(strict_types=1);

use App\Models\Agent;
use App\Models\Product;
use App\Models\ProductOrder;
use App\Models\ProductOrderItem;
use App\Models\User;
use App\Models\Warehouse;
use Livewire\Volt\Volt;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($this->admin);

    $this->agent = Agent::factory()->agent()->create();
    $this->order = ProductOrder::factory()->create([
        'agent_id' => $this->agent->id,
        'status' => 'pending',
    ]);

    $product = Product::factory()->create();
    $warehouse = Warehouse::factory()->create();

    ProductOrderItem::factory()->create([
        'order_id' => $this->order->id,
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'product_name' => $product->name,
        'quantity_ordered' => 2,
        'unit_price' => 50.00,
        'total_price' => 100.00,
    ]);
});

test('agent order show page loads with action buttons', function () {
    $this->get('/admin/agent-orders/'.$this->order->id)
        ->assertSuccessful()
        ->assertSee('Download PDF')
        ->assertSee('Delivery Note')
        ->assertSee('Edit Order')
        ->assertSee('Back to List');
});

test('download pdf returns a pdf file', function () {
    Volt::test('admin.agent-orders.agent-orders-show', ['order' => $this->order])
        ->call('downloadPdf')
        ->assertFileDownloaded('receipt-'.$this->order->order_number.'.pdf');
});

test('download delivery note returns a pdf file', function () {
    Volt::test('admin.agent-orders.agent-orders-show', ['order' => $this->order])
        ->call('downloadDeliveryNote')
        ->assertFileDownloaded('delivery-note-'.$this->order->order_number.'.pdf');
});
