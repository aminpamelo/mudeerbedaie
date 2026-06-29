<?php

namespace App\Services\Shipping;

use App\Contracts\Shipping\ShippingProvider;
use App\DTOs\Shipping\CancelResult;
use App\DTOs\Shipping\ShipmentRequest;
use App\DTOs\Shipping\ShipmentResult;
use App\DTOs\Shipping\ShippingRate;
use App\DTOs\Shipping\ShippingRateRequest;
use App\DTOs\Shipping\TrackingResult;
use App\Services\SettingsService;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * EasyParcel Open API (v2026-03) shipping aggregator for Malaysia.
 *
 * Authenticates with OAuth 2.0 bearer tokens minted by {@see EasyParcelOAuthService}
 * (the account is linked once through the hosted login). All calls are JSON.
 * Booking via `submit_orders` charges the linked wallet immediately — there is no
 * separate pay step — but the AWB number and printable label are generated
 * asynchronously, so a successful booking may come back with `awbPending` set and
 * the label fetched later via {@see getShipmentDetails()}.
 *
 * @see https://easyparcel.github.io/OpenAPI/
 */
class EasyParcelShippingService implements ShippingProvider
{
    private const API_BASE = 'https://api.easyparcel.com';

    private const VERSION = '/open_api/2026-03';

