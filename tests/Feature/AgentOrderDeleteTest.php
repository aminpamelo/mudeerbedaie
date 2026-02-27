<?php

declare(strict_types=1);

use App\Models\Agent;
use App\Models\ProductOrder;
use App\Models\ProductOrderItem;
use App\Models\User;
use Livewire\Volt\Volt;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($this->admin);
});

test('delete button is visible on agent orders list', function () {
    $agent = Agent::factory()->agent()->create();
    $order = ProductOrder::factory()->create([
        'agent_id' => $agent->id,
        'status' => 'pending',
    ]);

    $this->get('/admin/agent-orders')
        ->assertSuccessful()
        ->assertSee($order->order_number);
});

test('confirm delete order sets the selected order', function () {
    $agent = Agent::factory()->agent()->create();
    $order = ProductOrder::factory()->create([
        'agent_id' => $agent->id,
        'status' => 'pending',
    ]);

    Volt::test('admin.agent-orders.agent-orders-index')
        ->call('confirmDeleteOrder', $order->id)
        ->assertSet('showDeleteModal', true)
        ->assertSet('selectedOrderForDeletion', $order->id);
});

test('close delete modal resets state', function () {
    $agent = Agent::factory()->agent()->create();
    $order = ProductOrder::factory()->create([
        'agent_id' => $agent->id,
        'status' => 'pending',
    ]);

    Volt::test('admin.agent-orders.agent-orders-index')
        ->call('confirmDeleteOrder', $order->id)
        ->assertSet('showDeleteModal', true)
        ->call('closeDeleteModal')
        ->assertSet('showDeleteModal', false)
        ->assertSet('selectedOrderForDeletion', null);
});

test('delete order removes the order and related records', function () {
    $agent = Agent::factory()->agent()->create();
    $order = ProductOrder::factory()->create([
        'agent_id' => $agent->id,
        'status' => 'pending',
    ]);

    ProductOrderItem::factory()->create([
        'order_id' => $order->id,
    ]);

    Volt::test('admin.agent-orders.agent-orders-index')
        ->call('confirmDeleteOrder', $order->id)
        ->call('deleteOrder')
        ->assertSet('showDeleteModal', false)
        ->assertSet('selectedOrderForDeletion', null);

    expect(ProductOrder::find($order->id))->toBeNull();
    expect(ProductOrderItem::where('order_id', $order->id)->count())->toBe(0);
});

test('delete order with invalid id handles gracefully', function () {
    Volt::test('admin.agent-orders.agent-orders-index')
        ->set('selectedOrderForDeletion', 99999)
        ->set('showDeleteModal', true)
        ->call('deleteOrder')
        ->assertSet('showDeleteModal', false)
        ->assertSet('selectedOrderForDeletion', null);
});
