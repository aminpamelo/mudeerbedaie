<?php

use App\Contracts\Shipping\ShippingProvider;
use App\DTOs\Shipping\CancelResult;
use App\DTOs\Shipping\ShipmentRequest;
use App\DTOs\Shipping\ShipmentResult;
use App\DTOs\Shipping\ShippingRateRequest;
use App\DTOs\Shipping\TrackingResult;
use App\Models\ProductOrder;
use App\Services\SettingsService;
use App\Services\Shipping\EasyParcelTrackingSync;
use App\Services\Shipping\ShippingManager;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// Start every test with no webhook secret so the endpoint is open by default;
// the auth-specific tests opt in by setting it. Keeps tests independent of
// whatever EASYPARCEL_WEBHOOK_SECRET happens to be in the environment.
beforeEach(fn () => config()->set('services.easyparcel.webhook_secret', null));

/**
 * Build a fake EasyParcel provider that returns a fixed tracking result, and
 * register it as the container's ShippingManager so the sync service / command
 * resolve it.
 */
function fakeEasyParcelProvider(TrackingResult $result): void
{
    $provider = new class($result) implements ShippingProvider
    {
        public function __construct(private TrackingResult $result) {}

        public function getProviderName(): string
        {
            return 'EasyParcel';
        }

        public function getProviderSlug(): string
        {
            return 'easyparcel';
        }

        public function isConfigured(): bool
        {
            return true;
        }

        public function isEnabled(): bool
        {
            return true;
        }

        public function isSandbox(): bool
        {
            return true;
        }

        public function getRates(ShippingRateRequest $request): array
        {
            return [];
        }

        public function createShipment(ShipmentRequest $request): ShipmentResult
        {
            return new ShipmentResult(success: false);
        }

        public function getTracking(string $trackingNumber): TrackingResult
        {
            return $this->result;
        }

        public function cancelShipment(string $trackingNumber): CancelResult
        {
            return new CancelResult(success: false);
        }

        public function testConnection(): bool
        {
            return true;
        }
    };

    $manager = new ShippingManager;
    $manager->registerProvider($provider);
    app()->instance(ShippingManager::class, $manager);
}

function shippedEasyParcelOrder(array $overrides = []): ProductOrder
{
    return ProductOrder::factory()->create(array_merge([
        'shipping_provider' => 'easyparcel',
        'tracking_id' => 'EP-AWB-123',
        'status' => 'shipped',
        'shipped_at' => now()->subDay(),
        'delivered_at' => null,
    ], $overrides));
}

it('marks a shipped order delivered when EasyParcel reports delivery', function () {
    $order = shippedEasyParcelOrder();

    $resolved = app(EasyParcelTrackingSync::class)->apply($order, 'Delivered');

    expect($resolved)->toBe('delivered');

    $order->refresh();
    expect($order->status)->toBe('delivered')
        ->and($order->delivered_at)->not->toBeNull()
        ->and($order->metadata['easyparcel_tracking_status'])->toBe('Delivered')
        ->and($order->metadata['easyparcel_tracking_synced_at'])->not->toBeNull()
        ->and($order->notes()->where('message', 'like', '%Auto-marked as delivered%')->exists())->toBeTrue();
});

it('auto-marks a COD order paid when it is delivered', function () {
    $order = shippedEasyParcelOrder(['payment_method' => 'cod', 'payment_status' => 'pending']);

    app(EasyParcelTrackingSync::class)->apply($order, 'Delivered');

    $order->refresh();
    expect($order->status)->toBe('delivered')
        ->and($order->payment_status)->toBe('paid')
        ->and($order->paid_time)->not->toBeNull()
        ->and($order->notes()->where('message', 'like', '%COD payment auto-marked%')->exists())->toBeTrue();
});

it('auto-marks an EasyParcel-COD (metadata-flagged) order paid on delivery', function () {
    $order = shippedEasyParcelOrder([
        'payment_method' => 'fpx',
        'payment_status' => 'pending',
        'metadata' => ['easyparcel_cod' => true],
    ]);

    app(EasyParcelTrackingSync::class)->apply($order, 'Delivered');

    expect($order->fresh()->payment_status)->toBe('paid');
});

it('leaves a prepaid order payment_status untouched on delivery', function () {
    $order = shippedEasyParcelOrder(['payment_method' => 'fpx', 'payment_status' => 'pending']);

    app(EasyParcelTrackingSync::class)->apply($order, 'Delivered');

    expect($order->fresh()->payment_status)->toBe('pending');
});

