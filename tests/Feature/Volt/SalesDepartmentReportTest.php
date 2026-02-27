<?php

declare(strict_types=1);

use App\Models\ProductOrder;
use App\Models\ProductOrderItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;

uses(RefreshDatabase::class);

test('admin can access sales department report', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get('/admin/reports/sales-department')
        ->assertSuccessful();
});

test('sales user can access sales department report', function () {
    $sales = User::factory()->sales()->create();

    $this->actingAs($sales)
        ->get('/admin/reports/sales-department')
        ->assertSuccessful();
});

test('student cannot access sales department report', function () {
    $student = User::factory()->student()->create();

    $this->actingAs($student)
        ->get('/admin/reports/sales-department')
        ->assertForbidden();
});

test('teacher cannot access sales department report', function () {
    $teacher = User::factory()->teacher()->create();

    $this->actingAs($teacher)
        ->get('/admin/reports/sales-department')
        ->assertForbidden();
});

test('guest is redirected to login', function () {
    $this->get('/admin/reports/sales-department')
        ->assertRedirect('/login');
});

test('report shows data from all salespersons', function () {
    $admin = User::factory()->admin()->create();
    $salesUser1 = User::factory()->sales()->create();
    $salesUser2 = User::factory()->sales()->create();

    ProductOrder::factory()->create([
        'source' => 'pos',
        'paid_time' => now(),
        'order_date' => now(),
        'total_amount' => 100.00,
        'metadata' => ['pos_sale' => true, 'salesperson_id' => $salesUser1->id, 'salesperson_name' => $salesUser1->name],
    ]);

    ProductOrder::factory()->create([
        'source' => 'pos',
        'paid_time' => now(),
        'order_date' => now(),
        'total_amount' => 200.00,
        'metadata' => ['pos_sale' => true, 'salesperson_id' => $salesUser2->id, 'salesperson_name' => $salesUser2->name],
    ]);

    $this->actingAs($admin);

    Volt::test('admin.reports.sales-department')
        ->assertSee($salesUser1->name)
        ->assertSee($salesUser2->name);
});

test('salesperson filter narrows results', function () {
    $admin = User::factory()->admin()->create();
    $salesUser1 = User::factory()->sales()->create();
    $salesUser2 = User::factory()->sales()->create();

    ProductOrder::factory()->create([
        'source' => 'pos',
        'paid_time' => now(),
        'order_date' => now(),
        'total_amount' => 100.00,
        'metadata' => ['pos_sale' => true, 'salesperson_id' => $salesUser1->id, 'salesperson_name' => $salesUser1->name],
    ]);

    ProductOrder::factory()->create([
        'source' => 'pos',
        'paid_time' => now(),
        'order_date' => now(),
        'total_amount' => 200.00,
        'metadata' => ['pos_sale' => true, 'salesperson_id' => $salesUser2->id, 'salesperson_name' => $salesUser2->name],
    ]);

    $this->actingAs($admin);

    Volt::test('admin.reports.sales-department')
        ->set('selectedSalesperson', (string) $salesUser1->id)
        ->set('selectedPeriod', 'all')
        ->assertSet('summary.total_revenue', 100.0);
});

test('status filter works', function () {
    $admin = User::factory()->admin()->create();

    ProductOrder::factory()->create([
        'source' => 'pos',
        'paid_time' => now(),
        'order_date' => now(),
        'total_amount' => 100.00,
        'metadata' => ['pos_sale' => true, 'salesperson_id' => $admin->id, 'salesperson_name' => $admin->name],
    ]);

    ProductOrder::factory()->create([
        'source' => 'pos',
        'paid_time' => null,
        'status' => 'pending',
        'order_date' => now(),
        'total_amount' => 50.00,
        'metadata' => ['pos_sale' => true, 'salesperson_id' => $admin->id, 'salesperson_name' => $admin->name],
    ]);

    $this->actingAs($admin);

    Volt::test('admin.reports.sales-department')
        ->set('selectedStatus', 'paid')
        ->set('selectedPeriod', 'all')
        ->assertSet('summary.total_revenue', 100.0)
        ->assertSet('summary.total_orders', 1);
});

test('salesperson filter narrows monthly pivot data', function () {
    $admin = User::factory()->admin()->create();
    $salesUser1 = User::factory()->sales()->create();
    $salesUser2 = User::factory()->sales()->create();

    ProductOrder::factory()->create([
        'source' => 'pos',
        'paid_time' => now(),
        'order_date' => now(),
        'total_amount' => 100.00,
        'metadata' => ['pos_sale' => true, 'salesperson_id' => $salesUser1->id, 'salesperson_name' => $salesUser1->name],
    ]);

    ProductOrder::factory()->create([
        'source' => 'pos',
        'paid_time' => now(),
        'order_date' => now(),
        'total_amount' => 200.00,
        'metadata' => ['pos_sale' => true, 'salesperson_id' => $salesUser2->id, 'salesperson_name' => $salesUser2->name],
    ]);

    $this->actingAs($admin);

    $currentMonth = (int) now()->format('m');

    $component = Volt::test('admin.reports.sales-department')
        ->set('selectedSalesperson', (string) $salesUser1->id)
        ->set('selectedPeriod', 'all');

    $pivotData = $component->get('monthlyPivotData');
    $monthRow = collect($pivotData)->firstWhere('month', $currentMonth);

    expect($monthRow['total_sales'])->toBe(1);
    expect($monthRow['total_revenue'])->toBe(100.0);
});

