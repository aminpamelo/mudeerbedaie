<?php

use App\Models\ProductOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('allows admin to download an order receipt PDF', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $order = ProductOrder::factory()->create(['payment_status' => 'paid']);

    $response = $this->actingAs($admin)
        ->get(route('admin.orders.receipt-pdf', $order));

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('application/pdf');
});

it('allows employee to download an order receipt PDF', function () {
    $employee = User::factory()->create(['role' => 'employee']);
    $order = ProductOrder::factory()->create(['payment_status' => 'paid']);

    $response = $this->actingAs($employee)
        ->get(route('admin.orders.receipt-pdf', $order));

    $response->assertOk();
});

it('allows accountant to download an order receipt PDF', function () {
    $accountant = User::factory()->create(['role' => 'accountant']);
    $order = ProductOrder::factory()->create(['payment_status' => 'paid']);

    $response = $this->actingAs($accountant)
        ->get(route('admin.orders.receipt-pdf', $order));

    $response->assertOk();
});

it('forbids non-admin/employee/accountant from downloading', function () {
    $student = User::factory()->create(['role' => 'student']);
    $order = ProductOrder::factory()->create();

    $this->actingAs($student)
        ->get(route('admin.orders.receipt-pdf', $order))
        ->assertForbidden();
});

it('returns 404 for non-existent order', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->get('/admin/product-orders/99999999/receipt-pdf')
        ->assertNotFound();
});

it('requires authentication to download an order receipt PDF', function () {
    $order = ProductOrder::factory()->create();

    $this->get(route('admin.orders.receipt-pdf', $order))
        ->assertRedirect(route('login'));
});
