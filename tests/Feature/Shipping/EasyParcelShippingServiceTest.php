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

function configureEasyParcel(bool $sandbox = true, bool $enabled = true): void
{
    $settings = app(SettingsService::class);
    $settings->set('easyparcel_api_key', 'TEST-KEY', 'encrypted', 'shipping');
    $settings->set('easyparcel_sandbox', $sandbox, 'boolean', 'shipping');
    $settings->set('enable_easyparcel_shipping', $enabled, 'boolean', 'shipping');
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

describe('configuration', function () {
    it('reports configured + enabled from settings', function () {
        configureEasyParcel();

        expect(easyParcelService()->isConfigured())->toBeTrue()
            ->and(easyParcelService()->isEnabled())->toBeTrue()
            ->and(easyParcelService()->isSandbox())->toBeTrue()
            ->and(easyParcelService()->getProviderSlug())->toBe('easyparcel');
    });

    it('is not enabled without an api key', function () {
        app(SettingsService::class)->set('enable_easyparcel_shipping', true, 'boolean', 'shipping');

        expect(easyParcelService()->isConfigured())->toBeFalse()
            ->and(easyParcelService()->isEnabled())->toBeFalse();
    });
});

describe('getRates', function () {
    it('parses each courier rate and hits the sandbox endpoint', function () {
        configureEasyParcel(sandbox: true);

        Http::fake(['*' => Http::response([
            'api_status' => 'Success',
            'error_code' => '0',
            'result' => [[
                'status' => 'Success',
                'rates' => [
                    ['service_id' => 'EP-CS0IM', 'courier_name' => 'J&T Express', 'service_name' => 'Standard', 'price' => '5.50', 'delivery' => '2 working day(s)'],
                    ['service_id' => 'EP-CS0XX', 'courier_name' => 'Poslaju', 'service_name' => 'Next Day', 'price' => '8.00', 'delivery' => '1 working day'],
                ],
            ]],
        ])]);

        $rates = easyParcelService()->getRates(rateRequest());

        expect($rates)->toHaveCount(2)
            ->and($rates[0]->serviceCode)->toBe('EP-CS0IM')
            ->and($rates[0]->serviceName)->toBe('J&T Express — Standard')
            ->and($rates[0]->cost)->toBe(5.5)
            ->and($rates[0]->estimatedDays)->toBe(2)
            ->and($rates[1]->cost)->toBe(8.0);

        Http::assertSent(fn ($req) => str_contains($req->url(), 'demo.connect.easyparcel.my')
            && str_contains($req->url(), 'ac=EPRateCheckingBulk'));
    });

    it('returns an empty array on an API error', function () {
        configureEasyParcel();

        Http::fake(['*' => Http::response(['api_status' => 'Error', 'error_code' => '1', 'error_remark' => 'Invalid API'])]);

        expect(easyParcelService()->getRates(rateRequest()))->toBe([]);
    });

    it('uses the production endpoint when sandbox is off', function () {
        configureEasyParcel(sandbox: false);

        Http::fake(['*' => Http::response(['api_status' => 'Success', 'error_code' => '0', 'result' => [['rates' => []]]])]);

        easyParcelService()->getRates(rateRequest());

        Http::assertSent(fn ($req) => str_contains($req->url(), 'connect.easyparcel.my')
            && ! str_contains($req->url(), 'demo.'));
    });
});

describe('createShipment', function () {
    it('submits then pays and returns the AWB + label', function () {
        configureEasyParcel();

        Http::fake(function ($request) {
            $url = $request->url();

            if (str_contains($url, 'EPSubmitOrderBulk')) {
                return Http::response([
                    'api_status' => 'Success',
                    'error_code' => '0',
                    'result' => [['status' => 'Success', 'order_number' => 'EI-ABCDE']],
                ]);
            }

            if (str_contains($url, 'EPPayOrderBulk')) {
                return Http::response([
                    'api_status' => 'Success',
                    'error_code' => '0',
                    'result' => [[
                        'orderno' => 'EI-ABCDE',
                        'messagenow' => 'Fully Paid',
                        'parcel' => [[
                            'awb' => '238770015234',
                            'awb_id_link' => 'https://easyparcel.test/label.pdf',
                            'tracking_url' => 'https://easyparcel.test/track',
                        ]],
                    ]],
                ]);
            }

            return Http::response([], 200);
        });

        $result = easyParcelService()->createShipment(shipmentRequest('EP-CS0IM'));

        expect($result->success)->toBeTrue()
            ->and($result->trackingNumber)->toBe('238770015234')
            ->and($result->labelUrl)->toBe('https://easyparcel.test/label.pdf')
            ->and($result->trackingUrl)->toBe('https://easyparcel.test/track')
            ->and($result->providerOrderId)->toBe('EI-ABCDE');

        Http::assertSentCount(2);
    });

    it('fails without a selected service and makes no API call', function () {
        configureEasyParcel();
        Http::fake();

        $result = easyParcelService()->createShipment(shipmentRequest(''));

        expect($result->success)->toBeFalse()
            ->and($result->message)->toContain('courier service must be selected');

        Http::assertNothingSent();
    });

    it('surfaces a submit error without attempting payment', function () {
        configureEasyParcel();

        Http::fake(['*' => Http::response([
            'api_status' => 'Success',
            'error_code' => '0',
            'result' => [['status' => 'Fail', 'remarks' => 'Invalid postcode']],
        ])]);

        $result = easyParcelService()->createShipment(shipmentRequest('EP-CS0IM'));

        expect($result->success)->toBeFalse()
            ->and($result->message)->toBe('Invalid postcode');

        Http::assertSentCount(1);
    });
});

describe('testConnection', function () {
    it('is true when credit balance check succeeds', function () {
        configureEasyParcel();
        Http::fake(['*' => Http::response(['api_status' => 'Success', 'error_code' => '0', 'result' => ['wallet' => '120.50']])]);

        expect(easyParcelService()->testConnection())->toBeTrue();
    });

    it('is false when the API rejects the key', function () {
        configureEasyParcel();
        Http::fake(['*' => Http::response(['api_status' => 'Error', 'error_code' => '1'])]);

        expect(easyParcelService()->testConnection())->toBeFalse();
    });
});

describe('cancelShipment', function () {
    it('directs the admin to the dashboard rather than failing silently', function () {
        configureEasyParcel();

        $result = easyParcelService()->cancelShipment('238770015234');

        expect($result->success)->toBeFalse()
            ->and($result->message)->toContain('dashboard');
    });
});

describe('EasyParcelStateMapper', function () {
    it('maps state names to EasyParcel codes', function () {
        expect(EasyParcelStateMapper::getStateCode('Selangor'))->toBe('sgr')
            ->and(EasyParcelStateMapper::getStateCode('Pulau Pinang'))->toBe('png')
            ->and(EasyParcelStateMapper::getStateCode('Negeri Sembilan'))->toBe('nsn')
            ->and(EasyParcelStateMapper::getStateCode('W.P. Kuala Lumpur'))->toBe('kul')
            ->and(EasyParcelStateMapper::getStateCode('sgr'))->toBe('sgr')
            ->and(EasyParcelStateMapper::getStateCode(''))->toBe('');
    });
});

function shipmentRequest(string $serviceCode): ShipmentRequest
{
    return new ShipmentRequest(
        orderNumber: 'ORD-1',
        senderName: 'Sender Co',
        senderPhone: '0123456789',
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
