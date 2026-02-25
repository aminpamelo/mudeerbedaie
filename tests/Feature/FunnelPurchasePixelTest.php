<?php

use App\Models\Funnel;
use App\Models\FunnelOrder;
use App\Models\FunnelSession;
use App\Models\FunnelStep;
use App\Models\ProductOrder;
use App\Models\ProductOrderItem;
use App\Services\Funnel\FacebookPixelService;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function createFunnelWithPixelEnabled(): array
{
    $funnel = Funnel::factory()->published()->create([
        'settings' => [
            'pixel_settings' => [
                'facebook' => [
                    'enabled' => true,
                    'pixel_id' => '123456789',
                    'access_token' => '',
                    'events' => [
                        'page_view' => true,
                        'view_content' => true,
                        'add_to_cart' => true,
                        'initiate_checkout' => true,
                        'purchase' => true,
                        'lead' => true,
                    ],
                ],
            ],
        ],
    ]);

    $thankYouStep = FunnelStep::factory()->create([
        'funnel_id' => $funnel->id,
        'name' => 'Thank You',
        'slug' => 'thank-you-page',
        'type' => 'thankyou',
        'sort_order' => 2,
        'is_active' => true,
    ]);

    $session = FunnelSession::factory()->create([
        'funnel_id' => $funnel->id,
    ]);

    return [$funnel, $thankYouStep, $session];
}

test('thank you page fires purchase pixel when order has fb_purchase_event_id', function () {
    [$funnel, $thankYouStep, $session] = createFunnelWithPixelEnabled();

    $order = ProductOrder::factory()->create([
        'status' => 'confirmed',
        'metadata' => [
            'fb_purchase_event_id' => 'test-event-123',
        ],
    ]);

    ProductOrderItem::factory()->create([
        'order_id' => $order->id,
    ]);

    FunnelOrder::factory()->create([
        'funnel_id' => $funnel->id,
        'session_id' => $session->id,
        'product_order_id' => $order->id,
    ]);

    $response = $this->withSession(["funnel_session_{$funnel->id}" => $session->uuid])
        ->get("/f/{$funnel->slug}/{$thankYouStep->slug}?order={$order->order_number}");

    $response->assertSuccessful();
    $response->assertSee("fbq('track', 'Purchase'", false);
    $response->assertSee('test-event-123');
});

test('thank you page fires purchase pixel even without fb_purchase_event_id in metadata', function () {
    [$funnel, $thankYouStep, $session] = createFunnelWithPixelEnabled();

    // Order WITHOUT fb_purchase_event_id (the bug scenario - e.g. COD payment)
    $order = ProductOrder::factory()->create([
        'status' => 'confirmed',
        'metadata' => [
            'funnel_id' => $funnel->id,
        ],
    ]);

    ProductOrderItem::factory()->create([
        'order_id' => $order->id,
    ]);

    FunnelOrder::factory()->create([
        'funnel_id' => $funnel->id,
        'session_id' => $session->id,
        'product_order_id' => $order->id,
    ]);

    // Mock the pixel service to avoid actual API calls but still generate event IDs
    $this->partialMock(FacebookPixelService::class, function ($mock) {
        $mock->shouldReceive('trackPurchase')
            ->once()
            ->andReturn('generated-event-id');
        $mock->shouldReceive('isEnabled')->andReturn(true);
        $mock->shouldReceive('isEventEnabled')->andReturn(true);
        $mock->shouldReceive('getPixelSettings')->andReturn([
            'enabled' => true,
            'pixel_id' => '123456789',
            'access_token' => '',
            'test_event_code' => '',
            'events' => [
                'page_view' => true,
                'purchase' => true,
            ],
        ]);
        $mock->shouldReceive('getPixelInitCode')->andReturn("fbq('init', '123456789');");
        $mock->shouldReceive('trackPageView')->andReturn('page-view-event-id');
    });

    $response = $this->withSession(["funnel_session_{$funnel->id}" => $session->uuid])
        ->get("/f/{$funnel->slug}/{$thankYouStep->slug}?order={$order->order_number}");

    $response->assertSuccessful();
    $response->assertSee("fbq('track', 'Purchase'", false);
});

test('thank you page finds order from URL query parameter as fallback', function () {
    [$funnel, $thankYouStep, $session] = createFunnelWithPixelEnabled();

    // Create order with fb_purchase_event_id but NOT linked to session via FunnelOrder
    $order = ProductOrder::factory()->create([
        'status' => 'confirmed',
        'metadata' => [
            'fb_purchase_event_id' => 'fallback-event-123',
        ],
    ]);

    ProductOrderItem::factory()->create([
        'order_id' => $order->id,
    ]);

    // No FunnelOrder linking session to order - simulates edge case

    $response = $this->withSession(["funnel_session_{$funnel->id}" => $session->uuid])
        ->get("/f/{$funnel->slug}/{$thankYouStep->slug}?order={$order->order_number}");

    $response->assertSuccessful();
    $response->assertSee("fbq('track', 'Purchase'", false);
    $response->assertSee('fallback-event-123');
});
