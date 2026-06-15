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
        // Aggregators (e.g. EasyParcel) return a printable AWB label, a public
        // tracking URL, and their own order reference distinct from the AWB.
        public readonly ?string $labelUrl = null,
        public readonly ?string $trackingUrl = null,
        public readonly ?string $providerOrderId = null,
        // EasyParcel cancels/queries by shipment number; the AWB + label are
        // generated asynchronously, so a booking can succeed with awbPending.
        public readonly ?string $shipmentNumber = null,
        public readonly bool $awbPending = false,
    ) {}
}
