<?php

namespace App\Contracts\Shipping;

use App\DTOs\Shipping\CancelResult;
use App\DTOs\Shipping\ShipmentRequest;
use App\DTOs\Shipping\ShipmentResult;
use App\DTOs\Shipping\ShippingRate;
use App\DTOs\Shipping\ShippingRateRequest;
use App\DTOs\Shipping\TrackingResult;

interface ShippingProvider
{
    public function getProviderName(): string;

    public function getProviderSlug(): string;

    public function isConfigured(): bool;

    public function isEnabled(): bool;

    public function isSandbox(): bool;

    /**
     * @return ShippingRate[]
     */
    public function getRates(ShippingRateRequest $request): array;

    public function createShipment(ShipmentRequest $request): ShipmentResult;

    public function getTracking(string $trackingNumber): TrackingResult;

    public function cancelShipment(string $trackingNumber): CancelResult;

    public function testConnection(): bool;
}
