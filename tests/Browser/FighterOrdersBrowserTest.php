<?php

declare(strict_types=1);

use App\Models\ProductOrder;
use App\Models\User;
use App\Services\Fighter\FighterProvisioner;

/**
 * Browser test: a fighter's shipped order links out to tracking.my for parcel
 * tracking (the switch away from EasyParcel's tracker).
 */
it('links a shipped order to tracking.my on the fighter orders page', function () {
    $fighter = User::factory()->create(['role' => 'fighter']);
    $segment = app(FighterProvisioner::class)->ensureSalesSource($fighter);

    ProductOrder::factory()->create([
        'source' => 'funnel',
        'order_number' => 'PO-TRACKMY',
        'sales_source_id' => $segment->id,
        'status' => 'shipped',
        'payment_status' => 'paid',
        'shipping_provider' => 'easyparcel',
        'tracking_id' => '632118771195',
    ]);

    $this->actingAs($fighter);

    visit('/fighter/orders')
        ->assertNoJavascriptErrors()
        ->assertSee('632118771195')
        ->assertPresent('a[href*="tracking.my/instant/632118771195"]');
});
