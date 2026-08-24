<?php

declare(strict_types=1);

use App\Models\Product;
use App\Models\ProductOrder;
use App\Models\ProductOrderItem;
use App\Models\User;
use App\Services\Fighter\FighterProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'admin']);
});

test('report page loads for authenticated admin', function () {
    $this->actingAs($this->admin)
        ->get('/admin/product-orders/report')
        ->assertSuccessful();
});

test('report page shows summary cards', function () {
    ProductOrder::factory()->create([
        'order_date' => now(),
        'status' => 'delivered',
        'total_amount' => 500,
    ]);

    $this->actingAs($this->admin)
        ->get('/admin/product-orders/report')
        ->assertSuccessful()
        ->assertSee('Total Revenue')
        ->assertSee('Total Orders')
        ->assertSee('Avg Order Value')
        ->assertSee('Completion Rate');
});

test('report page shows product insights tab', function () {
    $product = Product::factory()->create(['name' => 'Test Widget XYZ']);

    $order = ProductOrder::factory()->create([
        'order_date' => now(),
        'status' => 'pending',
        'total_amount' => 300,
    ]);

    ProductOrderItem::factory()->create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'product_name' => $product->name,
        'quantity_ordered' => 5,
        'unit_price' => 60,
        'total_price' => 300,
    ]);

    $this->actingAs($this->admin)
        ->get('/admin/product-orders/report?tab=products')
        ->assertSuccessful()
        ->assertSee('Product Sales Detail')
        ->assertSee('Test Widget XYZ');
});

test('report page shows order status tab', function () {
    ProductOrder::factory()->create([
        'order_date' => now(),
        'status' => 'delivered',
        'total_amount' => 100,
    ]);

    ProductOrder::factory()->create([
        'order_date' => now(),
        'status' => 'pending',
        'total_amount' => 200,
    ]);

    $this->actingAs($this->admin)
        ->get('/admin/product-orders/report?tab=status')
        ->assertSuccessful()
        ->assertSee('Order Status Distribution')
        ->assertSee('Delivered')
        ->assertSee('Pending');
});

test('report page shows customer insights tab', function () {
    $customer = User::factory()->create(['name' => 'VIP Customer Test']);

    ProductOrder::factory()->create([
        'customer_id' => $customer->id,
        'customer_name' => $customer->name,
        'order_date' => now(),
        'status' => 'delivered',
        'total_amount' => 1000,
    ]);

    $this->actingAs($this->admin)
        ->get('/admin/product-orders/report?tab=customers')
        ->assertSuccessful()
        ->assertSee('Top Customers')
        ->assertSee('VIP Customer Test');
});

test('fighter orders get their own source bucket, carved out of funnel and pos', function () {
    $fighter = User::factory()->create(['role' => 'fighter']);
    $segment = app(FighterProvisioner::class)->ensureSalesSource($fighter);

    // Fighter-attributed orders (funnel + POS), tagged with the fighter segment.
    ProductOrder::factory()->create(['order_date' => now(), 'status' => 'delivered', 'total_amount' => 100, 'source' => 'funnel', 'sales_source_id' => $segment->id]);
    ProductOrder::factory()->create(['order_date' => now(), 'status' => 'delivered', 'total_amount' => 50, 'source' => 'pos', 'sales_source_id' => $segment->id]);

    // Company (non-fighter) funnel + POS orders — no segment.
    ProductOrder::factory()->create(['order_date' => now(), 'status' => 'delivered', 'total_amount' => 200, 'source' => 'funnel', 'sales_source_id' => null]);
    ProductOrder::factory()->create(['order_date' => now(), 'status' => 'delivered', 'total_amount' => 30, 'source' => 'pos', 'sales_source_id' => null]);

    $this->actingAs($this->admin);

    $component = Volt::test('admin.orders.order-report')
        ->set('selectedYear', (int) now()->year);

    $breakdown = $component->get('sourceBreakdown');

    expect($breakdown['fighter']['orders'])->toBe(2)
        ->and((float) $breakdown['fighter']['revenue'])->toBe(150.0)
        ->and($breakdown['funnel']['orders'])->toBe(1)          // fighter funnel order excluded
        ->and((float) $breakdown['funnel']['revenue'])->toBe(200.0)
        ->and($breakdown['pos']['orders'])->toBe(1)             // fighter POS order excluded
        ->and((float) $breakdown['pos']['revenue'])->toBe(30.0);
});

