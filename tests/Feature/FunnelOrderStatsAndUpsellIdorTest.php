<?php

use App\Models\Funnel;
use App\Models\FunnelOrder;
use App\Models\FunnelProduct;
use App\Models\FunnelSession;
use App\Models\FunnelStep;
use App\Models\ProductOrder;
use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\postJson;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

// FunnelCheckoutController constructs a Stripe client on boot, so a secret must
// exist for the endpoint to be reachable (the ownership check runs before any
// actual Stripe call).
beforeEach(fn () => app(\App\Services\SettingsService::class)->set('stripe_secret_key', 'sk_test_fake'));

function funnelWithCheckoutStep(array $funnelAttrs = []): array
{
    $funnel = Funnel::factory()->create(array_merge(['status' => 'published'], $funnelAttrs));

    $step = FunnelStep::create([
        'funnel_id' => $funnel->id,
        'name' => 'Checkout',
        'slug' => 'checkout',
        'type' => 'checkout',
        'sort_order' => 0,
    ]);

    return [$funnel, $step];
}

function makeFunnelOrder(Funnel $funnel, FunnelStep $step, FunnelSession $session, string $type, float $revenue): FunnelOrder
{
    return FunnelOrder::create([
        'funnel_id' => $funnel->id,
        'session_id' => $session->id,
        'product_order_id' => ProductOrder::factory()->create()->id,
        'step_id' => $step->id,
        'order_type' => $type,
        'funnel_revenue' => $revenue,
    ]);
}

test('one-click upsell rejects an original_order_id from another session (IDOR)', function () {
    [$funnel, $step] = funnelWithCheckoutStep();

    $upsellProduct = FunnelProduct::create([
        'funnel_step_id' => $step->id,
        'name' => 'Upsell',
        'funnel_price' => 50,
        'is_active' => true,
    ]);

    $sessionA = FunnelSession::factory()->create(['funnel_id' => $funnel->id]);
    $sessionB = FunnelSession::factory()->create(['funnel_id' => $funnel->id]);

    // The victim's order belongs to session B.
    $victimOrder = makeFunnelOrder($funnel, $step, $sessionB, 'main', 100);

    // Attacker acts in session A but references the victim's order id.
    postJson("/api/v1/funnel-checkout/{$funnel->uuid}/steps/{$step->id}/upsell", [
        'session_uuid' => $sessionA->uuid,
        'product_id' => $upsellProduct->id,
        'original_order_id' => $victimOrder->id,
    ])->assertNotFound();
});

test('one-click upsell accepts an original_order_id from the same session', function () {
    [$funnel, $step] = funnelWithCheckoutStep();

    $upsellProduct = FunnelProduct::create([
        'funnel_step_id' => $step->id,
        'name' => 'Upsell',
        'funnel_price' => 50,
        'is_active' => true,
    ]);

    $session = FunnelSession::factory()->create(['funnel_id' => $funnel->id]);
    $ownOrder = makeFunnelOrder($funnel, $step, $session, 'main', 100);

    // Same-session order passes the ownership check; without auth/saved card it
    // then stops at "payment required" (402) — the point is it is NOT a 404.
    $response = postJson("/api/v1/funnel-checkout/{$funnel->uuid}/steps/{$step->id}/upsell", [
        'session_uuid' => $session->uuid,
        'product_id' => $upsellProduct->id,
        'original_order_id' => $ownOrder->id,
    ]);

    expect($response->getStatusCode())->not->toBe(404);
});

test('funnel order stats returns correct grouped aggregates', function () {
    $owner = User::factory()->create(['role' => 'admin']);
    [$funnel, $step] = funnelWithCheckoutStep(['user_id' => $owner->id]);

    $session = FunnelSession::factory()->create(['funnel_id' => $funnel->id]);

    makeFunnelOrder($funnel, $step, $session, 'main', 100);
    makeFunnelOrder($funnel, $step, $session, 'main', 100);
    makeFunnelOrder($funnel, $step, $session, 'upsell', 50);
    makeFunnelOrder($funnel, $step, $session, 'bump', 20);

    $response = actingAs($owner)->getJson("/api/v1/funnels/{$funnel->uuid}/orders/stats");

    $response->assertOk()
        ->assertJsonPath('data.total_orders', 4)
        ->assertJsonPath('data.total_revenue', 270)
        ->assertJsonPath('data.type_breakdown.main.count', 2)
        ->assertJsonPath('data.type_breakdown.main.revenue', 200)
        ->assertJsonPath('data.type_breakdown.upsell.count', 1)
        ->assertJsonPath('data.type_breakdown.upsell.revenue', 50)
        ->assertJsonPath('data.type_breakdown.bump.count', 1)
        ->assertJsonPath('data.type_breakdown.downsell.count', 0);
});
