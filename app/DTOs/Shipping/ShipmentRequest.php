<?php

namespace App\DTOs\Shipping;

class ShipmentRequest
{
    public function __construct(
        public readonly string $orderNumber,
        public readonly string $senderName,
        public readonly string $senderPhone,
        public readonly string $senderAddress,
        public readonly string $senderCity,
        public readonly string $senderState,
        public readonly string $senderPostalCode,
        public readonly string $receiverName,
        public readonly string $receiverPhone,
        public readonly string $receiverAddress,
        public readonly string $receiverCity,
        public readonly string $receiverState,
        public readonly string $receiverPostalCode,
        public readonly float $weightKg,
        public readonly string $itemDescription = '',
        public readonly ?float $itemValue = null,
        public readonly int $itemQuantity = 1,
        public readonly string $serviceCode = 'EZ',
        public readonly string $paymentType = 'PP_PM',
        public readonly ?string $note = null,
    ) {}
}