it('keeps a shipped order shipped for in-transit statuses', function (string $status) {
    $order = shippedEasyParcelOrder();

    $resolved = app(EasyParcelTrackingSync::class)->apply($order, $status);

    $order->refresh();
    expect($order->status)->toBe('shipped')
        ->and($order->delivered_at)->toBeNull()
        ->and($order->metadata['easyparcel_tracking_status'])->toBe($status)
        ->and($resolved)->not->toBe('delivered');
})->with([
    'in transit' => ['In Transit'],
    'out for delivery' => ['Out For Delivery'],
    'picked up' => ['Picked Up'],
    'failed delivery' => ['Delivery Failed'],
]);

it('never overrides a protected status with delivered', function () {
    $order = shippedEasyParcelOrder(['status' => 'cancelled']);

    app(EasyParcelTrackingSync::class)->apply($order, 'Delivered');

    $order->refresh();
    expect($order->status)->toBe('cancelled')
        ->and($order->delivered_at)->toBeNull();
});

it('does not duplicate work when the order is already delivered', function () {
    $order = shippedEasyParcelOrder(['status' => 'delivered', 'delivered_at' => now()->subHour()]);

    app(EasyParcelTrackingSync::class)->apply($order, 'Delivered');

    $order->refresh();
    expect($order->notes()->where('message', 'like', '%Auto-marked as delivered%')->count())->toBe(0);
});

it('maps EasyParcel statuses to order statuses', function (?string $raw, ?string $expected) {
    expect(app(EasyParcelTrackingSync::class)->mapToOrderStatus($raw))->toBe($expected);
})->with([
    ['Delivered', 'delivered'],
    ['Parcel Delivered', 'delivered'],
    ['Completed', 'delivered'],
    ['Out For Delivery', 'shipped'],
    ['In Transit', 'shipped'],
    ['Picked Up', 'shipped'],
    ['Collected', 'shipped'],
    ['Return To Sender', 'returned'],
    ['Cancelled', 'cancelled'],
    ['Delivery Failed', null],
    ['Exception', null],
    ['', null],
    [null, null],
]);

it('syncOrder pulls status from the provider and applies it', function () {
    fakeEasyParcelProvider(new TrackingResult(success: true, trackingNumber: 'EP-AWB-123', currentStatus: 'Delivered'));

    $order = shippedEasyParcelOrder();

    $resolved = app(EasyParcelTrackingSync::class)->syncOrder($order);

    expect($resolved)->toBe('delivered');
    expect($order->fresh()->status)->toBe('delivered');
});

it('syncOrder ignores non-easyparcel orders', function () {
    $order = shippedEasyParcelOrder(['shipping_provider' => 'jnt']);

    expect(app(EasyParcelTrackingSync::class)->syncOrder($order))->toBeNull();
});

it('the scheduled command reconciles in-flight shipments', function () {
    fakeEasyParcelProvider(new TrackingResult(success: true, trackingNumber: 'EP-AWB-123', currentStatus: 'Delivered'));

    $this->mock(SettingsService::class, function ($mock) {
        $mock->shouldReceive('isEasyParcelConnected')->andReturn(true);
    });

    $order = shippedEasyParcelOrder();
    $alreadyDelivered = shippedEasyParcelOrder(['tracking_id' => 'EP-AWB-999', 'status' => 'delivered', 'delivered_at' => now()]);

    $this->artisan('easyparcel:sync-tracking')
        ->assertSuccessful();

    expect($order->fresh()->status)->toBe('delivered')
        ->and($alreadyDelivered->fresh()->status)->toBe('delivered');
});

it('the command no-ops when EasyParcel is not connected', function () {
    $this->mock(SettingsService::class, function ($mock) {
        $mock->shouldReceive('isEasyParcelConnected')->andReturn(false);
    });

    $order = shippedEasyParcelOrder();

    $this->artisan('easyparcel:sync-tracking')->assertSuccessful();

    expect($order->fresh()->status)->toBe('shipped');
});

it('the webhook marks the matching order delivered', function () {
    $order = shippedEasyParcelOrder();

    $this->postJson('/webhooks/easyparcel', [
        'awb_no' => 'EP-AWB-123',
        'tracking_status' => 'Delivered',
    ])->assertOk()->assertJson(['received' => true]);

    expect($order->fresh()->status)->toBe('delivered');
});

it('the webhook accepts a nested data payload', function () {
    $order = shippedEasyParcelOrder();

    $this->postJson('/webhooks/easyparcel', [
        'data' => ['tracking_number' => 'EP-AWB-123', 'latest_tracking_status' => 'Delivered'],
    ])->assertOk();

    expect($order->fresh()->status)->toBe('delivered');
});

