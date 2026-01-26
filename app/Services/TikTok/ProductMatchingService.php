<?php

declare(strict_types=1);

namespace App\Services\TikTok;

use App\Models\PlatformAccount;
use App\Models\PlatformSkuMapping;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\Log;

/**
 * Service for matching TikTok products with internal products.
 *
 * Matching Strategy (in order of confidence):
 * 1. SKU exact match (100% confidence)
 * 2. Barcode/EAN match (100% confidence)
 * 3. Platform SKU already mapped (100% confidence)
 * 4. Name + Price similarity (70-90% confidence)
 */
class ProductMatchingService
{
    /**
     * Confidence thresholds.
     */
    private const AUTO_LINK_THRESHOLD = 100.0;

    private const SUGGEST_THRESHOLD = 70.0;

    private const NAME_SIMILARITY_THRESHOLD = 0.80;

    /**
     * Find a match for a TikTok product.
     */
    public function findMatch(array $tiktokProduct, PlatformAccount $account): ?MatchResult
    {
        Log::debug('[ProductMatching] Finding match for product', [
            'product_id' => $tiktokProduct['id'] ?? $tiktokProduct['product_id'] ?? 'unknown',
            'product_name' => $tiktokProduct['product_name'] ?? $tiktokProduct['name'] ?? 'unknown',
        ]);

        // Try each matching strategy in order of confidence
        $strategies = [
            fn () => $this->matchByExistingMapping($tiktokProduct, $account),
            fn () => $this->matchBySku($tiktokProduct),
            fn () => $this->matchByBarcode($tiktokProduct),
            fn () => $this->matchByNameSimilarity($tiktokProduct),
        ];

        foreach ($strategies as $strategy) {
            $match = $strategy();
            if ($match !== null) {
                Log::info('[ProductMatching] Match found', [
                    'product_id' => $tiktokProduct['id'] ?? $tiktokProduct['product_id'] ?? 'unknown',
                    'matched_product' => $match->product->name,
                    'confidence' => $match->confidence,
                    'reason' => $match->matchReason,
                ]);

                return $match;
            }
        }

        Log::debug('[ProductMatching] No match found for product', [
            'product_id' => $tiktokProduct['id'] ?? $tiktokProduct['product_id'] ?? 'unknown',
        ]);

        return null;
    }

    /**
     * Match by existing platform SKU mapping.
     */
    public function matchByExistingMapping(array $tiktokProduct, PlatformAccount $account): ?MatchResult
    {
        $platformSku = $this->extractSku($tiktokProduct);
        $productId = $tiktokProduct['id'] ?? $tiktokProduct['product_id'] ?? null;

        if (! $platformSku && ! $productId) {
            return null;
        }

        // Check for existing mapping by SKU
        if ($platformSku) {
            $mapping = PlatformSkuMapping::where('platform_account_id', $account->id)
                ->where('platform_sku', $platformSku)
                ->where('is_active', true)
                ->with(['product', 'productVariant'])
                ->first();

            if ($mapping && $mapping->product) {
                return new MatchResult(
                    product: $mapping->product,
                    variant: $mapping->productVariant,
                    confidence: 100.0,
                    matchReason: 'Existing SKU mapping',
                    autoLink: true
                );
            }
        }

        // Check for existing mapping by platform product ID in metadata
        if ($productId) {
            $mapping = PlatformSkuMapping::where('platform_account_id', $account->id)
                ->where('is_active', true)
                ->whereJsonContains('mapping_metadata->platform_product_id', $productId)
                ->with(['product', 'productVariant'])
                ->first();

            if ($mapping && $mapping->product) {
                return new MatchResult(
                    product: $mapping->product,
                    variant: $mapping->productVariant,
                    confidence: 100.0,
                    matchReason: 'Existing product ID mapping',
                    autoLink: true
                );
            }
        }

        return null;
    }

