<?php

declare(strict_types=1);

use App\DTOs\Shipping\ShipmentRequest;
use App\DTOs\Shipping\ShippingRateRequest;
use App\Services\SettingsService;
use App\Services\Shipping\EasyParcelShippingService;
use App\Services\Shipping\EasyParcelStateMapper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function configureEasyParcel(bool $sandbox = true, bool $enabled = true, bool $connected = true): void
{
    $s = app(SettingsService::class);
    $s->set('easyparcel_client_id', 'CID', 'encrypted', 'shipping');
    $s->set('easyparcel_client_secret', 'CSECRET', 'encrypted', 'shipping');
    $s->set('easyparcel_sandbox', $sandbox, 'boolean', 'shipping');
    $s->set('enable_easyparcel_shipping', $enabled, 'boolean', 'shipping');

    if ($connected) {
        // Simulate a completed OAuth handshake with a still-fresh access token.
        $s->setEasyParcelTokens('ACCESS-TOKEN', 'REFRESH-TOKEN', now()->addHours(10)->toIso8601String());
    }
}

function easyParcelService(): EasyParcelShippingService
{
    return app(EasyParcelShippingService::class);
}

function rateRequest(): ShippingRateRequest
{
    return new ShippingRateRequest(
        originPostalCode: '40000',
        originCity: 'Shah Alam',
        originState: 'Selangor',
        destinationPostalCode: '10000',
        destinationCity: 'George Town',
        destinationState: 'Penang',
        weightKg: 1.5,
        itemValue: 100.0,
    );
}

function shipmentRequest(string $serviceCode): ShipmentRequest
{
    return new ShipmentRequest(
        orderNumber: 'ORD-1',
        senderName: 'Sender Co',
        senderPhone: '0312345678',
        senderAddress: '1 Jalan Test',
        senderCity: 'Shah Alam',
        senderState: 'Selangor',
        senderPostalCode: '40000',
        receiverName: 'Jane Doe',
        receiverPhone: '0198765432',
        receiverAddress: '2 Lorong Buyer',
        receiverCity: 'George Town',
        receiverState: 'Penang',
        receiverPostalCode: '10000',
        weightKg: 1.5,
        itemDescription: 'Order ORD-1',
        itemValue: 100.0,
        itemQuantity: 1,
        serviceCode: $serviceCode,
    );
}

describe('configuration', function () {
    it('is enabled only when credentials are present, linked, and toggled on', function () {
        configureEasyParcel();

        expect(easyParcelService()->isConfigured())->toBeTrue()
            ->and(easyParcelService()->isEnabled())->toBeTrue()
            ->and(easyParcelService()->getProviderSlug())->toBe('easyparcel');
    });

    it('is not configured before the OAuth account is linked', function () {
        configureEasyParcel(connected: false);

        expect(easyParcelService()->isConfigured())->toBeFalse()
            ->and(easyParcelService()->isEnabled())->toBeFalse();
    });
});

describe('getRates', function () {
    it('parses quotations and sends a Bearer token to the versioned endpoint', function () {
        configureEasyParcel();

        Http::fake(['*' => Http::response([
            'status_code' => 200,
            'data' => [[
                'quotations' => [
                    ['courier' => ['service_id' => 'EP-CS096', 'courier_name' => 'Aramex', 'service_name' => 'Pick Up', 'delivery_duration' => '2 working days'], 'pricing' => ['total_amount' => '10.84', 'currency' => 'MYR']],
                    ['courier' => ['service_id' => 'EP-CS09C', 'courier_name' => 'J&T', 'service_name' => 'Standard', 'delivery_duration' => null], 'pricing' => ['total_amount' => '5.50', 'currency' => 'MYR']],
                ],
            ]],
        ])]);

        $rates = easyParcelService()->getRates(rateRequest());

        expect($rates)->toHaveCount(2)
            ->and($rates[0]->serviceCode)->toBe('EP-CS096')
            ->and($rates[0]->serviceName)->toBe('Aramex — Pick Up')
            ->and($rates[0]->cost)->toBe(10.84)
            ->and($rates[0]->estimatedDays)->toBe(2)
            ->and($rates[1]->cost)->toBe(5.5);

        Http::assertSent(fn ($req) => str_contains($req->url(), 'api.easyparcel.com/open_api/2026-03/shipment/quotations')
            && $req->hasHeader('Authorization', 'Bearer ACCESS-TOKEN'));
    });

    it('returns an empty array on an API error envelope', function () {
        configureEasyParcel();
        Http::fake(['*' => Http::response(['status_code' => 401, 'message' => 'Unauthorized'], 401)]);

        expect(easyParcelService()->getRates(rateRequest()))->toBe([]);
    });
});