test('monthly data reflects status filter', function () {
    $admin = User::factory()->admin()->create();

    ProductOrder::factory()->create([
        'source' => 'pos',
        'paid_time' => now(),
        'order_date' => now(),
        'total_amount' => 100.00,
        'metadata' => ['pos_sale' => true, 'salesperson_id' => $admin->id, 'salesperson_name' => $admin->name],
    ]);

    ProductOrder::factory()->create([
        'source' => 'pos',
        'paid_time' => null,
        'status' => 'pending',
        'order_date' => now(),
        'total_amount' => 50.00,
        'metadata' => ['pos_sale' => true, 'salesperson_id' => $admin->id, 'salesperson_name' => $admin->name],
    ]);

    $this->actingAs($admin);

    $currentMonth = (int) now()->format('m');

    // monthlyData only shows paid orders, so status=paid should still show 1
    $component = Volt::test('admin.reports.sales-department')
        ->set('selectedStatus', 'paid')
        ->set('selectedPeriod', 'all');

    $monthlyData = $component->get('monthlyData');
    $monthRow = collect($monthlyData)->firstWhere('month', $currentMonth);

    expect($monthRow['sales_count'])->toBe(1);
    expect($monthRow['revenue'])->toBe(100.0);
});

test('export csv returns downloadable response', function () {
    $admin = User::factory()->admin()->create();

    ProductOrder::factory()->create([
        'source' => 'pos',
        'paid_time' => now(),
        'order_date' => now(),
        'total_amount' => 100.00,
        'metadata' => ['pos_sale' => true, 'salesperson_id' => $admin->id, 'salesperson_name' => $admin->name],
    ]);

    $this->actingAs($admin);

    Volt::test('admin.reports.sales-department')
        ->set('selectedPeriod', 'all')
        ->call('exportCsv')
        ->assertFileDownloaded();
});

// ===== Product Report Sub-Tab Tests =====

test('report sub-tab defaults to team sales', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin);

    Volt::test('admin.reports.sales-department')
        ->assertSet('reportSubTab', 'team_sales');
});

test('can switch to product report sub-tab', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin);

    Volt::test('admin.reports.sales-department')
        ->call('setReportSubTab', 'product_report')
        ->assertSet('reportSubTab', 'product_report');
});

test('product report shows product summary data', function () {
    $admin = User::factory()->admin()->create();

    $order = ProductOrder::factory()->create([
        'source' => 'pos',
        'paid_time' => now(),
        'order_date' => now(),
        'total_amount' => 150.00,
        'metadata' => ['pos_sale' => true, 'salesperson_id' => $admin->id, 'salesperson_name' => $admin->name],
    ]);

    ProductOrderItem::factory()->create([
        'order_id' => $order->id,
        'product_name' => 'Test Product A',
        'quantity_ordered' => 3,
        'unit_price' => 30.00,
        'total_price' => 90.00,
    ]);

    ProductOrderItem::factory()->create([
        'order_id' => $order->id,
        'product_name' => 'Test Product B',
        'quantity_ordered' => 2,
        'unit_price' => 30.00,
        'total_price' => 60.00,
    ]);

    $this->actingAs($admin);

    $component = Volt::test('admin.reports.sales-department')
        ->set('selectedPeriod', 'all');

    $summary = $component->get('productSummary');

    expect($summary['unique_products'])->toBe(2);
    expect($summary['total_units'])->toBe(5);
    expect($summary['total_revenue'])->toBe(150.0);
});

test('product report shows top products by revenue', function () {
    $admin = User::factory()->admin()->create();

    $order = ProductOrder::factory()->create([
        'source' => 'pos',
        'paid_time' => now(),
        'order_date' => now(),
        'total_amount' => 300.00,
        'metadata' => ['pos_sale' => true, 'salesperson_id' => $admin->id, 'salesperson_name' => $admin->name],
    ]);

    ProductOrderItem::factory()->create([
        'order_id' => $order->id,
        'product_name' => 'Expensive Product',
        'quantity_ordered' => 1,
        'unit_price' => 200.00,
        'total_price' => 200.00,
    ]);

    ProductOrderItem::factory()->create([
        'order_id' => $order->id,
        'product_name' => 'Cheap Product',
        'quantity_ordered' => 5,
        'unit_price' => 20.00,
        'total_price' => 100.00,
    ]);

    $this->actingAs($admin);

    $component = Volt::test('admin.reports.sales-department')
        ->set('selectedPeriod', 'all');

    $topByRevenue = $component->get('topProductsByRevenue');

    expect($topByRevenue)->toHaveCount(2);
    expect($topByRevenue[0]['product_name'])->toBe('Expensive Product');
    expect($topByRevenue[0]['revenue'])->toBe(200.0);
    expect($topByRevenue[1]['product_name'])->toBe('Cheap Product');
});

