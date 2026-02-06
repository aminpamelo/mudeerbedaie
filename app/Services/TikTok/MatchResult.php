<?php

declare(strict_types=1);

namespace App\Services\TikTok;

use App\Models\Package;
use App\Models\Product;
use App\Models\ProductVariant;

/**
 * Data class representing a match result.
 */
class MatchResult
{
    public function __construct(
        public ?Product $product = null,
        public ?ProductVariant $variant = null,
        public ?Package $package = null,
        public float $confidence = 0,
        public string $matchReason = '',
        public bool $autoLink = false
    ) {}

    public function isPackageMatch(): bool
    {
        return $this->package !== null;
    }

    /**
     * Convert to array for storage.
     */
    public function toArray(): array
    {
        return [
            'product_id' => $this->product?->id,
            'variant_id' => $this->variant?->id,
            'package_id' => $this->package?->id,
            'confidence' => $this->confidence,
            'reason' => $this->matchReason,
            'auto_link' => $this->autoLink,
            'type' => $this->isPackageMatch() ? 'package' : 'product',
        ];
    }
}