    /**
     * Match by SKU exact match.
     */
    public function matchBySku(array $tiktokProduct): ?MatchResult
    {
        $platformSku = $this->extractSku($tiktokProduct);

        if (! $platformSku) {
            return null;
        }

        // Try to find product by SKU
        $product = Product::where('sku', $platformSku)->first();
        if ($product) {
            return new MatchResult(
                product: $product,
                variant: null,
                confidence: 100.0,
                matchReason: 'SKU exact match (product)',
                autoLink: true
            );
        }

        // Try to find variant by SKU
        $variant = ProductVariant::where('sku', $platformSku)
            ->with('product')
            ->first();

        if ($variant && $variant->product) {
            return new MatchResult(
                product: $variant->product,
                variant: $variant,
                confidence: 100.0,
                matchReason: 'SKU exact match (variant)',
                autoLink: true
            );
        }

        return null;
    }

    /**
     * Match by barcode/EAN.
     */
    public function matchByBarcode(array $tiktokProduct): ?MatchResult
    {
        $barcode = $this->extractBarcode($tiktokProduct);

        if (! $barcode) {
            return null;
        }

        // Try to find product by barcode
        $product = Product::where('barcode', $barcode)->first();
        if ($product) {
            return new MatchResult(
                product: $product,
                variant: null,
                confidence: 100.0,
                matchReason: 'Barcode exact match (product)',
                autoLink: true
            );
        }

        // Try to find variant by barcode
        $variant = ProductVariant::where('barcode', $barcode)
            ->with('product')
            ->first();

        if ($variant && $variant->product) {
            return new MatchResult(
                product: $variant->product,
                variant: $variant,
                confidence: 100.0,
                matchReason: 'Barcode exact match (variant)',
                autoLink: true
            );
        }

        return null;
    }

    /**
     * Match by name similarity.
     */
    public function matchByNameSimilarity(array $tiktokProduct, float $threshold = self::NAME_SIMILARITY_THRESHOLD): ?MatchResult
    {
        $tiktokName = $tiktokProduct['title'] ?? $tiktokProduct['product_name'] ?? $tiktokProduct['name'] ?? null;
        $tiktokPrice = $this->extractPrice($tiktokProduct);

        if (! $tiktokName) {
            return null;
        }

        $normalizedTiktokName = $this->normalizeProductName($tiktokName);
        $bestMatch = null;
        $bestSimilarity = 0;

        // Get all active products for comparison
        // In a large catalog, you might want to optimize this with full-text search
        $products = Product::where('status', 'active')
            ->select(['id', 'name', 'base_price', 'sku'])
            ->limit(1000) // Limit for performance
            ->get();

        foreach ($products as $product) {
            $similarity = $this->calculateNameSimilarity($normalizedTiktokName, $this->normalizeProductName($product->name));

            if ($similarity > $bestSimilarity && $similarity >= $threshold) {
                $bestSimilarity = $similarity;
                $bestMatch = $product;
            }
        }

        if (! $bestMatch) {
            return null;
        }

        // Calculate confidence based on similarity and price match
        $confidence = $bestSimilarity * 100;

        // Boost confidence if prices match
        if ($tiktokPrice !== null && $bestMatch->base_price !== null) {
            $priceDiff = abs($tiktokPrice - (float) $bestMatch->base_price);
            $priceMatch = $priceDiff < 1.0; // Within 1 currency unit
            if ($priceMatch) {
                $confidence = min(90, $confidence + 10); // Boost by 10%, max 90%
            }
        }

        // Never auto-link name matches - always suggest
        return new MatchResult(
            product: $bestMatch,
            variant: null,
            confidence: round($confidence, 2),
            matchReason: sprintf('Name similarity (%.0f%%)', $bestSimilarity * 100),
            autoLink: false
        );
    }

