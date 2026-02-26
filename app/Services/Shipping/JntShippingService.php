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

class JntShippingService implements ShippingProvider
{
    // Malaysia Open Platform URLs
    private const SANDBOX_BASE_URL = 'https://demoopenapi.jtexpress.my/webopenplatformapi/api';

    private const PRODUCTION_BASE_URL = 'https://ylopenapi.jtexpress.my/webopenplatformapi/api';

    public function __construct(private SettingsService $settingsService) {}

    public function getProviderName(): string
    {
        return 'J&T Express';
    }

    public function getProviderSlug(): string
    {
        return 'jnt';
    }

    public function isConfigured(): bool
    {
        return $this->settingsService->isJntConfigured();
    }

    public function isEnabled(): bool
    {
        return $this->settingsService->isJntEnabled();
    }

    public function isSandbox(): bool
    {
        return (bool) $this->settingsService->get('jnt_sandbox', true);
    }

    /**
     * @return ShippingRate[]
     */
    public function getRates(ShippingRateRequest $request): array
    {
        $payload = [
            'senderPostcode' => $request->originPostalCode,
            'receiverPostcode' => $request->destinationPostalCode,
            'weight' => (string) $request->weightKg,
            'length' => (string) ($request->lengthCm ?? 1),
            'width' => (string) ($request->widthCm ?? 1),
            'height' => (string) ($request->heightCm ?? 1),
        ];

        try {
            $response = $this->makeRequest('/order/price', $payload);

            if (! $response || ! isset($response['code']) || $response['code'] !== '1') {
                Log::warning('JNT price query returned error', [
                    'request' => $payload,
                    'response' => $response,
                ]);

                return $this->getDefaultRates($request);
            }

            $rates = [];
            $data = $response['data'] ?? [];

            // If API returns price data
            if (isset($data['price'])) {
                $rates[] = new ShippingRate(
                    providerSlug: 'jnt',
                    providerName: 'J&T Express',
                    serviceName: $this->mapServiceName($this->getDefaultServiceType()),
                    serviceCode: $this->getDefaultServiceType(),
                    cost: (float) $data['price'],
                    currency: 'MYR',
                    estimatedDays: $this->estimateDeliveryDays($this->getDefaultServiceType()),
                    metadata: $data,
                );
            } else {
                // Return default rates if no price returned
                return $this->getDefaultRates($request);
            }

            return $rates;
        } catch (\Exception $e) {
            Log::error('JNT price query failed', ['error' => $e->getMessage()]);

            return $this->getDefaultRates($request);
        }
    }

