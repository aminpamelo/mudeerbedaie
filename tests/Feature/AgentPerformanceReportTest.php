<?php

declare(strict_types=1);

use App\Models\Agent;
use App\Models\Product;
use App\Models\ProductOrder;
use App\Models\ProductOrderItem;
use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'admin']);
});

test('report page loads for authenticated admin', function () {
    $this->actingAs($this->admin)
        ->get('/admin/agent-orders/report')
        ->assertSuccessful();
});

test('report page shows top selling products section', function () {
    $agent = Agent::factory()->agent()->create();
    $product = Product::factory()->create(['name' => 'Test Product ABC']);

    $order = ProductOrder::factory()->create([
        'agent_id' => $agent->id,
        'order_date' => now(),
        'status' => 'pending',
        'total_amount' => 500,
    ]);

    ProductOrderItem::factory()->create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'product_name' => $product->name,
        'quantity_ordered' => 3,
        'unit_price' => 100,
        'total_price' => 300,
    ]);

    $this->actingAs($this->admin)
        ->get('/admin/agent-orders/report?tab=products')
        ->assertSuccessful()
        ->assertSee('Product Sales Detail')
        ->assertSee('Test Product ABC');
});

test('report page shows top agents and companies section', function () {
    $agent = Agent::factory()->agent()->create(['name' => 'Top Agent Smith']);

    ProductOrder::factory()->create([
        'agent_id' => $agent->id,
        'order_date' => now(),
        'status' => 'pending',
        'total_amount' => 1000,
    ]);

    $this->actingAs($this->admin)
        ->get('/admin/agent-orders/report?tab=agents')
        ->assertSuccessful()
        ->assertSee('Top Agents')
        ->assertSee('Top Agent Smith');
});

test('cancelled orders are excluded from top products and agents', function () {
    $agent = Agent::factory()->agent()->create(['name' => 'Cancelled Agent']);
    $product = Product::factory()->create(['name' => 'Cancelled Product']);

    $order = ProductOrder::factory()->create([
        'agent_id' => $agent->id,
        'order_date' => now(),
        'status' => 'cancelled',
        'total_amount' => 500,
    ]);

    ProductOrderItem::factory()->create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'product_name' => $product->name,
        'quantity_ordered' => 1,
        'total_price' => 500,
    ]);

    $this->actingAs($this->admin)
        ->get('/admin/agent-orders/report')
        ->assertSuccessful()
        ->assertDontSee('Cancelled Product')
        ->assertDontSee('Cancelled Agent');
});

test('agents are ranked by revenue in top agents section', function () {
    $topAgent = Agent::factory()->agent()->create(['name' => 'High Revenue Agent']);
    $lowAgent = Agent::factory()->agent()->create(['name' => 'Low Revenue Agent']);

    ProductOrder::factory()->create([
        'agent_id' => $topAgent->id,
        'order_date' => now(),
        'status' => 'pending',
        'total_amount' => 5000,
    ]);

    ProductOrder::factory()->create([
        'agent_id' => $lowAgent->id,
        'order_date' => now(),
        'status' => 'pending',
        'total_amount' => 100,
    ]);

    $response = $this->actingAs($this->admin)
        ->get('/admin/agent-orders/report?tab=agents');

    $response->assertSuccessful()
        ->assertSee('High Revenue Agent')
        ->assertSee('Low Revenue Agent');

    // High revenue agent should appear before low revenue agent
    $content = $response->getContent();
    $highPos = strpos($content, 'High Revenue Agent');
    $lowPos = strpos($content, 'Low Revenue Agent');

    expect($highPos)->toBeLessThan($lowPos);
});

test('product insights tab shows summary cards', function () {
    $agent = Agent::factory()->agent()->create();

    $order = ProductOrder::factory()->create([
        'agent_id' => $agent->id,
        'order_date' => now(),
        'status' => 'pending',
        'total_amount' => 300,
    ]);

    ProductOrderItem::factory()->create([
        'order_id' => $order->id,
        'product_name' => 'Product A',
        'quantity_ordered' => 5,
        'unit_price' => 30,
        'total_price' => 150,
    ]);

    ProductOrderItem::factory()->create([
        'order_id' => $order->id,
        'product_name' => 'Product B',
        'quantity_ordered' => 3,
        'unit_price' => 50,
        'total_price' => 150,
    ]);

    $this->actingAs($this->admin)
        ->get('/admin/agent-orders/report?tab=products')
        ->assertSuccessful()
        ->assertSee('Unique Products')
        ->assertSee('Total Units Sold')
        ->assertSee('Product Revenue')
        ->assertSee('Avg Revenue / Product');
});

test('agents tab shows summary cards and detail table', function () {
    $agent1 = Agent::factory()->agent()->create(['name' => 'Agent One']);
    $agent2 = Agent::factory()->company()->create(['name' => 'Company Two']);

    ProductOrder::factory()->create([
        'agent_id' => $agent1->id,
        'order_date' => now(),
        'status' => 'pending',
        'total_amount' => 1000,
    ]);

    ProductOrder::factory()->create([
        'agent_id' => $agent2->id,
        'order_date' => now(),
        'status' => 'pending',
        'total_amount' => 2000,
    ]);

    $this->actingAs($this->admin)
        ->get('/admin/agent-orders/report?tab=agents')
        ->assertSuccessful()
        ->assertSee('Active Agents')
        ->assertSee('Total Revenue')
        ->assertSee('Avg Revenue / Agent')
        ->assertSee('Agent Performance Detail')
        ->assertSee('Agent One')
        ->assertSee('Company Two');
});

test('product detail table shows all products', function () {
    $agent = Agent::factory()->agent()->create();
    $product1 = Product::factory()->create(['name' => 'Expensive Item']);
    $product2 = Product::factory()->create(['name' => 'Cheap Item']);

    $order = ProductOrder::factory()->create([
        'agent_id' => $agent->id,
        'order_date' => now(),
        'status' => 'pending',
        'total_amount' => 600,
    ]);

    ProductOrderItem::factory()->create([
        'order_id' => $order->id,
        'product_id' => $product1->id,
        'product_name' => $product1->name,
        'quantity_ordered' => 2,
        'unit_price' => 200,
        'total_price' => 400,
    ]);

    ProductOrderItem::factory()->create([
        'order_id' => $order->id,
        'product_id' => $product2->id,
        'product_name' => $product2->name,
        'quantity_ordered' => 4,
        'unit_price' => 50,
        'total_price' => 200,
    ]);

    $this->actingAs($this->admin)
        ->get('/admin/agent-orders/report?tab=products')
        ->assertSuccessful()
        ->assertSee('Product Sales Detail')
        ->assertSee('Expensive Item')
        ->assertSee('Cheap Item');
});
