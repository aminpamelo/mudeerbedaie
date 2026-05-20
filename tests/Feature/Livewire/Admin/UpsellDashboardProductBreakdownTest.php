<?php

declare(strict_types=1);

use App\Models\ClassSession;
use App\Models\FunnelOrder;
use App\Models\Product;
use App\Models\ProductOrder;
use App\Models\ProductOrderItem;
use App\Models\User;
use Livewire\Volt\Volt;

it('renders by-product section listing paid products', function () {
    $admin = User::factory()->admin()->create();
    $teacher = User::factory()->create();

    $session = ClassSession::factory()->create([
        'session_date' => now()->startOfMonth()->addDays(2),
        'upsell_funnel_ids' => [1],
        'upsell_teacher_ids' => [$teacher->id],
        'upsell_teacher_commission_rate' => 10,
    ]);

    $product = Product::factory()->create(['name' => 'Stellar Course']);

    $paid = ProductOrder::factory()->create(['payment_status' => 'paid']);
    ProductOrderItem::factory()->create([
        'order_id' => $paid->id,
        'product_id' => $product->id,
        'product_name' => 'Stellar Course',
        'quantity_ordered' => 1,
        'total_price' => 500,
    ]);

    FunnelOrder::factory()->create([
        'funnel_id' => 1,
        'class_session_id' => $session->id,
        'product_order_id' => $paid->id,
        'order_type' => 'main',
        'funnel_revenue' => 500,
    ]);

    $this->actingAs($admin);

    Volt::test('admin.upsell-dashboard')
        ->assertSee('Performance by Product')
        ->assertSee('Stellar Course')
        ->assertSee('Main') // ucfirst of line_type
        ->assertSee('500.00');
});

it('shows products sorted by revenue descending with different line types', function () {
    $admin = User::factory()->admin()->create();
    $teacher = User::factory()->create();

    $session = ClassSession::factory()->create([
        'session_date' => now()->startOfMonth()->addDays(2),
        'upsell_funnel_ids' => [1],
        'upsell_teacher_ids' => [$teacher->id],
        'upsell_teacher_commission_rate' => 10,
    ]);

    $mainProduct = Product::factory()->create(['name' => 'AAA Main Item']);
    $bumpProduct = Product::factory()->create(['name' => 'BBB Bump Item']);

    $mainOrder = ProductOrder::factory()->create(['payment_status' => 'paid']);
    ProductOrderItem::factory()->create([
        'order_id' => $mainOrder->id,
        'product_id' => $mainProduct->id,
        'product_name' => 'AAA Main Item',
        'quantity_ordered' => 1,
        'total_price' => 1000,
    ]);

    $bumpOrder = ProductOrder::factory()->create(['payment_status' => 'paid']);
    ProductOrderItem::factory()->create([
        'order_id' => $bumpOrder->id,
        'product_id' => $bumpProduct->id,
        'product_name' => 'BBB Bump Item',
        'quantity_ordered' => 1,
        'total_price' => 200,
    ]);

    FunnelOrder::factory()->create([
        'funnel_id' => 1,
        'class_session_id' => $session->id,
        'product_order_id' => $mainOrder->id,
        'order_type' => 'main',
        'funnel_revenue' => 1000,
    ]);
    FunnelOrder::factory()->create([
        'funnel_id' => 1,
        'class_session_id' => $session->id,
        'product_order_id' => $bumpOrder->id,
        'order_type' => 'bump',
        'funnel_revenue' => 200,
    ]);

    $this->actingAs($admin);

    Volt::test('admin.upsell-dashboard')
        ->assertSeeInOrder(['AAA Main Item', 'BBB Bump Item'])
        ->assertSee('Bump');
});

it('shows empty state when no products', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    Volt::test('admin.upsell-dashboard')
        ->assertSee('No product data in selected period');
});
