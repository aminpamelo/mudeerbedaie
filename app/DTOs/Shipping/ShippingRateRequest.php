<?php

namespace App\DTOs\Shipping;

class ShippingRateRequest
{
    public function __construct(
        public readonly string $originPostalCode,
        public readonly string $originCity,
        public readonly string $originState,
        public readonly string $destinationPostalCode,
        public readonly string $destinationCity,
        public readonly string $destinationState,
        public readonly float $weightKg,
        public readonly ?float $lengthCm = null,
        public readonly ?float $widthCm = null,
        public readonly ?float $heightCm = null,
        public readonly ?float $itemValue = null,
    ) {}
}