test('the fighter source filter scopes the report to fighter orders only', function () {
    $fighter = User::factory()->create(['role' => 'fighter']);
    $segment = app(FighterProvisioner::class)->ensureSalesSource($fighter);

    ProductOrder::factory()->create(['order_date' => now(), 'status' => 'delivered', 'total_amount' => 100, 'source' => 'funnel', 'sales_source_id' => $segment->id]);
    ProductOrder::factory()->create(['order_date' => now(), 'status' => 'delivered', 'total_amount' => 200, 'source' => 'funnel', 'sales_source_id' => null]);

    $this->actingAs($this->admin);

    $component = Volt::test('admin.orders.order-report')
        ->set('selectedYear', (int) now()->year)
        ->set('sourceFilter', 'fighter');

    expect($component->get('summary')['total_orders'])->toBe(1);
});

test('the month filter narrows the report to the selected month within the year', function () {
    $year = (int) now()->year;

    ProductOrder::factory()->create(['order_date' => Carbon\Carbon::create($year, 3, 10), 'status' => 'delivered', 'total_amount' => 100]);
    ProductOrder::factory()->create(['order_date' => Carbon\Carbon::create($year, 3, 20), 'status' => 'delivered', 'total_amount' => 150]);
    ProductOrder::factory()->create(['order_date' => Carbon\Carbon::create($year, 7, 5), 'status' => 'delivered', 'total_amount' => 999]);

    $this->actingAs($this->admin);

    $component = Volt::test('admin.orders.order-report')
        ->set('selectedYear', $year)
        ->set('selectedMonth', 3);

    expect($component->get('summary')['total_orders'])->toBe(2)
        ->and((float) $component->get('summary')['total_revenue'])->toBe(250.0);
});

test('a custom date range overrides the year and month filters', function () {
    $year = (int) now()->year;

    ProductOrder::factory()->create(['order_date' => Carbon\Carbon::create($year, 3, 5), 'status' => 'delivered', 'total_amount' => 100]);
    ProductOrder::factory()->create(['order_date' => Carbon\Carbon::create($year, 3, 15), 'status' => 'delivered', 'total_amount' => 200]);
    ProductOrder::factory()->create(['order_date' => Carbon\Carbon::create($year, 3, 25), 'status' => 'delivered', 'total_amount' => 400]);

    $this->actingAs($this->admin);

    $component = Volt::test('admin.orders.order-report')
        ->set('selectedYear', $year)
        ->set('selectedMonth', 7) // deliberately a month with no orders — should be ignored
        ->set('dateFrom', Carbon\Carbon::create($year, 3, 10)->toDateString())
        ->set('dateTo', Carbon\Carbon::create($year, 3, 20)->toDateString());

    expect($component->get('summary')['total_orders'])->toBe(1)
        ->and((float) $component->get('summary')['total_revenue'])->toBe(200.0);
});

test('clearing the custom date range restores the year and month filter', function () {
    $year = (int) now()->year;

    ProductOrder::factory()->create(['order_date' => Carbon\Carbon::create($year, 3, 15), 'status' => 'delivered', 'total_amount' => 200]);
    ProductOrder::factory()->create(['order_date' => Carbon\Carbon::create($year, 7, 15), 'status' => 'delivered', 'total_amount' => 500]);

    $this->actingAs($this->admin);

    $component = Volt::test('admin.orders.order-report')
        ->set('selectedYear', $year)
        ->set('dateFrom', Carbon\Carbon::create($year, 3, 1)->toDateString())
        ->set('dateTo', Carbon\Carbon::create($year, 3, 31)->toDateString());

    expect($component->get('summary')['total_orders'])->toBe(1);

    $component->call('clearCustomRange');

    expect($component->get('summary')['total_orders'])->toBe(2);
});

test('cancelled orders are excluded from revenue calculations', function () {
    ProductOrder::factory()->create([
        'order_date' => now(),
        'status' => 'cancelled',
        'total_amount' => 5000,
    ]);

    ProductOrder::factory()->create([
        'order_date' => now(),
        'status' => 'delivered',
        'total_amount' => 100,
    ]);

    $response = $this->actingAs($this->admin)
        ->get('/admin/product-orders/report')
        ->assertSuccessful();

    // Revenue should not include the 5000 cancelled order
    $response->assertDontSee('5,000.00');
    $response->assertSee('100.00');
});
