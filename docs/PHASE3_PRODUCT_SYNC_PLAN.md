# Phase 3: TikTok Product Synchronization Plan

## Overview

This document details the implementation plan for importing and synchronizing products from TikTok Shop to the internal system with smart matching capabilities and a review queue for unmatched products.

---

## Requirements Summary

| Requirement | Decision |
|-------------|----------|
| Source of Truth | Bidirectional (both internal and TikTok) |
| SKU Strategy | Mixed (some match, some don't) |
| Unmatched Products | Queue for manual review |
| Sync Direction | Import only (TikTok → Internal) |

---

## Database Schema

### New Table: `pending_platform_products`

Stores TikTok products that couldn't be automatically matched and need manual review.

```sql
CREATE TABLE pending_platform_products (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    platform_id BIGINT UNSIGNED NOT NULL,
    platform_account_id BIGINT UNSIGNED NOT NULL,

    -- TikTok Product Data
    platform_product_id VARCHAR(255) NOT NULL,
    platform_sku VARCHAR(255) NULL,
    name VARCHAR(500) NOT NULL,
    description TEXT NULL,

    -- Pricing
    price DECIMAL(12,2) NULL,
    original_price DECIMAL(12,2) NULL,
    currency VARCHAR(10) DEFAULT 'MYR',

    -- Product Details
    category_id VARCHAR(255) NULL,
    category_name VARCHAR(500) NULL,
    brand VARCHAR(255) NULL,

    -- Images
    main_image_url TEXT NULL,
    images JSON NULL,

    -- Variants (for variable products)
    variants JSON NULL,

    -- Stock Info
    quantity INT DEFAULT 0,

    -- Matching Suggestions
    suggested_product_id BIGINT UNSIGNED NULL,
    suggested_variant_id BIGINT UNSIGNED NULL,
    match_confidence DECIMAL(5,2) NULL,
    match_reason VARCHAR(255) NULL,

    -- Status
    status ENUM('pending', 'linked', 'created', 'ignored') DEFAULT 'pending',
    reviewed_at TIMESTAMP NULL,
    reviewed_by BIGINT UNSIGNED NULL,

    -- Full API Response
    raw_data JSON NULL,

    -- Timestamps
    fetched_at TIMESTAMP NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    -- Indexes
    FOREIGN KEY (platform_id) REFERENCES platforms(id) ON DELETE CASCADE,
    FOREIGN KEY (platform_account_id) REFERENCES platform_accounts(id) ON DELETE CASCADE,
    FOREIGN KEY (suggested_product_id) REFERENCES products(id) ON DELETE SET NULL,
    FOREIGN KEY (suggested_variant_id) REFERENCES product_variants(id) ON DELETE SET NULL,
    FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL,

    UNIQUE KEY unique_platform_product (platform_account_id, platform_product_id),
    INDEX idx_status (status),
    INDEX idx_platform_sku (platform_sku)
);
```

### Modifications to Existing Tables

**`platform_accounts`** - Add product sync tracking:
```sql
ALTER TABLE platform_accounts ADD COLUMN last_product_sync_at TIMESTAMP NULL;
ALTER TABLE platform_accounts ADD COLUMN product_sync_status ENUM('idle', 'syncing', 'completed', 'error') DEFAULT 'idle';
```

---

## Models

### PendingPlatformProduct Model

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PendingPlatformProduct extends Model
{
    protected $fillable = [
        'platform_id',
        'platform_account_id',
        'platform_product_id',
        'platform_sku',
        'name',
        'description',
        'price',
        'original_price',
        'currency',
        'category_id',
        'category_name',
        'brand',
        'main_image_url',
        'images',
        'variants',
        'quantity',
        'suggested_product_id',
        'suggested_variant_id',
        'match_confidence',
        'match_reason',
        'status',
        'reviewed_at',
        'reviewed_by',
        'raw_data',
        'fetched_at',
    ];

    protected function casts(): array
    {
        return [
            'images' => 'array',
            'variants' => 'array',
            'raw_data' => 'array',
            'price' => 'decimal:2',
            'original_price' => 'decimal:2',
            'match_confidence' => 'decimal:2',
            'fetched_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    // Relationships
    public function platform(): BelongsTo;
    public function platformAccount(): BelongsTo;
    public function suggestedProduct(): BelongsTo;
    public function suggestedVariant(): BelongsTo;
    public function reviewer(): BelongsTo;

    // Scopes
    public function scopePending($query);
    public function scopeForAccount($query, int $accountId);
    public function scopeWithSuggestions($query);

    // Actions
    public function linkToProduct(Product $product, ?ProductVariant $variant = null): PlatformSkuMapping;
    public function createAsNewProduct(): Product;
    public function ignore(): void;
}
```

---

## Services

### 1. ProductMatchingService

Handles smart matching between TikTok products and internal products.

```php
<?php

namespace App\Services\TikTok;

class ProductMatchingService
{
    /**
     * Matching Strategy (in order of confidence):
     * 1. SKU exact match (100% confidence)
     * 2. Barcode/EAN match (100% confidence)
     * 3. Platform SKU already mapped (100% confidence)
     * 4. Name + Price similarity (70-90% confidence)
     */

    public function findMatch(array $tiktokProduct, PlatformAccount $account): ?MatchResult
    {
        // Try each matching strategy
        if ($match = $this->matchBySku($tiktokProduct)) {
            return $match;
        }

        if ($match = $this->matchByBarcode($tiktokProduct)) {
            return $match;
        }

        if ($match = $this->matchByExistingMapping($tiktokProduct, $account)) {
            return $match;
        }

        if ($match = $this->matchByNameSimilarity($tiktokProduct)) {
            return $match;
        }

        return null;
    }

    public function matchBySku(array $tiktokProduct): ?MatchResult;
    public function matchByBarcode(array $tiktokProduct): ?MatchResult;
    public function matchByExistingMapping(array $tiktokProduct, PlatformAccount $account): ?MatchResult;
    public function matchByNameSimilarity(array $tiktokProduct, float $threshold = 0.8): ?MatchResult;

    // Utility methods
    public function calculateNameSimilarity(string $name1, string $name2): float;
    public function normalizeProductName(string $name): string;
}

class MatchResult
{
    public function __construct(
        public Product $product,
        public ?ProductVariant $variant,
        public float $confidence,
        public string $matchReason,
        public bool $autoLink = false
    ) {}
}
```

### 2. TikTokProductSyncService

Main service for importing products from TikTok.

```php
<?php

namespace App\Services\TikTok;

class TikTokProductSyncService
{
    public function __construct(
        private TikTokClientFactory $clientFactory,
        private ProductMatchingService $matchingService
    ) {}

    /**
     * Sync products from TikTok Shop
     */
    public function syncProducts(PlatformAccount $account, array $options = []): SyncResult
    {
        // 1. Fetch products from TikTok API
        // 2. Process each product through matching engine
        // 3. Auto-link high-confidence matches
        // 4. Queue low-confidence matches for review
        // 5. Return sync statistics
    }

    /**
     * Fetch all products from TikTok API (with pagination)
     */
    public function fetchProducts(PlatformAccount $account): Collection;

    /**
     * Process a single product
     */
    public function processProduct(PlatformAccount $account, array $tiktokProduct): ProcessResult;

    /**
     * Create platform SKU mapping for matched product
     */
    public function createMapping(
        PlatformAccount $account,
        Product $product,
        ?ProductVariant $variant,
        array $tiktokProduct
    ): PlatformSkuMapping;

    /**
     * Queue product for manual review
     */
    public function queueForReview(
        PlatformAccount $account,
        array $tiktokProduct,
        ?MatchResult $suggestion = null
    ): PendingPlatformProduct;

    /**
     * Get sync progress
     */
    public static function getSyncProgress(int $accountId): ?array;
}

class SyncResult
{
    public int $total = 0;
    public int $autoLinked = 0;
    public int $queuedForReview = 0;
    public int $alreadyLinked = 0;
    public int $failed = 0;
    public array $errors = [];
}
```

---

## Jobs

### SyncTikTokProducts Job

```php
<?php

namespace App\Jobs;

class SyncTikTokProducts implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(
        public PlatformAccount $account,
        public bool $fullSync = false
    ) {}

    public function handle(TikTokProductSyncService $syncService): void
    {
        // Similar to order sync job
        // Update progress in cache
        // Handle errors with retry
    }

    public function failed(?Exception $exception): void;
}
```

---

## UI Components

### 1. Products Tab on Account Show Page

Add a "Products" tab to the existing account show page showing:
- Product sync button
- Sync status and last sync time
- Count of linked products
- Count of pending products for review
- Quick access to pending products page

### 2. Pending Products Review Page

New Livewire component: `resources/views/livewire/admin/platforms/products/pending.blade.php`

**Features:**
- List of pending TikTok products with images
- Show match suggestions with confidence score
- Search/filter functionality
- Actions per product:
  - **Link to Existing**: Open modal to search internal products
  - **Create New**: Create internal product from TikTok data
  - **Ignore**: Mark as ignored (won't show again)
- Bulk actions:
  - Accept all suggestions (for high confidence matches)
  - Ignore selected

### 3. Product Linking Modal

Modal component for manually linking TikTok product to internal product:
- Search internal products by name/SKU
- Show product details side-by-side
- Option to link to specific variant
- Confirm and create mapping

### 4. Product Mapping Management Page

View and manage all product mappings for an account:
- List of all linked products
- Edit/remove mappings
- Re-sync product data from TikTok

---

## API Integration

### TikTok Shop Product Endpoints

Using `ecomphp/tiktokshop-php` SDK:

```php
// Get product list
$client->Product->getProducts([
    'page_size' => 100,
    'page_number' => 1,
]);

// Get product details
$client->Product->getProductDetail($productId);

// Get product categories
$client->Product->getCategories();
```

### Rate Limiting

- TikTok API has rate limits per endpoint
- Implement backoff strategy
- Log all API calls for debugging

---

## Implementation Order

### Step 1: Database & Models (Foundation)
1. Create migration for `pending_platform_products`
2. Create `PendingPlatformProduct` model
3. Update `PlatformAccount` model with product sync fields

### Step 2: Services (Core Logic)
4. Create `ProductMatchingService`
5. Create `TikTokProductSyncService`
6. Create `SyncTikTokProducts` job

### Step 3: UI Components
7. Add Products tab to account show page
8. Create pending products review page
9. Create product linking modal
10. Create product mapping management page

### Step 4: Testing & Refinement
11. Write feature tests
12. Test with sandbox data
13. Refine matching algorithms

---

## Matching Algorithm Details

### Confidence Levels

| Match Type | Confidence | Auto-Link? |
|------------|------------|------------|
| SKU exact match | 100% | Yes |
| Barcode exact match | 100% | Yes |
| Existing mapping | 100% | Yes |
| Name 95%+ similar + same price | 90% | Suggest |
| Name 90%+ similar | 80% | Suggest |
| Name 80%+ similar | 70% | Suggest |
| Below 80% similarity | N/A | No suggestion |

### Name Similarity Algorithm

```php
public function calculateNameSimilarity(string $name1, string $name2): float
{
    // Normalize names
    $normalized1 = $this->normalizeProductName($name1);
    $normalized2 = $this->normalizeProductName($name2);

    // Use combination of algorithms for better accuracy
    $levenshtein = 1 - (levenshtein($normalized1, $normalized2) / max(strlen($normalized1), strlen($normalized2)));
    $similar = similar_text($normalized1, $normalized2, $percent);

    // Weight: 60% similar_text, 40% levenshtein
    return ($percent / 100 * 0.6) + ($levenshtein * 0.4);
}

public function normalizeProductName(string $name): string
{
    $name = strtolower($name);
    $name = preg_replace('/[^a-z0-9\s]/', '', $name);
    $name = preg_replace('/\s+/', ' ', $name);
    return trim($name);
}
```

---

## Configuration

### Environment Variables

```env
# Product sync settings
TIKTOK_PRODUCT_SYNC_BATCH_SIZE=50
TIKTOK_PRODUCT_AUTO_LINK_THRESHOLD=100
TIKTOK_PRODUCT_SUGGEST_THRESHOLD=70
```

### Config File Addition

```php
// config/tiktok.php
return [
    // ... existing config

    'product_sync' => [
        'batch_size' => env('TIKTOK_PRODUCT_SYNC_BATCH_SIZE', 50),
        'auto_link_threshold' => env('TIKTOK_PRODUCT_AUTO_LINK_THRESHOLD', 100),
        'suggest_threshold' => env('TIKTOK_PRODUCT_SUGGEST_THRESHOLD', 70),
    ],
];
```

---

## User Flow

### Flow 1: Initial Product Sync

1. User navigates to TikTok account page
2. Clicks "Products" tab
3. Clicks "Sync Products from TikTok"
4. Progress modal shows sync progress
5. After sync:
   - Shows count of auto-linked products
   - Shows count of products pending review
   - Button to review pending products

### Flow 2: Review Pending Products

1. User clicks "Review Pending Products"
2. Sees list of unmatched TikTok products
3. For each product:
   - If suggestion exists, can accept or override
   - Can search for internal product to link
   - Can create new internal product
   - Can ignore
4. Changes save immediately

### Flow 3: Manual Product Linking

1. User finds a product they want to link
2. Clicks "Link to Internal Product"
3. Search modal opens
4. User searches and selects internal product
5. Mapping is created
6. Product removed from pending list

---

## Success Metrics

- **Auto-link rate**: Target 60%+ of products auto-linked
- **Review queue size**: Should decrease over time as mappings build up
- **Sync time**: < 2 minutes for 500 products
- **Match accuracy**: < 5% incorrect auto-links

---

*Document created: January 2026*
*Ready for implementation*
