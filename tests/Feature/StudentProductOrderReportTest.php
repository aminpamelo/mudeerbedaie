<?php

use App\Models\Product;
use App\Models\ProductOrder;
use App\Models\ProductOrderItem;
use App\Models\Student;
use App\Models\User;
use Livewire\Volt\Volt;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($this->admin);
});

test('student product order report page loads successfully', function () {
    $response = $this->get(route('admin.reports.student-product-orders'));

    $response->assertStatus(200);
    $response->assertSee('Student Product Order Report');
    $response->assertSee('Comprehensive statistics and insights');
});

test('report displays summary statistics correctly', function () {
    // Create test data
    $student = Student::factory()->create();

    $order1 = ProductOrder::factory()->create([
        'student_id' => $student->id,
        'order_date' => now(),
        'total_amount' => 100.00,
        'status' => 'delivered',
    ]);

    $order2 = ProductOrder::factory()->create([
        'student_id' => $student->id,
        'order_date' => now(),
        'total_amount' => 200.00,
        'status' => 'delivered',
    ]);

    $product = Product::factory()->create();

    ProductOrderItem::create([
        'order_id' => $order1->id,
        'product_id' => $product->id,
        'product_name' => $product->name,
        'quantity_ordered' => 2,
        'unit_price' => 50.00,
        'total_price' => 100.00,
    ]);

    ProductOrderItem::create([
        'order_id' => $order2->id,
        'product_id' => $product->id,
        'product_name' => $product->name,
        'quantity_ordered' => 4,
        'unit_price' => 50.00,
        'total_price' => 200.00,
    ]);

    Volt::test('admin.reports.student-product-orders')
        ->assertSet('totalRevenue', 300.00)
        ->assertSet('totalOrders', 2)
        ->assertSet('totalStudents', 1)
        ->assertSet('avgOrderValue', 150.00)
        ->assertSet('totalItems', 6)
        ->assertSet('avgItemsPerOrder', 3.0);
});

test('report filters by year correctly', function () {
    $student = Student::factory()->create();

    // Order in 2024
    ProductOrder::factory()->create([
        'student_id' => $student->id,
        'order_date' => '2024-06-15',
        'total_amount' => 100.00,
        'status' => 'delivered',
    ]);

    // Order in 2025
    ProductOrder::factory()->create([
        'student_id' => $student->id,
        'order_date' => '2025-03-10',
        'total_amount' => 200.00,
        'status' => 'delivered',
    ]);

    Volt::test('admin.reports.student-product-orders')
        ->set('selectedYear', 2024)
        ->assertSet('totalOrders', 1)
        ->assertSet('totalRevenue', 100.00)
        ->set('selectedYear', 2025)
        ->assertSet('totalOrders', 1)
        ->assertSet('totalRevenue', 200.00);
});

test('report filters by month correctly', function () {
    $student = Student::factory()->create();

    // Order in January
    ProductOrder::factory()->create([
        'student_id' => $student->id,
        'order_date' => now()->year.'-01-15',
        'total_amount' => 100.00,
        'status' => 'delivered',
    ]);

    // Order in June
    ProductOrder::factory()->create([
        'student_id' => $student->id,
        'order_date' => now()->year.'-06-10',
        'total_amount' => 200.00,
        'status' => 'delivered',
    ]);

    Volt::test('admin.reports.student-product-orders')
        ->set('selectedYear', now()->year)
        ->set('selectedMonth', '1')
        ->assertSet('totalOrders', 1)
        ->assertSet('totalRevenue', 100.00)
        ->set('selectedMonth', '6')
        ->assertSet('totalOrders', 1)
        ->assertSet('totalRevenue', 200.00)
        ->set('selectedMonth', 'all')
        ->assertSet('totalOrders', 2)
        ->assertSet('totalRevenue', 300.00);
});

test('report filters by status correctly', function () {
    $student = Student::factory()->create();

    ProductOrder::factory()->create([
        'student_id' => $student->id,
        'order_date' => now(),
        'total_amount' => 100.00,
        'status' => 'pending',
    ]);

    ProductOrder::factory()->create([
        'student_id' => $student->id,
        'order_date' => now(),
        'total_amount' => 200.00,
        'status' => 'delivered',
    ]);

    ProductOrder::factory()->create([
        'student_id' => $student->id,
        'order_date' => now(),
        'total_amount' => 150.00,
        'status' => 'cancelled',
    ]);

    Volt::test('admin.reports.student-product-orders')
        ->set('selectedYear', now()->year)
        ->set('selectedStatus', 'all')
        ->assertSet('totalOrders', 2)
        ->assertSet('totalRevenue', 300.00)
        ->set('selectedStatus', 'pending')
        ->assertSet('totalOrders', 1)
        ->assertSet('totalRevenue', 100.00)
        ->set('selectedStatus', 'delivered')
        ->assertSet('totalOrders', 1)
        ->assertSet('totalRevenue', 200.00);
});

