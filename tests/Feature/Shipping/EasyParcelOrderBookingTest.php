<?php

declare(strict_types=1);

use App\Models\ProductOrder;
use App\Models\ProductOrderAddress;
use App\Models\User;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Volt\Volt;

uses(RefreshDatabase::class);

function connectEasyParcel(): void
{
    $s = app(SettingsService::class);
    $s->set('easyparcel_client_id', 'CID', 'encrypted', 'shipping');
    $s->set('easyparcel_client_secret', 'SECRET', 'encrypted', 'shipping');
    $s->set('enable_easyparcel_shipping', true, 'boolean', 'shipping');
    $s->setEasyParcelTokens('ACCESS-TOKEN', 'REFRESH-TOKEN', now()->addHours(10)->toIso8601String());

    $s->set('shipping_sender_name', 'Sender Co', 'string', 'shipping');
    $s->set('shipping_sender_phone', '0312345678', 'string', 'shipping');
    $s->set('shipping_sender_address', '1 Jalan Test', 'string', 'shipping');
    $s->set('shipping_sender_city', 'Shah Alam', 'string', 'shipping');
    $s->set('shipping_sender_state', 'Selangor', 'string', 'shipping');
    $s->set('shipping_sender_postal_code', '40000', 'string', 'shipping');
}

function orderWithShippingAddress(): ProductOrder
{
    $order = ProductOrder::factory()->create(['status' => 'processing', 'weight_kg' => 1.2]);

    ProductOrderAddress::create([
        'order_id' => $order->id,
        'type' => 'shipping',
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'email' => 'jane@example.com',
        'address_line_1' => '2 Lorong Buyer',
        'city' => 'George Town',
        'state' => 'Penang',
        'postal_code' => '10000',
        'country' => 'Malaysia',
        'phone' => '0198765432',
    ]);

    return $order->fresh();
}

function fakeEasyParcelApi(): void
{
    Http::fake(function ($request) {
        $url = $request->url();

        if (str_contains($url, '/shipment/quotations')) {
            return Http::response(['status_code' => 200, 'data' => [['quotations' => [
                ['courier' => ['service_id' => 'EP-CS096', 'courier_name' => 'J&T', 'service_name' => 'Standard', 'delivery_duration' => '2 working days'], 'pricing' => ['total_amount' => '5.50', 'currency' => 'MYR']],
                ['courier' => ['service_id' => 'EP-CS09C', 'courier_name' => 'Aramex', 'service_name' => 'Pick Up', 'delivery_duration' => null], 'pricing' => ['total_amount' => '8.00', 'currency' => 'MYR']],
            ]]]]);
        }

        if (str_contains($url, '/shipment/submit_orders')) {
            return Http::response(['status_code' => 200, 'data' => [[
                'order_details' => ['order_number' => 'EI-2602-DEMO1'],
                'shipments' => [['status' => 'success', 'shipment_number' => 'ES-2602-DEMO1', 'awb_number' => '63112345678', 'awb_url' => 'https://ep.test/label.pdf', 'tracking_url' => 'https://ep.test/track']],
            ]]]);
        }

        return Http::response(['status_code' => 200, 'data' => []]);
    });
}

it('fetches EasyParcel rates and pre-selects the cheapest on the order page', function () {
    connectEasyParcel();
    fakeEasyParcelApi();

    $order = orderWithShippingAddress();

    Volt::actingAs(User::factory()->create(['role' => 'admin']))
        ->test('admin.orders.order-show', ['order' => $order])
        ->call('getEasyParcelRates')
        ->assertCount('easyParcelRates', 2)
        ->assertSet('easyParcelServiceId', 'EP-CS096');
});

it('books an EasyParcel shipment and stamps the order with the AWB, shipment number and label', function () {
    connectEasyParcel();
    fakeEasyParcelApi();

    $order = orderWithShippingAddress();

    Volt::actingAs(User::factory()->create(['role' => 'admin']))
        ->test('admin.orders.order-show', ['order' => $order])
        ->set('easyParcelServiceId', 'EP-CS096')
        ->call('bookEasyParcelShipment');

    $order->refresh();

    expect($order->tracking_id)->toBe('63112345678')
        ->and($order->shipping_provider)->toBe('easyparcel')
        ->and($order->status)->toBe('shipped')
        ->and(data_get($order->metadata, 'easyparcel_shipment_number'))->toBe('ES-2602-DEMO1')
        ->and(data_get($order->metadata, 'shipping_label_url'))->toBe('https://ep.test/label.pdf')
        ->and(data_get($order->metadata, 'easyparcel_awb_pending'))->toBeFalse();
});

it('refuses to book without a selected service', function () {
    connectEasyParcel();
    Http::fake();

    $order = orderWithShippingAddress();

    Volt::actingAs(User::factory()->create(['role' => 'admin']))
        ->test('admin.orders.order-show', ['order' => $order])
        ->call('bookEasyParcelShipment');

    expect($order->fresh()->shipping_provider)->toBeNull();
    Http::assertNothingSent();
});

it('resolves the address from the shipping_address JSON column for TikTok orders (no address rows)', function () {
    connectEasyParcel();
    fakeEasyParcelApi();

    $order = ProductOrder::factory()->create([
        'status' => 'processing',
        'weight_kg' => 1.0,
        'source' => 'tiktok_shop',
        'shipping_address' => [
            'name' => 'Siti Aminah',
            'phone' => '0198765432',
            'address_line1' => '12 Jalan Mawar',
            'city' => 'George Town',
            'state' => 'Penang',
            'postal_code' => '10000',
            'country' => 'MY',
        ],
    ]);

    expect($order->addresses()->count())->toBe(0);

    Volt::actingAs(User::factory()->create(['role' => 'admin']))
        ->test('admin.orders.order-show', ['order' => $order])
        ->call('getEasyParcelRates')
        ->assertCount('easyParcelRates', 2)
        ->assertSet('easyParcelServiceId', 'EP-CS096');
});

it('resolves POS-style JSON address keys (postcode / address_1)', function () {
    connectEasyParcel();
    fakeEasyParcelApi();

    $order = ProductOrder::factory()->create([
        'status' => 'processing',
        'source' => 'pos',
        'shipping_address' => [
            'first_name' => 'Ahmad',
            'last_name' => 'Bakar',
            'phone' => '0123334444',
            'address_1' => '5 Lorong Kedai',
            'city' => 'Ipoh',
            'state' => 'Perak',
            'postcode' => '30000',
        ],
    ]);

    Volt::actingAs(User::factory()->create(['role' => 'admin']))
        ->test('admin.orders.order-show', ['order' => $order])
        ->call('getEasyParcelRates')
        ->assertCount('easyParcelRates', 2);
});

it('still reports no address when the order has neither rows nor JSON', function () {
    connectEasyParcel();
    Http::fake();

    $order = ProductOrder::factory()->create(['status' => 'processing', 'shipping_address' => null]);

    Volt::actingAs(User::factory()->create(['role' => 'admin']))
        ->test('admin.orders.order-show', ['order' => $order])
        ->call('getEasyParcelRates')
        ->assertCount('easyParcelRates', 0);

    Http::assertNothingSent();
});
