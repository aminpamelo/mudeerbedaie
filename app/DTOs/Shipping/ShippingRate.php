<?php

namespace App\DTOs\Shipping;

class ShippingRate
{
    public function __construct(
        public readonly string $providerSlug,
        public readonly string $providerName,
        public readonly string $serviceName,
        public readonly string $serviceCode,
        public readonly float $cost,
        public readonly string $currency = 'MYR',
        public readonly ?int $estimatedDays = null,
        public readonly array $metadata = [],
    ) {}
}