test('product report shows top products by volume', function () {
    $admin = User::factory()->admin()->create();

    $order = ProductOrder::factory()->create([
        'source' => 'pos',
        'paid_time' => now(),
        'order_date' => now(),
        'total_amount' => 300.00,
        'metadata' => ['pos_sale' => true, 'salesperson_id' => $admin->id, 'salesperson_name' => $admin->name],
    ]);

    ProductOrderItem::factory()->create([
        'order_id' => $order->id,
        'product_name' => 'Expensive Product',
        'quantity_ordered' => 1,
        'unit_price' => 200.00,
        'total_price' => 200.00,
    ]);

    ProductOrderItem::factory()->create([
        'order_id' => $order->id,
        'product_name' => 'Popular Product',
        'quantity_ordered' => 10,
        'unit_price' => 10.00,
        'total_price' => 100.00,
    ]);

    $this->actingAs($admin);

    $component = Volt::test('admin.reports.sales-department')
        ->set('selectedPeriod', 'all');

    $topByVolume = $component->get('topProductsByVolume');

    expect($topByVolume)->toHaveCount(2);
    expect($topByVolume[0]['product_name'])->toBe('Popular Product');
    expect($topByVolume[0]['units_sold'])->toBe(10);
});

test('product report filters by salesperson', function () {
    $admin = User::factory()->admin()->create();
    $salesUser1 = User::factory()->sales()->create();
    $salesUser2 = User::factory()->sales()->create();

    $order1 = ProductOrder::factory()->create([
        'source' => 'pos',
        'paid_time' => now(),
        'order_date' => now(),
        'total_amount' => 100.00,
        'metadata' => ['pos_sale' => true, 'salesperson_id' => $salesUser1->id, 'salesperson_name' => $salesUser1->name],
    ]);

    ProductOrderItem::factory()->create([
        'order_id' => $order1->id,
        'product_name' => 'Product From Sales 1',
        'quantity_ordered' => 2,
        'unit_price' => 50.00,
        'total_price' => 100.00,
    ]);

    $order2 = ProductOrder::factory()->create([
        'source' => 'pos',
        'paid_time' => now(),
        'order_date' => now(),
        'total_amount' => 200.00,
        'metadata' => ['pos_sale' => true, 'salesperson_id' => $salesUser2->id, 'salesperson_name' => $salesUser2->name],
    ]);

    ProductOrderItem::factory()->create([
        'order_id' => $order2->id,
        'product_name' => 'Product From Sales 2',
        'quantity_ordered' => 4,
        'unit_price' => 50.00,
        'total_price' => 200.00,
    ]);

    $this->actingAs($admin);

    $component = Volt::test('admin.reports.sales-department')
        ->set('selectedSalesperson', (string) $salesUser1->id)
        ->set('selectedPeriod', 'all');

    $summary = $component->get('productSummary');

    expect($summary['total_units'])->toBe(2);
    expect($summary['total_revenue'])->toBe(100.0);
});

test('product detail table contains all products sorted by revenue', function () {
    $admin = User::factory()->admin()->create();

    $order = ProductOrder::factory()->create([
        'source' => 'pos',
        'paid_time' => now(),
        'order_date' => now(),
        'total_amount' => 300.00,
        'metadata' => ['pos_sale' => true, 'salesperson_id' => $admin->id, 'salesperson_name' => $admin->name],
    ]);

    ProductOrderItem::factory()->create([
        'order_id' => $order->id,
        'product_name' => 'Low Revenue',
        'quantity_ordered' => 1,
        'unit_price' => 50.00,
        'total_price' => 50.00,
    ]);

    ProductOrderItem::factory()->create([
        'order_id' => $order->id,
        'product_name' => 'High Revenue',
        'quantity_ordered' => 5,
        'unit_price' => 50.00,
        'total_price' => 250.00,
    ]);

    $this->actingAs($admin);

    $component = Volt::test('admin.reports.sales-department')
        ->set('selectedPeriod', 'all');

    $table = $component->get('productDetailTable');

    expect($table)->toHaveCount(2);
    expect($table[0]['product_name'])->toBe('High Revenue');
    expect($table[1]['product_name'])->toBe('Low Revenue');
});
