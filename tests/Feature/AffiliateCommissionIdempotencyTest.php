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
use App\Services\Funnel\AffiliateCommissionService;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

/**
 * Builds the minimal graph needed for an affiliate-eligible order.
 *
 * @return array{order: FunnelOrder, session: FunnelSession, affiliate: FunnelAffiliate}
 */
function makeAffiliateOrder(): array
{
    $affiliate = FunnelAffiliate::factory()->create();
    $funnel = Funnel::factory()->affiliateEnabled()->create();

    $affiliate->funnels()->attach($funnel->id, ['status' => 'approved', 'joined_at' => now()]);

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

    $order = FunnelOrder::create([
        'funnel_id' => $funnel->id,
        'session_id' => $session->id,
        'product_order_id' => ProductOrder::factory()->create()->id,
        'step_id' => $step->id,
        'order_type' => 'main',
        'funnel_revenue' => 100.00,
    ]);

    return ['order' => $order, 'session' => $session, 'affiliate' => $affiliate];
}

test('calculateCommission is idempotent — calling it twice creates only one commission', function () {
    ['order' => $order, 'session' => $session] = makeAffiliateOrder();

    $service = app(AffiliateCommissionService::class);

    $first = $service->calculateCommission($order, $session);
    $second = $service->calculateCommission($order, $session);

    expect(FunnelAffiliateCommission::where('funnel_order_id', $order->id)->count())->toBe(1);
    expect($second->id)->toBe($first->id);
});

test('the funnel_order_id unique index rejects a second commission for the same order', function () {
    ['order' => $order, 'affiliate' => $affiliate] = makeAffiliateOrder();
    $funnelId = $order->funnel_id;

    FunnelAffiliateCommission::create([
        'affiliate_id' => $affiliate->id,
        'funnel_id' => $funnelId,
        'funnel_order_id' => $order->id,
        'commission_type' => 'percentage',
        'commission_rate' => 10,
        'order_amount' => 100,
        'commission_amount' => 10,
        'status' => 'pending',
    ]);

    expect(fn () => FunnelAffiliateCommission::create([
        'affiliate_id' => $affiliate->id,
        'funnel_id' => $funnelId,
        'funnel_order_id' => $order->id,
        'commission_type' => 'percentage',
        'commission_rate' => 10,
        'order_amount' => 100,
        'commission_amount' => 10,
        'status' => 'pending',
    ]))->toThrow(\Illuminate\Database\QueryException::class);
});
