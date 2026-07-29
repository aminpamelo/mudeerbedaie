<?php

declare(strict_types=1);

use App\Models\Funnel;
use App\Models\FunnelOrder;
use App\Models\ProductOrder;
use App\Models\User;
use App\Services\Fighter\FighterProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Links an untagged funnel order to a funnel via the FunnelOrder pivot,
 * mirroring how the live checkout records the relationship.
 */
function untaggedFunnelOrder(Funnel $funnel, array $overrides = []): ProductOrder
{
    $order = ProductOrder::factory()->create(array_merge([
        'source' => 'funnel',
        'source_reference' => $funnel->slug,
        'sales_source_id' => null,
        'hidden_from_admin' => true,
        'metadata' => ['funnel_id' => $funnel->id],
    ], $overrides));

    FunnelOrder::create([
        'funnel_id' => $funnel->id,
        'product_order_id' => $order->id,
        'order_type' => 'main',
        'funnel_revenue' => $order->total_amount,
    ]);

    return $order;
}

it('backfills the fighter segment onto an existing untagged fighter-funnel order', function () {
    $fighter = User::factory()->create(['role' => 'fighter']);
    $funnel = Funnel::factory()->create(['user_id' => $fighter->id]);
    $order = untaggedFunnelOrder($funnel);

    $this->artisan('fighter:backfill-funnel-order-tags')->assertSuccessful();

    $segmentId = app(FighterProvisioner::class)->ensureSalesSource($fighter->fresh())->id;

    expect($order->fresh())
        ->sales_source_id->toBe($segmentId)
        ->hidden_from_admin->toBeFalse();

    // And it now surfaces in the fighter's own feed.
    $this->actingAs($fighter->fresh())
        ->get('/fighter/orders')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('orders.data', 1)
            ->where('orders.data.0.order_number', $order->order_number)
        );
});

it('leaves a non-fighter funnel order untouched', function () {
    $owner = User::factory()->create(['role' => 'student']);
    $funnel = Funnel::factory()->create(['user_id' => $owner->id]);
    $order = untaggedFunnelOrder($funnel);

    $this->artisan('fighter:backfill-funnel-order-tags')->assertSuccessful();

    expect($order->fresh()->sales_source_id)->toBeNull();
});

it('does not touch an already-tagged funnel order (idempotent)', function () {
    $fighter = User::factory()->create(['role' => 'fighter']);
    $segment = app(FighterProvisioner::class)->ensureSalesSource($fighter);
    $funnel = Funnel::factory()->create(['user_id' => $fighter->id]);

    $order = ProductOrder::factory()->create([
        'source' => 'funnel',
        'sales_source_id' => $segment->id,
    ]);

    $this->artisan('fighter:backfill-funnel-order-tags')
        ->expectsOutputToContain('No untagged funnel orders found. Nothing to backfill.')
        ->assertSuccessful();

    expect($order->fresh()->sales_source_id)->toBe($segment->id);
});

it('dry-run reports without writing', function () {
    $fighter = User::factory()->create(['role' => 'fighter']);
    $funnel = Funnel::factory()->create(['user_id' => $fighter->id]);
    $order = untaggedFunnelOrder($funnel);

    $this->artisan('fighter:backfill-funnel-order-tags', ['--dry-run' => true])->assertSuccessful();

    expect($order->fresh()->sales_source_id)->toBeNull();
});