    public function createShipment(ShipmentRequest $request): ShipmentResult
    {
        $payload = [
            'eccompanyid' => $this->getCustomerCode(),
            'customerPwd' => $this->getPassword(), // Business password for order operations
            'serviceType' => $request->serviceCode ?: $this->getDefaultServiceType(),
            'orderType' => '1', // 1 = pickup, 2 = drop-off
            'expressType' => $request->serviceCode ?: $this->getDefaultServiceType(),
            'deliveryType' => '1', // 1 = standard
            'payType' => $request->paymentType === 'cod' ? '2' : '1', // 1=prepaid, 2=COD
            'goodsType' => 'ITN1', // Item type
            'totalQuantity' => (string) ($request->itemQuantity ?? 1),
            'weight' => (string) $request->weightKg,
            'itemsValue' => (string) ($request->itemValue ?? 0),
            'priceCurrency' => 'MYR',
            'remark' => $request->note ?? '',
            'txlogisticId' => $request->orderNumber,
            // Sender info
            'senderName' => $request->senderName,
            'senderMobile' => $request->senderPhone,
            'senderPhone' => $request->senderPhone,
            'senderAddress' => $request->senderAddress,
            'senderPostcode' => $request->senderPostalCode,
            'senderCity' => $request->senderCity,
            'senderProv' => JntAreaCodeMapper::getStateCode($request->senderState),
            // Receiver info
            'receiverName' => $request->receiverName,
            'receiverMobile' => $request->receiverPhone,
            'receiverPhone' => $request->receiverPhone,
            'receiverAddress' => $request->receiverAddress,
            'receiverPostcode' => $request->receiverPostalCode,
            'receiverCity' => $request->receiverCity,
            'receiverProv' => JntAreaCodeMapper::getStateCode($request->receiverState),
            // Item info
            'goodsName' => $request->itemDescription ?: 'General Items',
        ];

        try {
            $response = $this->makeRequest('/order/addOrder', $payload);

            if (! $response) {
                return new ShipmentResult(
                    success: false,
                    message: 'Empty response from JNT API.',
                    rawResponse: [],
                );
            }

            $success = ($response['code'] ?? '') === '1';
            $data = $response['data'] ?? [];

            $trackingNumber = $data['billCode'] ?? $data['billcode'] ?? null;
            $waybillNumber = $trackingNumber;
            $sortingCode = $data['sortingCode'] ?? $data['sortingcode'] ?? null;
            $message = $response['msg'] ?? $response['message'] ?? '';

            return new ShipmentResult(
                success: $success,
                trackingNumber: $trackingNumber,
                waybillNumber: $waybillNumber,
                sortingCode: $sortingCode,
                message: $success ? 'Shipment created successfully.' : $message,
                rawResponse: $response,
            );
        } catch (\Exception $e) {
            Log::error('JNT create shipment failed', [
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
        $payload = [
            'billCodes' => $trackingNumber,
            'lang' => 'en',
        ];

        try {
            $response = $this->makeRequest('/order/orderTrack', $payload);

            if (! $response || ($response['code'] ?? '') !== '1') {
                return new TrackingResult(
                    success: false,
                    trackingNumber: $trackingNumber,
                    message: $response['msg'] ?? 'Failed to retrieve tracking info.',
                    rawResponse: $response ?? [],
                );
            }

            $data = $response['data'] ?? [];
            $trackData = $data[0] ?? $data;
            $details = $trackData['details'] ?? [];

            $events = [];
            foreach ($details as $detail) {
                $events[] = [
                    'status' => $detail['scanType'] ?? $detail['scantype'] ?? '',
                    'datetime' => $detail['scanTime'] ?? $detail['scantime'] ?? '',
                    'location' => $detail['scanCity'] ?? $detail['scancity'] ?? '',
                    'description' => $detail['desc'] ?? $detail['scanStatus'] ?? '',
                ];
            }

            $currentStatus = ! empty($events) ? $events[0]['status'] : null;

            return new TrackingResult(
                success: true,
                trackingNumber: $trackingNumber,
                currentStatus: $this->mapJntStatus($currentStatus),
                events: $events,
                message: 'Tracking data retrieved.',
                rawResponse: $response,
            );
        } catch (\Exception $e) {
            Log::error('JNT tracking query failed', [
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

    public function cancelShipment(string $trackingNumber): CancelResult
    {
        $payload = [
            'billCode' => $trackingNumber,
            'orderType' => 1,
        ];

        try {
            $response = $this->makeRequest('/order/cancelOrder', $payload);

            if (! $response) {
                return new CancelResult(
                    success: false,
                    message: 'Empty response from JNT API.',
                );
            }

            $success = ($response['code'] ?? '') === '1';
            $message = $response['msg'] ?? $response['message'] ?? '';

            return new CancelResult(
                success: $success,
                message: $success ? 'Shipment cancelled successfully.' : $message,
                rawResponse: $response,
            );
        } catch (\Exception $e) {
            Log::error('JNT cancel shipment failed', [
                'error' => $e->getMessage(),
                'tracking' => $trackingNumber,
            ]);

            return new CancelResult(
                success: false,
                message: $e->getMessage(),
            );
        }
    }

    public function testConnection(): bool
    {
        try {
            // Use price inquiry as connection test
            $payload = [
                'senderPostcode' => '50000',
                'receiverPostcode' => '40000',
                'weight' => '1',
                'length' => '10',
                'width' => '10',
                'height' => '10',
            ];

            $response = $this->makeRequest('/order/price', $payload);

            // Check if we got a valid response (even if no data)
            return $response !== null && isset($response['code']);
        } catch (\Exception $e) {
            Log::warning('JNT connection test failed', ['error' => $e->getMessage()]);

            return false;
        }
    }

    private function getBaseUrl(): string
    {
        return $this->isSandbox() ? self::SANDBOX_BASE_URL : self::PRODUCTION_BASE_URL;
    }

    private function getCustomerCode(): string
    {
        return (string) $this->settingsService->get('jnt_customer_code', '');
    }

    private function getPrivateKey(): string
    {
        return (string) $this->settingsService->get('jnt_private_key', '');
    }

    private function getPassword(): string
    {
        return (string) $this->settingsService->get('jnt_password', '');
    }

    private function getDefaultServiceType(): string
    {
        return (string) $this->settingsService->get('jnt_default_service_type', 'EZ');
    }

    /**
     * Generate digest signature for Malaysia Open Platform API.
     * Signature = Base64(MD5_raw_bytes(body_json + privateKey))
     * Note: md5() with true returns raw binary (16 bytes), not hex string
     */
    private function generateDigest(string $bodyJson): string
    {
        return base64_encode(md5($bodyJson.$this->getPrivateKey(), true));
    }

    /**
     * Make an API request to J&T Express Malaysia Open Platform.
     */
    private function makeRequest(string $endpoint, array $payload): ?array
    {
        $bodyJson = json_encode($payload);
        $timestamp = (int) (microtime(true) * 1000); // milliseconds
        $digest = $this->generateDigest($bodyJson);

        $response = Http::timeout(30)
            ->withHeaders([
                'Content-Type' => 'application/x-www-form-urlencoded',
                'apiAccount' => $this->getCustomerCode(),
                'digest' => $digest,
                'timestamp' => (string) $timestamp,
            ])
            ->asForm()
            ->post($this->getBaseUrl().$endpoint, [
                'bizContent' => $bodyJson,
            ]);

        if (! $response->successful()) {
            Log::error('JNT API request failed', [
                'endpoint' => $endpoint,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        }

        $data = $response->json();

        Log::debug('JNT API response', [
            'endpoint' => $endpoint,
            'response' => $data,
        ]);

        return $data;
    }

    /**
     * Get default rates when API doesn't return pricing.
     *
     * @return ShippingRate[]
     */
    private function getDefaultRates(ShippingRateRequest $request): array
    {
        // Default pricing based on weight (approximate JNT Malaysia rates)
        $basePrice = 6.00; // Base rate
        $pricePerKg = 2.00; // Per additional kg
        $weight = max($request->weightKg, 0.5);

        $ezPrice = $basePrice + (max($weight - 1, 0) * $pricePerKg);
        $exPrice = $ezPrice * 1.5; // Express is ~50% more

        return [
            new ShippingRate(
                providerSlug: 'jnt',
                providerName: 'J&T Express',
                serviceName: 'J&T Domestic Standard',
                serviceCode: 'EZ',
                cost: round($ezPrice, 2),
                currency: 'MYR',
                estimatedDays: 3,
                metadata: ['source' => 'default_rates'],
            ),
            new ShippingRate(
                providerSlug: 'jnt',
                providerName: 'J&T Express',
                serviceName: 'J&T Express Next Day',
                serviceCode: 'EX',
                cost: round($exPrice, 2),
                currency: 'MYR',
                estimatedDays: 1,
                metadata: ['source' => 'default_rates'],
            ),
        ];
    }

    private function mapServiceName(string $expressType): string
    {
        return match ($expressType) {
            'EZ' => 'J&T Domestic Standard',
            'EX' => 'J&T Express Next Day',
            'FD' => 'J&T Fresh Delivery',
            default => 'J&T Express ('.$expressType.')',
        };
    }

    private function estimateDeliveryDays(string $expressType): int
    {
        return match ($expressType) {
            'EX' => 1,
            'EZ' => 3,
            'FD' => 1,
            default => 5,
        };
    }

    private function mapJntStatus(?string $scanType): string
    {
        if ($scanType === null) {
            return 'unknown';
        }

        return match (strtoupper($scanType)) {
            'PICKUP' => 'picked_up',
            'GATEWAY_IN', 'GATEWAY_OUT' => 'in_transit',
            'DELIVERY' => 'out_for_delivery',
            'SIGNED' => 'delivered',
            'PROBLEM' => 'exception',
            'RETURN' => 'returned',
            default => 'in_transit',
        };
    }
}