    public function __construct(
        private SettingsService $settingsService,
        private EasyParcelOAuthService $oauth,
    ) {}

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
        return $this->settingsService->isEasyParcelConfigured()
            && $this->settingsService->isEasyParcelConnected();
    }

    public function isEnabled(): bool
    {
        return $this->settingsService->isEasyParcelEnabled();
    }

    public function isSandbox(): bool
    {
        return (bool) $this->settingsService->get('easyparcel_sandbox', true);
    }

    /**
     * Quote every courier EasyParcel offers for the parcel. The `serviceCode`
     * carried back is the EasyParcel `service_id` needed to book that service.
     *
     * @return ShippingRate[]
     */
    public function getRates(ShippingRateRequest $request): array
    {
        $payload = ['shipment' => [[
            'sender' => [
                'postcode' => $request->originPostalCode,
                'subdivision_code' => EasyParcelStateMapper::getSubdivisionCode($request->originState),
                'country' => 'MY',
            ],
            'receiver' => [
                'postcode' => $request->destinationPostalCode,
                'subdivision_code' => EasyParcelStateMapper::getSubdivisionCode($request->destinationState),
                'country' => 'MY',
            ],
            'weight' => max($request->weightKg, 0.1),
            'width' => $request->widthCm ?? 5,
            'length' => $request->lengthCm ?? 5,
            'height' => $request->heightCm ?? 5,
            'parcel_value' => $request->itemValue ?? 0,
        ]]];

        try {
            $response = $this->apiRequest('POST', self::VERSION.'/shipment/quotations', $payload);

            if (! $this->ok($response)) {
                Log::warning('EasyParcel quotations error', ['request' => $payload, 'response' => $response]);

                return [];
            }

            $rates = [];

            foreach (($response['data'][0]['quotations'] ?? []) as $quote) {
                $courier = $quote['courier'] ?? [];
                $pricing = $quote['pricing'] ?? [];

                $rates[] = new ShippingRate(
                    providerSlug: 'easyparcel',
                    providerName: 'EasyParcel',
                    serviceName: trim(($courier['courier_name'] ?? 'Courier').' — '.($courier['service_name'] ?? '')),
                    serviceCode: (string) ($courier['service_id'] ?? ''),
                    cost: (float) ($pricing['total_amount'] ?? 0),
                    currency: $pricing['currency'] ?? 'MYR',
                    estimatedDays: $this->parseDeliveryDays($courier['delivery_duration'] ?? null),
                    metadata: $quote,
                );
            }

            return $rates;
        } catch (\Exception $e) {
            Log::error('EasyParcel rate checking failed', ['error' => $e->getMessage()]);

            return [];
        }
    }

    public function createShipment(ShipmentRequest $request): ShipmentResult
    {
        if (empty($request->serviceCode)) {
            return new ShipmentResult(
                success: false,
                message: 'A courier service must be selected before booking an EasyParcel shipment.',
            );
        }

        $payload = ['shipment' => [[
            'reference' => $request->orderNumber,
            'service_id' => $request->serviceCode,
            'collection_date' => now()->addDay()->format('Y-m-d'),
            'weight' => max($request->weightKg, 0.1),
            'width' => 5,
            'length' => 5,
            'height' => 5,
            'item' => [[
                'content' => $request->itemDescription ?: 'General goods',
                'weight' => max($request->weightKg, 0.1),
                'width' => 5,
                'length' => 5,
                'height' => 5,
                'currency_code' => 'MYR',
                'value' => $request->itemValue ?? 0,
                'quantity' => max($request->itemQuantity, 1),
            ]],
            'sender' => $this->party($request->senderName, $request->senderPhone, $request->senderAddress, $request->senderCity, $request->senderState, $request->senderPostalCode),
            'receiver' => $this->party($request->receiverName, $request->receiverPhone, $request->receiverAddress, $request->receiverCity, $request->receiverState, $request->receiverPostalCode),
            'feature' => [
                'sms_tracking' => false,
                'email_tracking' => true,
                'whatsapp_tracking' => false,
            ],
        ]]];

        try {
            // NOTE: the published docs show `submit_orders` without the version
            // prefix, unlike every other shipment endpoint. We use the versioned
            // path for consistency; flip to '/shipment/submit_orders' if rejected.
            $response = $this->apiRequest('POST', self::VERSION.'/shipment/submit_orders', $payload);

            $first = $response['data'][0] ?? [];
            $shipment = $first['shipments'][0] ?? [];

            if (! $this->ok($response) || ($shipment['status'] ?? '') !== 'success') {
                return new ShipmentResult(
                    success: false,
                    message: $shipment['message'] ?? $response['message'] ?? 'Failed to submit EasyParcel order.',
                    rawResponse: $response ?? [],
                );
            }

            $awb = ($shipment['awb_number'] ?? null) ?: null;
            $labelUrl = ($shipment['awb_url'] ?? '') ?: (data_get($shipment, 'awb_urls_by_format.A4') ?: null);

            return new ShipmentResult(
                success: true,
                trackingNumber: $awb,
                waybillNumber: $awb,
                message: $awb ? 'Shipment booked and paid.' : 'Shipment booked. The AWB and label are being generated.',
                rawResponse: $response,
                labelUrl: $labelUrl,
                trackingUrl: ($shipment['tracking_url'] ?? null) ?: null,
                providerOrderId: data_get($first, 'order_details.order_number'),
                shipmentNumber: $shipment['shipment_number'] ?? null,
                awbPending: empty($awb),
            );
        } catch (\Exception $e) {
            Log::error('EasyParcel create shipment failed', ['error' => $e->getMessage(), 'order' => $request->orderNumber]);

            return new ShipmentResult(success: false, message: $e->getMessage());
        }
    }

    public function getTracking(string $trackingNumber): TrackingResult
    {
        try {
            $response = $this->apiRequest('POST', self::VERSION.'/shipment/tracking_status', [
                'awb_numbers' => [$trackingNumber],
            ]);

            if (! $this->ok($response)) {
                return new TrackingResult(
                    success: false,
                    trackingNumber: $trackingNumber,
                    message: $response['message'] ?? 'Failed to retrieve tracking info.',
                    rawResponse: $response ?? [],
                );
            }

            $result = $response['data']['results'][0] ?? [];
            $events = [];

            foreach ($result['status_log'] ?? [] as $log) {
                $events[] = [
                    'status' => $log['tracking_status'] ?? '',
                    'datetime' => $log['event_date'] ?? '',
                    'location' => $log['location'] ?? '',
                    'description' => $log['tracking_status'] ?? '',
                ];
            }

            return new TrackingResult(
                success: true,
                trackingNumber: $trackingNumber,
                currentStatus: $result['latest_tracking_status'] ?? null,
                events: $events,
                message: 'Tracking data retrieved.',
                rawResponse: $response,
                currentStatusCode: isset($result['latest_shipment_status_code'])
                    ? (int) $result['latest_shipment_status_code']
                    : null,
            );
        } catch (\Exception $e) {
            Log::error('EasyParcel tracking failed', ['error' => $e->getMessage(), 'tracking' => $trackingNumber]);

            return new TrackingResult(success: false, trackingNumber: $trackingNumber, message: $e->getMessage());
        }
    }

    /**
     * Cancel a shipment. EasyParcel cancels by shipment number (ES-YYMM-XXXXX),
     * within 7 days of the collection date — pass the shipment number, not the AWB.
     */
    public function cancelShipment(string $shipmentNumber): CancelResult
    {
        try {
            $response = $this->apiRequest('POST', self::VERSION.'/shipment/cancel', [
                'cancel_list' => [[
                    'shipment_number' => $shipmentNumber,
                    'remark' => 'Cancelled by admin',
                ]],
            ]);

            $result = $response['data'][0] ?? [];
            $success = $this->ok($response) && ($result['status'] ?? '') === 'success';

            return new CancelResult(
                success: $success,
                message: $result['message'] ?? ($success ? 'Shipment cancelled.' : 'Failed to cancel shipment.'),
                rawResponse: $response ?? [],
            );
        } catch (\Exception $e) {
            Log::error('EasyParcel cancel failed', ['error' => $e->getMessage(), 'shipment' => $shipmentNumber]);

            return new CancelResult(success: false, message: $e->getMessage());
        }
    }

    public function testConnection(): bool
    {
        try {
            return $this->ok($this->apiRequest('GET', self::VERSION.'/wallet'));
        } catch (\Exception $e) {
            Log::warning('EasyParcel connection test failed', ['error' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * Current linked-wallet balance, or null when it can't be read.
     */
    public function getCreditBalance(): ?float
    {
        try {
            $response = $this->apiRequest('GET', self::VERSION.'/wallet');

            if (! $this->ok($response)) {
                return null;
            }

            $balance = $response['data']['wallet'][0]['balance'] ?? null;

            return $balance !== null ? (float) $balance : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Fetch the AWB number, label URL and current status for a booked shipment.
     * Used to populate the label once EasyParcel finishes generating it.
     *
     * @return array{awb_number: ?string, awb_url: ?string, tracking_url: ?string, status: ?string}|null
     */
    public function getShipmentDetails(string $shipmentNumber): ?array
    {
        try {
            $response = $this->apiRequest('POST', self::VERSION.'/shipment/details', [
                'shipment_number' => $shipmentNumber,
            ]);

            if (! $this->ok($response)) {
                return null;
            }

            $details = $response['data'][0]['shipment_details'] ?? [];

            return [
                'awb_number' => $details['awb_number'] ?: null,
                'awb_url' => $details['awb_url'] ?: null,
                'tracking_url' => $details['tracking_url'] ?: null,
                'status' => $details['shipment_status'] ?? null,
            ];
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Build a sender/receiver party object for the submit_orders payload.
     *
     * @return array<string, mixed>
     */
    private function party(string $name, string $phone, string $address, string $city, string $state, string $postcode): array
    {
        return [
            'name' => $name ?: 'N/A',
            'phone_number_country_code' => 'MY',
            'phone_number' => $this->localPhone($phone),
            'address_1' => $address ?: 'N/A',
            'postcode' => $postcode,
            'city' => $city ?: '',
            'subdivision_code' => EasyParcelStateMapper::getSubdivisionCode($state),
            'country_code' => 'MY',
        ];
    }

    /**
     * Normalise a Malaysian phone to the local form EasyParcel expects (no
     * country code, no leading zero): "+60 12-345 6789" / "0123456789" -> "123456789".
     */
    private function localPhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        $digits = Str::of($digits)->ltrim('0')->value();

        if (str_starts_with($digits, '60')) {
            $digits = substr($digits, 2);
        }

        return ltrim($digits, '0');
    }

    private function parseDeliveryDays(?string $delivery): ?int
    {
        if (! $delivery) {
            return null;
        }

        return preg_match('/(\d+)/', $delivery, $m) ? (int) $m[1] : null;
    }

    /**
     * @param  array<string, mixed>|null  $response
     */
    private function ok(?array $response): bool
    {
        return is_array($response) && (int) ($response['status_code'] ?? 0) === 200;
    }

    /**
     * Authenticated JSON call against the Open API, refreshing the access token
     * once on a 401.
     *
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>|null
     */
    private function apiRequest(string $method, string $path, array $body = []): ?array
    {
        $token = $this->oauth->accessToken();

        if (! $token) {
            Log::warning('EasyParcel API call without a valid access token', ['path' => $path]);

            return null;
        }

        $response = $this->send($method, $path, $token, $body);

        if ($response->status() === 401 && $this->oauth->refresh()) {
            $token = $this->oauth->accessToken();
            $response = $this->send($method, $path, (string) $token, $body);
        }

        if (! $response->successful()) {
            Log::error('EasyParcel API request failed', [
                'path' => $path,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            // Surface the body when it is structured (e.g. 401 status_code envelopes).
            $json = $response->json();

            return is_array($json) ? $json : null;
        }

        $data = $response->json();

        return is_array($data) ? $data : null;
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function send(string $method, string $path, string $token, array $body): Response
    {
        $request = Http::timeout(40)->withToken($token)->acceptJson();
        $url = self::API_BASE.$path;

        return $method === 'GET'
            ? $request->get($url)
            : $request->post($url, $body);
    }
}
