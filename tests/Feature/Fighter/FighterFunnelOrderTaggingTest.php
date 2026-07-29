<?php

declare(strict_types=1);

use App\Models\Funnel;
use App\Models\FunnelProduct;
use App\Models\FunnelSession;
use App\Models\FunnelStep;
use App\Models\Product;
use App\Models\ProductOrder;
use App\Models\User;
use App\Services\Fighter\FighterProvisioner;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;

uses(RefreshDatabase::class);

/**
 * Regression: a funnel owned by a Fighter must tag its checkout orders with the
 * fighter's sales-source segment, otherwise they never surface in the fighter's
 * own /fighter/orders feed (which is scoped by sales_source_id).
 */
function fighterCheckoutStep(User $owner): FunnelStep
{
    $funnel = Funnel::factory()->create([
        'user_id' => $owner->id,
        'disable_shipping' => true, // skip billing-address validation in the test
        'payment_settings' => [
            'enabled_methods' => ['cod'],
            'show_method_selector' => true,
            'default_method' => 'cod',
        ],
    ]);

    $step = FunnelStep::create([
        'funnel_id' => $funnel->id,
        'name' => 'Checkout',
        'slug' => 'checkout',
        'type' => 'checkout',
        'sort_order' => 0,
        'is_active' => true,
        'settings' => [],
    ]);

    $product = Product::factory()->create(['base_price' => 59]);
    FunnelProduct::factory()->create([
        'funnel_step_id' => $step->id,
        'product_id' => $product->id,
        'type' => 'main',
        'name' => 'Buku Panduan',
        'funnel_price' => 59,
    ]);

    return $step;
}

function placeCodFunnelOrder(FunnelStep $step): void
{
    $session = FunnelSession::factory()->create(['funnel_id' => $step->funnel_id]);

    Volt::test('funnel.checkout-form', ['funnel' => $step->funnel, 'step' => $step, 'session' => $session])
        ->set('customerData.name', 'Test Buyer')
        ->set('customerData.email', 'buyer@example.com')
        ->set('customerData.phone', '123456789')
        ->set('paymentMethod', 'cod')
        ->call('processOrder');
}

beforeEach(function () {
    app(SettingsService::class)->set('enable_cod_payments', true, 'boolean');
});

it('tags a fighter funnel COD order with the fighter segment and surfaces it in the fighter feed', function () {
    $fighter = User::factory()->create(['role' => 'fighter']);
    $step = fighterCheckoutStep($fighter);

    placeCodFunnelOrder($step);

    $order = ProductOrder::query()->where('source', 'funnel')->latest('id')->firstOrFail();

    $segmentId = app(FighterProvisioner::class)->ensureSalesSource($fighter->fresh())->id;

    expect($order->sales_source_id)->toBe($segmentId)
        ->and($order->hidden_from_admin)->toBeFalse()
        ->and($order->status)->toBe('processing');

    // It now appears in the fighter's own orders feed (scoped by sales_source_id).
    $this->actingAs($fighter->fresh())
        ->get('/fighter/orders')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('orders.data', 1)
            ->where('orders.data.0.order_number', $order->order_number)
            ->where('orders.data.0.source_label', 'Funnel')
        );
});

it('leaves a non-fighter funnel order untagged (stays out of any fighter feed)', function () {
    $owner = User::factory()->create(['role' => 'student']);
    $step = fighterCheckoutStep($owner);

    placeCodFunnelOrder($step);

    $order = ProductOrder::query()->where('source', 'funnel')->latest('id')->firstOrFail();

    expect($order->sales_source_id)->toBeNull();
});
