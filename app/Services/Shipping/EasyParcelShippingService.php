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
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * EasyParcel shipping aggregator (Malaysia).
 *
 * EasyParcel exposes a single endpoint per environment; the operation is chosen
 * with the `?ac=` query parameter and the body is form-encoded with the API key
 * plus a `bulk` array of one shipment. Booking is a two-step flow — submit the
 * order to reserve it, then pay from the account's prepaid credit, which returns
 * the AWB (tracking number) and a printable label. `createShipment()` performs
 * both steps so a single call yields a ready-to-ship parcel.
 *
 * @see https://developers.easyparcel.com/
 */
class EasyParcelShippingService implements ShippingProvider
{
    private const SANDBOX_BASE_URL = 'https://demo.connect.easyparcel.my';

    private const PRODUCTION_BASE_URL = 'https://connect.easyparcel.my';

    public function __construct(private SettingsService $settingsService) {}

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
        return $this->settingsService->isEasyParcelConfigured();
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
     * Quote couriers for a parcel. EasyParcel returns one rate per available
     * courier service; the `serviceCode` carried back is the EasyParcel
     * `service_id`, which `createShipment()` needs to book that exact service.
     *
     * @return ShippingRate[]
     */
    public function getRates(ShippingRateRequest $request): array
    {
        $payload = [[
            'pick_code' => $request->originPostalCode,
            'pick_state' => EasyParcelStateMapper::getStateCode($request->originState),
            'pick_country' => 'MY',
            'send_code' => $request->destinationPostalCode,
            'send_state' => EasyParcelStateMapper::getStateCode($request->destinationState),
            'send_country' => 'MY',
            'weight' => (string) max($request->weightKg, 0.1),
            'width' => (string) ($request->widthCm ?? 0),
            'length' => (string) ($request->lengthCm ?? 0),
            'height' => (string) ($request->heightCm ?? 0),
        ]];

        try {
            $response = $this->makeRequest('EPRateCheckingBulk', $payload);

            if (! $this->ok($response)) {
                Log::warning('EasyParcel rate checking returned error', [
                    'request' => $payload,
                    'response' => $response,
                ]);

                return [];
            }

            $rates = [];
            $block = $response['result'][0] ?? [];

            foreach ($block['rates'] ?? [] as $rate) {
                $courier = $rate['courier_name'] ?? 'Courier';
                $service = $rate['service_name'] ?? '';

                $rates[] = new ShippingRate(
                    providerSlug: 'easyparcel',
                    providerName: 'EasyParcel',
                    serviceName: trim($courier.($service ? ' — '.$service : '')),
                    serviceCode: (string) ($rate['service_id'] ?? ''),
                    cost: (float) ($rate['price'] ?? 0),
                    currency: 'MYR',
                    estimatedDays: $this->parseDeliveryDays($rate['delivery'] ?? null),
                    metadata: $rate,
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

        try {
            // Step 1 — submit the order to reserve the chosen courier service.
            $submitPayload = [[
                'weight' => (string) max($request->weightKg, 0.1),
                'content' => $request->itemDescription ?: 'General goods',
                'value' => (string) ($request->itemValue ?? 0),
                'service_id' => $request->serviceCode,
                'collect_date' => now()->addDay()->format('Y-m-d'),
                // Sender (pickup)
                'pick_name' => $request->senderName,
                'pick_contact' => $request->senderPhone,
                'pick_mobile' => $request->senderPhone,
                'pick_addr1' => $request->senderAddress,
                'pick_city' => $request->senderCity,
                'pick_code' => $request->senderPostalCode,
                'pick_state' => EasyParcelStateMapper::getStateCode($request->senderState),
                'pick_country' => 'MY',
                // Receiver (send)
                'send_name' => $request->receiverName,
                'send_contact' => $request->receiverPhone,
                'send_mobile' => $request->receiverPhone,
                'send_addr1' => $request->receiverAddress,
                'send_city' => $request->receiverCity,
                'send_code' => $request->receiverPostalCode,
                'send_state' => EasyParcelStateMapper::getStateCode($request->receiverState),
                'send_country' => 'MY',
            ]];

            $submit = $this->makeRequest('EPSubmitOrderBulk', $submitPayload);
            $submitResult = $submit['result'][0] ?? [];

            if (! $this->ok($submit) || ($submitResult['status'] ?? '') !== 'Success') {
                return new ShipmentResult(
                    success: false,
                    message: $this->errorMessage($submit, $submitResult, 'Failed to submit EasyParcel order.'),
                    rawResponse: ['submit' => $submit],
                );
            }

            $orderNo = $submitResult['order_number'] ?? $submitResult['orderno'] ?? null;

            if (! $orderNo) {
                return new ShipmentResult(
                    success: false,
                    message: 'EasyParcel did not return an order number.',
                    rawResponse: ['submit' => $submit],
                );
            }

            // Step 2 — pay from prepaid credit; this generates the AWB + label.
            $pay = $this->makeRequest('EPPayOrderBulk', [['order_no' => $orderNo]]);
            $payResult = $pay['result'][0] ?? [];

            if (! $this->ok($pay)) {
                return new ShipmentResult(
                    success: false,
                    message: $this->errorMessage($pay, $payResult, 'EasyParcel order submitted but payment failed.'),
                    rawResponse: ['submit' => $submit, 'pay' => $pay],
                    providerOrderId: $orderNo,
                );
            }

            $parcel = $payResult['parcel'][0] ?? [];
            $awb = $parcel['awb'] ?? null;

            if (! $awb) {
                return new ShipmentResult(
                    success: false,
                    message: $payResult['messagenow'] ?? 'EasyParcel did not return an AWB. Check your account credit balance.',
                    rawResponse: ['submit' => $submit, 'pay' => $pay],
                    providerOrderId: $orderNo,
                );
            }

            return new ShipmentResult(
                success: true,
                trackingNumber: $awb,
                waybillNumber: $awb,
                message: 'Shipment booked and paid successfully.',
                rawResponse: ['submit' => $submit, 'pay' => $pay],
                labelUrl: $parcel['awb_id_link'] ?? null,
                trackingUrl: $parcel['tracking_url'] ?? null,
                providerOrderId: $orderNo,
            );
        } catch (\Exception $e) {
            Log::error('EasyParcel create shipment failed', [
                'error' => $e->getMessage(),
                'order' => $request->orderNumber,
            ]);

            return new ShipmentResult(
                success: false,
                message: $e->getMessage(),
            );
        }
    }

    public function getTracking(string $trackingNumber): TrackingResult
    {
        try {
            $response = $this->makeRequest('EPTrackingBulk', [['awb' => $trackingNumber]]);

            if (! $this->ok($response)) {
                return new TrackingResult(
                    success: false,
                    trackingNumber: $trackingNumber,
                    message: $response['error_remark'] ?? 'Failed to retrieve tracking info.',
                    rawResponse: $response ?? [],
                );
            }

            $block = $response['result'][0] ?? [];
            $events = [];

            foreach ($block['latest_status'] ?? ($block['tracking'] ?? []) as $detail) {
                $events[] = [
                    'status' => $detail['status'] ?? '',
                    'datetime' => $detail['event_time'] ?? $detail['date'] ?? '',
                    'location' => $detail['location'] ?? '',
                    'description' => $detail['details'] ?? $detail['status'] ?? '',
                ];
            }

            return new TrackingResult(
                success: true,
                trackingNumber: $trackingNumber,
                currentStatus: $events[0]['status'] ?? null,
                events: $events,
                message: 'Tracking data retrieved.',
                rawResponse: $response,
            );
        } catch (\Exception $e) {
            Log::error('EasyParcel tracking query failed', [
                'error' => $e->getMessage(),
                'tracking' => $trackingNumber,
            ]);

            return new TrackingResult(
                success: false,
                trackingNumber: $trackingNumber,
                message: $e->getMessage(),
            );
        }
    }

    /**
     * EasyParcel paid shipments cannot be voided through this API — cancellation
     * and refunds are handled from the EasyParcel dashboard. Surfaced as an
     * informative failure rather than a silent error.
     */
    public function cancelShipment(string $trackingNumber): CancelResult
    {
        return new CancelResult(
            success: false,
            message: 'EasyParcel shipments are cancelled/refunded from the EasyParcel dashboard, not via the API.',
        );
    }

    public function testConnection(): bool
    {
        try {
            $response = $this->makeRequest('EPCheckCreditBalance', []);

            return $this->ok($response);
        } catch (\Exception $e) {
            Log::warning('EasyParcel connection test failed', ['error' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * Current prepaid credit balance, or null when it can't be read. Used by the
     * settings screen so the admin can confirm there is credit to pay for labels.
     */
    public function getCreditBalance(): ?float
    {
        try {
            $response = $this->makeRequest('EPCheckCreditBalance', []);

            if (! $this->ok($response)) {
                return null;
            }

            $balance = $response['result']['wallet'] ?? $response['wallet'] ?? $response['result'][0]['balance'] ?? null;

            return $balance !== null ? (float) $balance : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    private function getBaseUrl(): string
    {
        return $this->isSandbox() ? self::SANDBOX_BASE_URL : self::PRODUCTION_BASE_URL;
    }

    private function getApiKey(): string
    {
        return (string) $this->settingsService->get('easyparcel_api_key', '');
    }

    /**
     * Whether the EasyParcel response reports overall success.
     *
     * @param  array<string, mixed>|null  $response
     */
    private function ok(?array $response): bool
    {
        return is_array($response)
            && (($response['api_status'] ?? null) === 'Success' || ($response['error_code'] ?? '1') === '0');
    }

    /**
     * @param  array<string, mixed>|null  $response
     * @param  array<string, mixed>  $result
     */
    private function errorMessage(?array $response, array $result, string $fallback): string
    {
        return $result['remarks']
            ?? $result['error_remark']
            ?? $response['error_remark']
            ?? $fallback;
    }

    private function parseDeliveryDays(?string $delivery): ?int
    {
        if (! $delivery) {
            return null;
        }

        if (preg_match('/(\d+)/', $delivery, $m)) {
            return (int) $m[1];
        }

        return null;
    }

    /**
     * POST a single-shipment `bulk` payload to an EasyParcel action.
     *
     * @param  array<int, array<string, mixed>>  $bulk
     * @return array<string, mixed>|null
     */
    private function makeRequest(string $action, array $bulk): ?array
    {
        $body = ['api' => $this->getApiKey()];

        if ($bulk !== []) {
            $body['bulk'] = $bulk;
        }

        $response = Http::timeout(30)
            ->asForm()
            ->post($this->getBaseUrl().'/?ac='.$action, $body);

        if (! $response->successful()) {
            Log::error('EasyParcel API request failed', [
                'action' => $action,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        }

        $data = $response->json();

        Log::debug('EasyParcel API response', [
            'action' => $action,
            'response' => $data,
        ]);

        return is_array($data) ? $data : null;
    }
}