describe('createShipment', function () {
    it('books via submit_orders and returns the AWB + label when ready', function () {
        configureEasyParcel();

        Http::fake(['*' => Http::response([
            'status_code' => 200,
            'data' => [[
                'order_details' => ['order_number' => 'EI-2602-4P2SK'],
                'shipments' => [[
                    'status' => 'success',
                    'shipment_number' => 'ES-2602-4VW9E',
                    'awb_number' => '238770015234',
                    'awb_url' => 'https://ep.test/label.pdf',
                    'tracking_url' => 'https://ep.test/track',
                ]],
            ]],
        ])]);

        $result = easyParcelService()->createShipment(shipmentRequest('EP-CS096'));

        expect($result->success)->toBeTrue()
            ->and($result->trackingNumber)->toBe('238770015234')
            ->and($result->shipmentNumber)->toBe('ES-2602-4VW9E')
            ->and($result->providerOrderId)->toBe('EI-2602-4P2SK')
            ->and($result->labelUrl)->toBe('https://ep.test/label.pdf')
            ->and($result->awbPending)->toBeFalse();
    });

    it('treats a booking with no AWB yet as success but awbPending', function () {
        configureEasyParcel();

        Http::fake(['*' => Http::response([
            'status_code' => 200,
            'data' => [[
                'order_details' => ['order_number' => 'EI-2602-4P2SK'],
                'shipments' => [['status' => 'success', 'shipment_number' => 'ES-2602-4VW9E', 'awb_number' => null, 'awb_url' => '']],
            ]],
        ])]);

        $result = easyParcelService()->createShipment(shipmentRequest('EP-CS096'));

        expect($result->success)->toBeTrue()
            ->and($result->awbPending)->toBeTrue()
            ->and($result->trackingNumber)->toBeNull()
            ->and($result->shipmentNumber)->toBe('ES-2602-4VW9E');
    });

    it('fails without a selected service and makes no API call', function () {
        configureEasyParcel();
        Http::fake();

        $result = easyParcelService()->createShipment(shipmentRequest(''));

        expect($result->success)->toBeFalse()
            ->and($result->message)->toContain('courier service must be selected');

        Http::assertNothingSent();
    });

    it('surfaces a per-shipment error', function () {
        configureEasyParcel();
        Http::fake(['*' => Http::response([
            'status_code' => 200,
            'data' => [['shipments' => [['status' => 'error', 'message' => 'Insufficient balance']]]],
        ])]);

        $result = easyParcelService()->createShipment(shipmentRequest('EP-CS096'));

        expect($result->success)->toBeFalse()
            ->and($result->message)->toBe('Insufficient balance');
    });
});

describe('tracking, cancel, wallet', function () {
    it('parses tracking status by AWB', function () {
        configureEasyParcel();
        Http::fake(['*' => Http::response([
            'status_code' => 200,
            'data' => ['results' => [[
                'awb_number' => '238770015234',
                'latest_tracking_status' => 'Schedule In Arrangement',
                'status_log' => [
                    ['event_date' => '2026-01-23 12:29:47', 'tracking_status' => 'Shipment data received', 'location' => 'KL Hub'],
                ],
            ]]],
        ])]);

        $result = easyParcelService()->getTracking('238770015234');

        expect($result->success)->toBeTrue()
            ->and($result->currentStatus)->toBe('Schedule In Arrangement')
            ->and($result->events)->toHaveCount(1)
            ->and($result->events[0]['location'])->toBe('KL Hub');
    });

    it('cancels by shipment number', function () {
        configureEasyParcel();
        Http::fake(['*' => Http::response([
            'status_code' => 200,
            'data' => [['status' => 'success', 'message' => 'Shipment Cancelled', 'shipment_number' => 'ES-2602-4VW9E']],
        ])]);

        $result = easyParcelService()->cancelShipment('ES-2602-4VW9E');

        expect($result->success)->toBeTrue()
            ->and($result->message)->toBe('Shipment Cancelled');

        Http::assertSent(fn ($req) => str_contains($req->url(), '/shipment/cancel'));
    });

    it('reads the wallet balance for the connection test', function () {
        configureEasyParcel();
        Http::fake(['*' => Http::response([
            'status_code' => 200,
            'data' => ['wallet' => [['balance' => 120.50, 'currency' => 'MYR']]],
        ])]);

        expect(easyParcelService()->testConnection())->toBeTrue()
            ->and(easyParcelService()->getCreditBalance())->toBe(120.5);
    });

    it('fails the connection test on a 401', function () {
        configureEasyParcel();
        Http::fake(['*' => Http::response(['status_code' => 401], 401)]);

        expect(easyParcelService()->testConnection())->toBeFalse();
    });
});

describe('EasyParcelStateMapper', function () {
    it('maps Malaysian states to ISO 3166-2 subdivision codes', function () {
        expect(EasyParcelStateMapper::getSubdivisionCode('Selangor'))->toBe('MY-10')
            ->and(EasyParcelStateMapper::getSubdivisionCode('Penang'))->toBe('MY-07')
            ->and(EasyParcelStateMapper::getSubdivisionCode('Pulau Pinang'))->toBe('MY-07')
            ->and(EasyParcelStateMapper::getSubdivisionCode('W.P. Kuala Lumpur'))->toBe('MY-14')
            ->and(EasyParcelStateMapper::getSubdivisionCode('MY-07'))->toBe('MY-07')
            ->and(EasyParcelStateMapper::getSubdivisionCode('Atlantis'))->toBe('');
    });
});
