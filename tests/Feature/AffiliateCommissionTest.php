<?php

use App\Models\Funnel;
use App\Models\FunnelAffiliate;
use App\Models\FunnelAffiliateCommission;
use App\Models\FunnelAffiliateCommissionRule;
use App\Models\FunnelOrder;
use App\Models\FunnelProduct;
use App\Models\FunnelSession;
use App\Models\FunnelStep;
use App\Models\ProductOrder;
use App\Models\User;
use App\Services\Funnel\AffiliateCommissionService;

use function Pest\Laravel\actingAs;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('commission service creates pending commission for affiliate sale', function () {
    $affiliate = FunnelAffiliate::factory()->create();
    $funnel = Funnel::factory()->affiliateEnabled()->create();

    $affiliate->funnels()->attach($funnel->id, [
        'status' => 'approved',
        'joined_at' => now(),
    ]);

    $step = FunnelStep::create([
        'funnel_id' => $funnel->id,
        'name' => 'Checkout',
        'slug' => 'checkout',
        'type' => 'checkout',
        'sort_order' => 0,
    ]);

    $product = FunnelProduct::create([
        'funnel_step_id' => $step->id,
        'name' => 'Test Product',
        'funnel_price' => 100.00,
    ]);

    FunnelAffiliateCommissionRule::create([
        'funnel_id' => $funnel->id,
        'funnel_product_id' => $product->id,
        'commission_type' => 'percentage',
        'commission_value' => 10,
    ]);

    $session = FunnelSession::factory()->create([
        'funnel_id' => $funnel->id,
        'affiliate_id' => $affiliate->id,
    ]);

    $productOrder = ProductOrder::factory()->create();

    $order = FunnelOrder::create([
        'funnel_id' => $funnel->id,
        'session_id' => $session->id,
        'product_order_id' => $productOrder->id,
        'step_id' => $step->id,
        'order_type' => 'main',
        'funnel_revenue' => 100.00,
    ]);

    $service = app(AffiliateCommissionService::class);
    $service->calculateCommission($order, $session);

    $commission = FunnelAffiliateCommission::where('affiliate_id', $affiliate->id)->first();

    expect($commission)->not->toBeNull();
    expect($commission->status)->toBe('pending');
    expect((float) $commission->commission_amount)->toBe(10.00);
    expect($commission->commission_type)->toBe('percentage');
});

test('commission service does not create commission when affiliate not on session', function () {
    $funnel = Funnel::factory()->affiliateEnabled()->create();

    $step = FunnelStep::create([
        'funnel_id' => $funnel->id,
        'name' => 'Checkout',
        'slug' => 'checkout',
        'type' => 'checkout',
        'sort_order' => 0,
    ]);

    $session = FunnelSession::factory()->create([
        'funnel_id' => $funnel->id,
        'affiliate_id' => null,
    ]);

    $productOrder = ProductOrder::factory()->create();

    $order = FunnelOrder::create([
        'funnel_id' => $funnel->id,
        'session_id' => $session->id,
        'product_order_id' => $productOrder->id,
        'step_id' => $step->id,
        'order_type' => 'main',
        'funnel_revenue' => 100.00,
    ]);

    $service = app(AffiliateCommissionService::class);
    $service->calculateCommission($order, $session);

    expect(FunnelAffiliateCommission::count())->toBe(0);
});