it('the webhook 200s and changes nothing for an unknown AWB', function () {
    $order = shippedEasyParcelOrder();

    $this->postJson('/webhooks/easyparcel', [
        'awb_no' => 'DOES-NOT-EXIST',
        'tracking_status' => 'Delivered',
    ])->assertOk();

    expect($order->fresh()->status)->toBe('shipped');
});

it('the webhook rejects a bad signature when a secret is configured', function () {
    config()->set('services.easyparcel.webhook_secret', 'super-secret');

    $order = shippedEasyParcelOrder();

    $this->postJson('/webhooks/easyparcel', [
        'awb_no' => 'EP-AWB-123',
        'tracking_status' => 'Delivered',
    ])->assertStatus(401);

    expect($order->fresh()->status)->toBe('shipped');
});

it('the webhook accepts a valid signature header', function () {
    config()->set('services.easyparcel.webhook_secret', 'super-secret');

    $order = shippedEasyParcelOrder();

    $this->withHeader('X-EasyParcel-Signature', 'super-secret')
        ->postJson('/webhooks/easyparcel', [
            'awb_no' => 'EP-AWB-123',
            'tracking_status' => 'Delivered',
        ])->assertOk();

    expect($order->fresh()->status)->toBe('delivered');
});

/*
|--------------------------------------------------------------------------
| Real EasyParcel payloads (verbatim shapes from their OpenAPI _webhooks.md)
|--------------------------------------------------------------------------
*/

it('maps the authoritative numeric status code', function (?int $code, ?string $expected) {
    expect(app(EasyParcelTrackingSync::class)->mapStatusCode($code))->toBe($expected);
})->with([
    'cancelled' => [0, 'cancelled'],
    'to be collected' => [2, 'shipped'],
    'collected' => [3, 'shipped'],
    'in transit' => [4, 'shipped'],
    'delivered' => [5, 'delivered'],
    'returned' => [6, 'returned'],
    'schedule' => [7, null],
    'on hold' => [8, null],
    'drop off' => [11, null],
    'unknown' => [99, null],
    'null' => [null, null],
]);

it('handles the real shipment.tracking.update payload via the numeric code (ignoring the misspelled label)', function () {
    $order = shippedEasyParcelOrder();

    // Verbatim shape from EasyParcel docs: code 5 = Delivered, label literally misspelt "Deliverd".
    $this->postJson('/webhooks/easyparcel', [
        'topic' => 'shipment.tracking.update',
        'shipment_number' => 'ES-2504-G7FDF',
        'uuid' => 'webhook-test-uuid-123',
        'timestamp' => '2017-10-28 11:40:00',
        'awb_number' => 'EP-AWB-123',
        'latest_shipment_status_code' => 5,
        'latest_tracking_status' => 'Deliverd To Suntech',
        'status_log' => [
            ['timestamp' => '2017-10-28 11:40:00', 'shipment_status_code' => 5, 'tracking_status' => 'Deliverd To Suntech'],
            ['timestamp' => '2017-06-28 12:00:00', 'shipment_status_code' => 3, 'tracking_status' => 'Parcel has been collected at Penang'],
        ],
    ])->assertOk();

    $order->refresh();
    expect($order->status)->toBe('delivered')
        ->and($order->delivered_at)->not->toBeNull()
        ->and($order->metadata['easyparcel_status_code'])->toBe(5);
});

it('handles the real shipment.status.update payload (shipment_status + shipment_status_code)', function () {
    $order = shippedEasyParcelOrder();

    $this->postJson('/webhooks/easyparcel', [
        'topic' => 'shipment.status.update',
        'awb_number' => 'EP-AWB-123',
        'event_date' => '2017-10-28 11:40:00',
        'shipment_number' => 'ES-2504-G7FDF',
        'shipment_status' => 'Delivered',
        'shipment_status_code' => 5,
    ])->assertOk();

    expect($order->fresh()->status)->toBe('delivered');
});

it('keeps the order shipped for the in-transit code (4)', function () {
    $order = shippedEasyParcelOrder();

    $this->postJson('/webhooks/easyparcel', [
        'topic' => 'shipment.status.update',
        'awb_number' => 'EP-AWB-123',
        'shipment_number' => 'ES-2504-G7FDF',
        'shipment_status' => 'Delivery In Transit',
        'shipment_status_code' => 4,
    ])->assertOk();

    expect($order->fresh()->status)->toBe('shipped');
});

