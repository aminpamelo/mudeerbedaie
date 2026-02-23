<?php

declare(strict_types=1);

use App\Models\ProductOrder;
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