test('report shows top students by spending', function () {
    $student1 = Student::factory()->create();
    $student2 = Student::factory()->create();

    // Student 1: 3 orders totaling 600
    ProductOrder::factory()->count(3)->create([
        'student_id' => $student1->id,
        'order_date' => now(),
        'total_amount' => 200.00,
        'status' => 'delivered',
    ]);

    // Student 2: 2 orders totaling 300
    ProductOrder::factory()->count(2)->create([
        'student_id' => $student2->id,
        'order_date' => now(),
        'total_amount' => 150.00,
        'status' => 'delivered',
    ]);

    $component = Volt::test('admin.reports.student-product-orders')
        ->set('selectedYear', now()->year);

    $topStudents = $component->get('topStudents');

    expect($topStudents)->toHaveCount(2);
    expect($topStudents[0]['total_spent'])->toEqual(600.00);
    expect($topStudents[0]['order_count'])->toBe(3);
    expect($topStudents[1]['total_spent'])->toEqual(300.00);
    expect($topStudents[1]['order_count'])->toBe(2);
});

test('report shows top products by quantity', function () {
    $student = Student::factory()->create();
    $product1 = Product::factory()->create(['name' => 'Product A']);
    $product2 = Product::factory()->create(['name' => 'Product B']);

    $order = ProductOrder::factory()->create([
        'student_id' => $student->id,
        'order_date' => now(),
        'total_amount' => 500.00,
        'status' => 'delivered',
    ]);

    // Product A: 10 units
    ProductOrderItem::create([
        'order_id' => $order->id,
        'product_id' => $product1->id,
        'product_name' => $product1->name,
        'quantity_ordered' => 10,
        'unit_price' => 30.00,
        'total_price' => 300.00,
    ]);

    // Product B: 5 units
    ProductOrderItem::create([
        'order_id' => $order->id,
        'product_id' => $product2->id,
        'product_name' => $product2->name,
        'quantity_ordered' => 5,
        'unit_price' => 40.00,
        'total_price' => 200.00,
    ]);

    $component = Volt::test('admin.reports.student-product-orders')
        ->set('selectedYear', now()->year);

    $topProducts = $component->get('topProducts');

    expect($topProducts)->toHaveCount(2);
    expect($topProducts[0]['name'])->toBe('Product A');
    expect($topProducts[0]['total_quantity'])->toBe(10);
    expect($topProducts[1]['name'])->toBe('Product B');
    expect($topProducts[1]['total_quantity'])->toBe(5);
});

test('report shows monthly trend data', function () {
    $student = Student::factory()->create();

    // Create orders in different months
    ProductOrder::factory()->create([
        'student_id' => $student->id,
        'order_date' => now()->year.'-01-15',
        'total_amount' => 100.00,
        'status' => 'delivered',
    ]);

    ProductOrder::factory()->create([
        'student_id' => $student->id,
        'order_date' => now()->year.'-01-20',
        'total_amount' => 150.00,
        'status' => 'delivered',
    ]);

    ProductOrder::factory()->create([
        'student_id' => $student->id,
        'order_date' => now()->year.'-06-10',
        'total_amount' => 200.00,
        'status' => 'delivered',
    ]);

    $component = Volt::test('admin.reports.student-product-orders')
        ->set('selectedYear', now()->year);

    $monthlyTrend = $component->get('monthlyTrend');

    expect($monthlyTrend)->toHaveCount(12);
    expect($monthlyTrend[0]['order_count'])->toBe(2);
    expect($monthlyTrend[0]['revenue'])->toEqual(250.00);
    expect($monthlyTrend[5]['order_count'])->toBe(1);
    expect($monthlyTrend[5]['revenue'])->toEqual(200.00);
});

test('report shows recent orders', function () {
    $student = Student::factory()->create();

    ProductOrder::factory()->count(15)->create([
        'student_id' => $student->id,
        'order_date' => now(),
        'total_amount' => 100.00,
        'status' => 'delivered',
    ]);

    $component = Volt::test('admin.reports.student-product-orders')
        ->set('selectedYear', now()->year);

    $recentOrders = $component->get('recentOrders');

    expect($recentOrders)->toHaveCount(10); // Should only show 10 most recent
});

test('report excludes cancelled and refunded orders by default', function () {
    $student = Student::factory()->create();

    ProductOrder::factory()->create([
        'student_id' => $student->id,
        'order_date' => now(),
        'total_amount' => 100.00,
        'status' => 'delivered',
    ]);

    ProductOrder::factory()->create([
        'student_id' => $student->id,
        'order_date' => now(),
        'total_amount' => 200.00,
        'status' => 'cancelled',
    ]);

    ProductOrder::factory()->create([
        'student_id' => $student->id,
        'order_date' => now(),
        'total_amount' => 150.00,
        'status' => 'refunded',
    ]);

    Volt::test('admin.reports.student-product-orders')
        ->set('selectedYear', now()->year)
        ->set('selectedStatus', 'all')
        ->assertSet('totalOrders', 1)
        ->assertSet('totalRevenue', 100.00);
});

test('report can export CSV', function () {
    $student = Student::factory()->create();

    ProductOrder::factory()->create([
        'student_id' => $student->id,
        'order_date' => now(),
        'total_amount' => 100.00,
        'status' => 'delivered',
    ]);

    $component = Volt::test('admin.reports.student-product-orders')
        ->set('selectedYear', now()->year)
        ->call('exportCsv');

    // The exportCsv method returns a StreamedResponse, which the Livewire test wrapper captures
    // We just need to ensure the call doesn't throw an exception
    expect($component)->toBeInstanceOf(\Livewire\Features\SupportTesting\Testable::class);
});

test('only admin can access student product order report', function () {
    // Create non-admin user
    $user = User::factory()->create(['role' => 'student']);
    $this->actingAs($user);

    $response = $this->get(route('admin.reports.student-product-orders'));

    $response->assertStatus(403);
});
