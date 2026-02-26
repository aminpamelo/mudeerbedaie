<?php

namespace App\DTOs\Shipping;

class TrackingResult
{
    /**
     * @param  array<int, array{status: string, datetime: string, location: string, description: string}>  $events
     */
    public function __construct(
        public readonly bool $success,
        public readonly ?string $trackingNumber = null,
        public readonly ?string $currentStatus = null,
        public readonly array $events = [],
        public readonly ?string $estimatedDelivery = null,
        public readonly string $message = '',
        public readonly array $rawResponse = [],
    ) {}
}