test('fixed commission is calculated correctly', function () {
    $affiliate = FunnelAffiliate::factory()->create();
    $funnel = Funnel::factory()->affiliateEnabled()->create();

    $affiliate->funnels()->attach($funnel->id, [
        'status' => 'approved',
        'joined_at' => now(),
    ]);

    $step = FunnelStep::create([
        'funnel_id' => $funnel->id,
        'name' => 'Checkout',
        'slug' => 'checkout',
        'type' => 'checkout',
        'sort_order' => 0,
    ]);

    $product = FunnelProduct::create([
        'funnel_step_id' => $step->id,
        'name' => 'Test Product',
        'funnel_price' => 200.00,
    ]);

    FunnelAffiliateCommissionRule::create([
        'funnel_id' => $funnel->id,
        'funnel_product_id' => $product->id,
        'commission_type' => 'fixed',
        'commission_value' => 25.00,
    ]);

    $session = FunnelSession::factory()->create([
        'funnel_id' => $funnel->id,
        'affiliate_id' => $affiliate->id,
    ]);

    $productOrder = ProductOrder::factory()->create();

    $order = FunnelOrder::create([
        'funnel_id' => $funnel->id,
        'session_id' => $session->id,
        'product_order_id' => $productOrder->id,
        'step_id' => $step->id,
        'order_type' => 'main',
        'funnel_revenue' => 200.00,
    ]);

    $service = app(AffiliateCommissionService::class);
    $service->calculateCommission($order, $session);

    $commission = FunnelAffiliateCommission::first();

    expect($commission)->not->toBeNull();
    expect((float) $commission->commission_amount)->toBe(25.00);
    expect($commission->commission_type)->toBe('fixed');
});

test('admin can approve commission', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $affiliate = FunnelAffiliate::factory()->create();
    $funnel = Funnel::factory()->affiliateEnabled()->create(['user_id' => $admin->id]);

    $session = FunnelSession::factory()->create(['funnel_id' => $funnel->id]);
    $productOrder = ProductOrder::factory()->create();
    $step = FunnelStep::create([
        'funnel_id' => $funnel->id,
        'name' => 'Checkout',
        'slug' => 'checkout',
        'type' => 'checkout',
        'sort_order' => 0,
    ]);

    $funnelOrder = FunnelOrder::create([
        'funnel_id' => $funnel->id,
        'session_id' => $session->id,
        'product_order_id' => $productOrder->id,
        'step_id' => $step->id,
        'order_type' => 'main',
        'funnel_revenue' => 100,
    ]);

    $commission = FunnelAffiliateCommission::create([
        'affiliate_id' => $affiliate->id,
        'funnel_id' => $funnel->id,
        'funnel_order_id' => $funnelOrder->id,
        'commission_type' => 'percentage',
        'commission_rate' => 10,
        'order_amount' => 100,
        'commission_amount' => 10,
        'status' => 'pending',
    ]);

    actingAs($admin);

    $response = $this->postJson("/api/v1/funnels/{$funnel->uuid}/commissions/{$commission->id}/approve");

    $response->assertSuccessful();

    $commission->refresh();
    expect($commission->status)->toBe('approved');
    expect($commission->approved_by)->toBe($admin->id);
});

test('admin can reject commission with notes', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $affiliate = FunnelAffiliate::factory()->create();
    $funnel = Funnel::factory()->affiliateEnabled()->create(['user_id' => $admin->id]);

    $session = FunnelSession::factory()->create(['funnel_id' => $funnel->id]);
    $productOrder = ProductOrder::factory()->create();
    $step = FunnelStep::create([
        'funnel_id' => $funnel->id,
        'name' => 'Checkout',
        'slug' => 'checkout',
        'type' => 'checkout',
        'sort_order' => 0,
    ]);

    $funnelOrder = FunnelOrder::create([
        'funnel_id' => $funnel->id,
        'session_id' => $session->id,
        'product_order_id' => $productOrder->id,
        'step_id' => $step->id,
        'order_type' => 'main',
        'funnel_revenue' => 100,
    ]);

    $commission = FunnelAffiliateCommission::create([
        'affiliate_id' => $affiliate->id,
        'funnel_id' => $funnel->id,
        'funnel_order_id' => $funnelOrder->id,
        'commission_type' => 'percentage',
        'commission_rate' => 10,
        'order_amount' => 100,
        'commission_amount' => 10,
        'status' => 'pending',
    ]);

    actingAs($admin);

    $response = $this->postJson("/api/v1/funnels/{$funnel->uuid}/commissions/{$commission->id}/reject", [
        'notes' => 'Fraudulent order',
    ]);

    $response->assertSuccessful();

    $commission->refresh();
    expect($commission->status)->toBe('rejected');
    expect($commission->notes)->toBe('Fraudulent order');
});
