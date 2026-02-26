<?php

namespace App\DTOs\Shipping;

class ShipmentResult
{
    public function __construct(
        public readonly bool $success,
        public readonly ?string $trackingNumber = null,
        public readonly ?string $waybillNumber = null,
        public readonly ?string $sortingCode = null,
        public readonly string $message = '',
        public readonly array $rawResponse = [],
    ) {}
}
