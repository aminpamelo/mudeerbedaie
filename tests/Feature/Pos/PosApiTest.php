<?php

use App\Models\Course;
use App\Models\Package;
use App\Models\Product;
use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('products endpoint returns active products', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    Product::factory()->create(['name' => 'Active Product', 'status' => 'active']);
    Product::factory()->create(['name' => 'Draft Product', 'status' => 'draft']);

    $response = $this->actingAs($admin)
        ->getJson(route('api.pos.products'));

    $response->assertSuccessful();
    $response->assertJsonFragment(['name' => 'Active Product']);
    $response->assertJsonMissing(['name' => 'Draft Product']);
});

test('products endpoint supports search', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    Product::factory()->create(['name' => 'Blue Widget', 'status' => 'active']);
    Product::factory()->create(['name' => 'Red Gadget', 'status' => 'active']);

    $response = $this->actingAs($admin)
        ->getJson(route('api.pos.products', ['search' => 'Blue']));

    $response->assertSuccessful();
    $response->assertJsonFragment(['name' => 'Blue Widget']);
    $response->assertJsonMissing(['name' => 'Red Gadget']);
});

test('packages endpoint returns active packages', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    Package::factory()->create(['name' => 'Active Package', 'status' => 'active']);
    Package::factory()->create(['name' => 'Inactive Package', 'status' => 'inactive']);

    $response = $this->actingAs($admin)
        ->getJson(route('api.pos.packages'));

    $response->assertSuccessful();
});

test('courses endpoint returns published courses', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    Course::factory()->create(['name' => 'Published Course', 'status' => 'published']);
    Course::factory()->create(['name' => 'Draft Course', 'status' => 'draft']);

    $response = $this->actingAs($admin)
        ->getJson(route('api.pos.courses'));

    $response->assertSuccessful();
    $response->assertJsonFragment(['name' => 'Published Course']);
    $response->assertJsonMissing(['name' => 'Draft Course']);
});

test('customers endpoint requires minimum search length', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $response = $this->actingAs($admin)
        ->getJson(route('api.pos.customers', ['search' => 'a']));

    $response->assertSuccessful();
    $response->assertJsonPath('data', []);
});

test('customers endpoint returns matching users', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    User::factory()->create(['name' => 'John Doe', 'email' => 'john@test.com']);
    User::factory()->create(['name' => 'Jane Smith', 'email' => 'jane@test.com']);

    $response = $this->actingAs($admin)
        ->getJson(route('api.pos.customers', ['search' => 'John']));

    $response->assertSuccessful();
    $response->assertJsonCount(1, 'data');
    $response->assertJsonFragment(['name' => 'John Doe']);
});

test('sales history endpoint returns paginated sales', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    \App\Models\PosSale::factory()->count(3)->create(['salesperson_id' => $admin->id]);

    $response = $this->actingAs($admin)
        ->getJson(route('api.pos.sales.index'));

    $response->assertSuccessful();
    $response->assertJsonCount(3, 'data');
});

test('sales history supports search by sale number', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $sale = \App\Models\PosSale::factory()->create([
        'salesperson_id' => $admin->id,
        'sale_number' => 'POS-20260204-0001',
    ]);
    \App\Models\PosSale::factory()->create([
        'salesperson_id' => $admin->id,
        'sale_number' => 'POS-20260204-0002',
    ]);

    $response = $this->actingAs($admin)
        ->getJson(route('api.pos.sales.index', ['search' => '0001']));

    $response->assertSuccessful();
    $response->assertJsonCount(1, 'data');
});

test('sale detail returns sale with items', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $sale = \App\Models\PosSale::factory()->create(['salesperson_id' => $admin->id]);
    \App\Models\PosSaleItem::factory()->create(['pos_sale_id' => $sale->id]);

    $response = $this->actingAs($admin)
        ->getJson(route('api.pos.sales.show', $sale));

    $response->assertSuccessful();
    $response->assertJsonPath('data.id', $sale->id);
});

test('dashboard returns today stats', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    \App\Models\PosSale::factory()->create([
        'salesperson_id' => $admin->id,
        'payment_status' => 'paid',
        'total_amount' => 100,
        'sale_date' => now(),
    ]);

    $response = $this->actingAs($admin)
        ->getJson(route('api.pos.dashboard'));

    $response->assertSuccessful();
    $response->assertJsonStructure([
        'data' => ['today_sales_count', 'today_revenue', 'my_sales_count', 'my_revenue'],
    ]);
    $response->assertJsonPath('data.my_sales_count', 1);
});
