<?php

declare(strict_types=1);

use App\Models\Product;
use App\Models\ProductOrder;
use App\Models\SalesSource;
use App\Models\User;
use Livewire\Volt\Volt;

test('sales sources page is accessible by admin', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('admin.sales-sources'))
        ->assertSuccessful();
});

test('sales sources page is not accessible by students', function () {
    $student = User::factory()->student()->create();

    $this->actingAs($student)
        ->get(route('admin.sales-sources'))
        ->assertForbidden();
});

test('can create a sales source', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    Volt::test('admin.sales-sources')
        ->set('name', 'Test Source')
        ->set('description', 'A test source')
        ->set('color', '#FF5733')
        ->call('save')
        ->assertHasNoErrors();

    expect(SalesSource::where('name', 'Test Source')->exists())->toBeTrue();
});

test('can toggle sales source active status', function () {
    $admin = User::factory()->admin()->create();
    $source = SalesSource::factory()->create(['is_active' => true]);
    $this->actingAs($admin);

    Volt::test('admin.sales-sources')
        ->call('toggleActive', $source->id);

    expect($source->fresh()->is_active)->toBeFalse();
});

test('cannot delete sales source with orders', function () {
    $admin = User::factory()->admin()->create();
    $source = SalesSource::factory()->create();
    ProductOrder::factory()->create(['sales_source_id' => $source->id]);
    $this->actingAs($admin);

    Volt::test('admin.sales-sources')
        ->call('deleteSource', $source->id);

    expect(SalesSource::find($source->id))->not->toBeNull();
});

test('can delete sales source without orders', function () {
    $admin = User::factory()->admin()->create();
    $source = SalesSource::factory()->create();
    $this->actingAs($admin);

    Volt::test('admin.sales-sources')
        ->call('deleteSource', $source->id);

    expect(SalesSource::find($source->id))->toBeNull();
});

test('api returns active sales sources', function () {
    $admin = User::factory()->admin()->create();
    SalesSource::factory()->create(['name' => 'Lead', 'is_active' => true]);
    SalesSource::factory()->create(['name' => 'Inactive', 'is_active' => false]);

    $this->actingAs($admin, 'sanctum')
        ->getJson('/api/pos/sales-sources')
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Lead');
});

test('pos sale requires sales_source_id', function () {
    $admin = User::factory()->admin()->create();
    $product = Product::factory()->create(['status' => 'active']);

    $this->actingAs($admin, 'sanctum')
        ->postJson('/api/pos/sales', [
            'payment_method' => 'cash',
            'payment_status' => 'paid',
            'customer_name' => 'Test Customer',
            'customer_phone' => '0123456789',
            'items' => [
                [
                    'itemable_type' => 'product',
                    'itemable_id' => $product->id,
                    'quantity' => 1,
                    'unit_price' => 10.00,
                ],
            ],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('sales_source_id');
});

test('pos sale stores sales_source_id on order', function () {
    $admin = User::factory()->admin()->create();
    $source = SalesSource::factory()->create();
    $product = Product::factory()->create(['status' => 'active', 'base_price' => 10.00]);

    $this->actingAs($admin, 'sanctum')
        ->postJson('/api/pos/sales', [
            'payment_method' => 'cash',
            'payment_status' => 'paid',
            'customer_name' => 'Test Customer',
            'customer_phone' => '0123456789',
            'sales_source_id' => $source->id,
            'items' => [
                [
                    'itemable_type' => 'product',
                    'itemable_id' => $product->id,
                    'quantity' => 1,
                    'unit_price' => 10.00,
                ],
            ],
        ])
        ->assertCreated();

    $order = ProductOrder::where('source', 'pos')->latest('id')->first();
    expect($order->sales_source_id)->toBe($source->id);
});