it('auto-cancels the order and refunds payment on cancelled code (0)', function () {
    $order = shippedEasyParcelOrder(['payment_status' => 'paid']);

    $this->postJson('/webhooks/easyparcel', [
        'topic' => 'shipment.status.update',
        'awb_number' => 'EP-AWB-123',
        'shipment_number' => 'ES-2504-G7FDF',
        'shipment_status' => 'Cancelled',
        'shipment_status_code' => 0,
    ])->assertOk();

    $order->refresh();
    expect($order->status)->toBe('cancelled')
        ->and($order->cancelled_at)->not->toBeNull()
        ->and($order->payment_status)->toBe('refunded');
});

it('auto-returns the order on returned code (6)', function () {
    $order = shippedEasyParcelOrder(['payment_status' => 'paid']);

    $this->postJson('/webhooks/easyparcel', [
        'topic' => 'shipment.tracking.update',
        'awb_number' => 'EP-AWB-123',
        'shipment_number' => 'ES-2504-G7FDF',
        'latest_tracking_status' => 'Returned to sender',
        'latest_shipment_status_code' => 6,
    ])->assertOk();

    $order->refresh();
    expect($order->status)->toBe('returned')
        ->and($order->payment_status)->toBe('refunded')
        ->and($order->notes()->where('message', 'like', '%returned%')->exists())->toBeTrue();
});

it('does not re-transition an order already in a protected status', function () {
    $order = shippedEasyParcelOrder(['status' => 'delivered', 'delivered_at' => now()->subDay(), 'payment_status' => 'paid']);

    app(EasyParcelTrackingSync::class)->apply($order, 'Cancelled', 0);

    $order->refresh();
    expect($order->status)->toBe('delivered')
        ->and($order->payment_status)->toBe('paid');
});

it('backfills the AWB from shipment.awb.update for an order booked with a pending AWB', function () {
    // The production-critical case: booked with awbPending — tracking_id is null,
    // only the EasyParcel shipment number is known, so it can only be found by that.
    $order = ProductOrder::factory()->create([
        'shipping_provider' => 'easyparcel',
        'tracking_id' => null,
        'status' => 'shipped',
        'shipped_at' => now()->subDay(),
        'delivered_at' => null,
        'metadata' => [
            'easyparcel_shipment_number' => 'ES-2504-G7FDF',
            'easyparcel_awb_pending' => true,
        ],
    ]);

    $this->postJson('/webhooks/easyparcel', [
        'topic' => 'shipment.awb.update',
        'shipment_number' => 'ES-2504-G7FDF',
        'uuid' => 'webhook-test-uuid-123',
        'timestamp' => '2017-10-28 11:40:00',
        'awb_number' => '23872512999',
        'awb_url' => 'http://demo.connect.easyparcel.my/?ac=AWBLabel&id=abc',
        'tracking_url' => 'https://easyparcel.com/my/en/track/details/?awb=23872512999',
    ])->assertOk();

    $order->refresh();
    expect($order->tracking_id)->toBe('23872512999')
        ->and($order->metadata['easyparcel_awb_pending'])->toBeFalse()
        ->and($order->metadata['shipping_label_url'])->toBe('http://demo.connect.easyparcel.my/?ac=AWBLabel&id=abc')
        ->and($order->metadata['shipping_tracking_url'])->toBe('https://easyparcel.com/my/en/track/details/?awb=23872512999')
        ->and($order->notes()->where('message', 'like', '%AWB received%')->exists())->toBeTrue();
});

it('applyAwb backfills tracking_id and is idempotent', function () {
    $order = ProductOrder::factory()->create([
        'shipping_provider' => 'easyparcel',
        'tracking_id' => null,
        'metadata' => ['easyparcel_shipment_number' => 'ES-1', 'easyparcel_awb_pending' => true],
    ]);

    $sync = app(EasyParcelTrackingSync::class);

    expect($sync->applyAwb($order, 'AWB-1', 'http://label', 'http://track'))->toBeTrue();
    $order->refresh();
    expect($order->tracking_id)->toBe('AWB-1');

    // Re-applying the same AWB changes nothing.
    expect($sync->applyAwb($order, 'AWB-1', 'http://label', 'http://track'))->toBeFalse();
});

it('the cron sync uses the numeric code from getTracking even when the label is misspelt', function () {
    fakeEasyParcelProvider(new TrackingResult(
        success: true,
        trackingNumber: 'EP-AWB-123',
        currentStatus: 'Deliverd To Suntech',
        currentStatusCode: 5,
    ));

    $order = shippedEasyParcelOrder();

    expect(app(EasyParcelTrackingSync::class)->syncOrder($order))->toBe('delivered');
    expect($order->fresh()->status)->toBe('delivered');
});
