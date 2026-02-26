<?php

namespace App\DTOs\Shipping;

class CancelResult
{
    public function __construct(
        public readonly bool $success,
        public readonly string $message = '',
        public readonly array $rawResponse = [],
    ) {}
}