    /**
     * Calculate name similarity between two product names.
     */
    public function calculateNameSimilarity(string $name1, string $name2): float
    {
        if (empty($name1) || empty($name2)) {
            return 0.0;
        }

        // Use combination of algorithms for better accuracy
        $maxLen = max(strlen($name1), strlen($name2));
        if ($maxLen === 0) {
            return 0.0;
        }

        // Levenshtein distance (normalized)
        $levenshtein = 1 - (levenshtein($name1, $name2) / $maxLen);

        // Similar text percentage
        similar_text($name1, $name2, $percent);
        $similarText = $percent / 100;

        // Weight: 60% similar_text, 40% levenshtein
        return ($similarText * 0.6) + ($levenshtein * 0.4);
    }

    /**
     * Normalize a product name for comparison.
     */
    public function normalizeProductName(string $name): string
    {
        // Convert to lowercase
        $name = strtolower($name);

        // Remove special characters except spaces
        $name = preg_replace('/[^a-z0-9\s]/', '', $name);

        // Normalize multiple spaces
        $name = preg_replace('/\s+/', ' ', $name);

        // Remove common filler words that don't help with matching
        $fillerWords = ['the', 'a', 'an', 'and', 'or', 'for', 'with', 'free', 'shipping'];
        $words = explode(' ', trim($name));
        $words = array_filter($words, fn ($word) => ! in_array($word, $fillerWords) && strlen($word) > 1);

        return implode(' ', $words);
    }

    /**
     * Check if a match should be auto-linked.
     */
    public function shouldAutoLink(MatchResult $match): bool
    {
        return $match->autoLink && $match->confidence >= self::AUTO_LINK_THRESHOLD;
    }

    /**
     * Check if a match should be suggested.
     */
    public function shouldSuggest(MatchResult $match): bool
    {
        return $match->confidence >= self::SUGGEST_THRESHOLD;
    }

    /**
     * Extract SKU from TikTok product data.
     */
    private function extractSku(array $tiktokProduct): ?string
    {
        // Try different locations where SKU might be stored
        if (isset($tiktokProduct['skus'][0]['seller_sku'])) {
            return $tiktokProduct['skus'][0]['seller_sku'];
        }
        if (isset($tiktokProduct['seller_sku'])) {
            return $tiktokProduct['seller_sku'];
        }
        if (isset($tiktokProduct['sku'])) {
            return $tiktokProduct['sku'];
        }

        return null;
    }

    /**
     * Extract barcode from TikTok product data.
     */
    private function extractBarcode(array $tiktokProduct): ?string
    {
        // Try different locations where barcode might be stored
        if (isset($tiktokProduct['skus'][0]['identifier_code']['identifier_code'])) {
            return $tiktokProduct['skus'][0]['identifier_code']['identifier_code'];
        }
        if (isset($tiktokProduct['barcode'])) {
            return $tiktokProduct['barcode'];
        }
        if (isset($tiktokProduct['ean'])) {
            return $tiktokProduct['ean'];
        }
        if (isset($tiktokProduct['gtin'])) {
            return $tiktokProduct['gtin'];
        }

        return null;
    }

    /**
     * Extract price from TikTok product data.
     */
    private function extractPrice(array $tiktokProduct): ?float
    {
        if (isset($tiktokProduct['skus'][0]['price']['sale_price'])) {
            return (float) $tiktokProduct['skus'][0]['price']['sale_price'];
        }
        if (isset($tiktokProduct['price'])) {
            return (float) $tiktokProduct['price'];
        }
        if (isset($tiktokProduct['sale_price'])) {
            return (float) $tiktokProduct['sale_price'];
        }

        return null;
    }
}

/**
 * Data class representing a match result.
 */
class MatchResult
{
    public function __construct(
        public Product $product,
        public ?ProductVariant $variant,
        public float $confidence,
        public string $matchReason,
        public bool $autoLink = false
    ) {}

    /**
     * Convert to array for storage.
     */
    public function toArray(): array
    {
        return [
            'product_id' => $this->product->id,
            'variant_id' => $this->variant?->id,
            'confidence' => $this->confidence,
            'reason' => $this->matchReason,
            'auto_link' => $this->autoLink,
        ];
    }
}
