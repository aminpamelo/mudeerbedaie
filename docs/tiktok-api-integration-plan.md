# TikTok Shop API Integration Plan

## Executive Summary

This document outlines a comprehensive plan to integrate TikTok Shop API capabilities into the Mudeer Bedaie platform management system. The goal is to enable real-time synchronization of orders, products, and inventory with TikTok Shop accounts.

---

## Current State Analysis

### What We Have

1. **Platform Management Infrastructure** ✅
   - `Platform` model with multi-platform support
   - `PlatformAccount` for managing connected accounts
   - `PlatformApiCredential` for secure, encrypted credential storage
   - `PlatformSkuMapping` for product mapping
   - `PlatformWebhook` for webhook management (infrastructure exists)

2. **TikTok Import System** ✅
   - CSV-based import via `TikTokOrderProcessor`
   - Queued job processing (`ProcessTikTokOrderImport`)
   - Smart order updates (preserves existing data)
   - Stock deduction with movement tracking
   - Customer and student auto-creation

3. **Missing Capabilities** ❌
   - **No API-based order sync** - Currently CSV only
   - **No product sync** - Cannot push products to TikTok
   - **No inventory sync** - No real-time stock updates
   - **No webhook receivers** - No real-time event handling
   - **No OAuth implementation** - No TikTok authentication flow

---

## TikTok Shop API Overview

### Authentication
- **Method**: OAuth 2.0
- **Access Token**: Expires in 24 hours
- **Refresh Token**: Expires in 1 year
- **Base URL**: `https://open-api.tiktokglobalshop.com/api`

### Available API Endpoints

| Category | Endpoints | Use Case |
|----------|-----------|----------|
| **Authorization** | Get authorized shops, token management | Connect accounts |
| **Orders** | Get order list, order details, update status | Sync orders |
| **Products** | List, create, update, delete products | Product management |
| **Inventory** | Update stock levels | Stock synchronization |
| **Fulfillment** | Shipping, tracking | Order fulfillment |
| **Webhooks** | Order events, product events | Real-time updates |

### Supported Regions
Malaysia (MY), Singapore (SG), Thailand (TH), Vietnam (VN), Philippines (PH), Indonesia (ID)

---

## Proposed Architecture

### System Components

```
┌─────────────────────────────────────────────────────────────────┐
│                        Admin Dashboard                          │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐          │
│  │ Connect      │  │ Sync         │  │ Product      │          │
│  │ Account      │  │ Dashboard    │  │ Management   │          │
│  └──────┬───────┘  └──────┬───────┘  └──────┬───────┘          │
└─────────┼─────────────────┼─────────────────┼───────────────────┘
          │                 │                 │
          ▼                 ▼                 ▼
┌─────────────────────────────────────────────────────────────────┐
│                     Service Layer                                │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐          │
│  │ TikTokShop   │  │ OrderSync    │  │ ProductSync  │          │
│  │ AuthService  │  │ Service      │  │ Service      │          │
│  └──────────────┘  └──────────────┘  └──────────────┘          │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐          │
│  │ InventorySync│  │ Webhook      │  │ RateLimit    │          │
│  │ Service      │  │ Handler      │  │ Manager      │          │
│  └──────────────┘  └──────────────┘  └──────────────┘          │
└─────────────────────────────────────────────────────────────────┘
          │                 │                 │
          ▼                 ▼                 ▼
┌─────────────────────────────────────────────────────────────────┐
│                    TikTok Shop SDK                              │
│           (ecomphp/tiktokshop-php v2.x)                         │
└─────────────────────────────────────────────────────────────────┘
          │
          ▼
┌─────────────────────────────────────────────────────────────────┐
│              TikTok Shop Open Platform API                      │
└─────────────────────────────────────────────────────────────────┘
```

### Database Schema Enhancements

#### New Tables

1. **`tiktok_api_logs`** - API call logging for debugging
   - `platform_account_id`, `endpoint`, `method`
   - `request_payload`, `response_payload`
   - `status_code`, `error_message`
   - `duration_ms`, `created_at`

2. **`sync_schedules`** - Configurable sync schedules
   - `platform_account_id`, `sync_type`
   - `interval_minutes`, `last_run_at`
   - `is_active`, `settings`

3. **`webhook_events`** - Webhook event log
   - `platform_account_id`, `event_type`
   - `payload`, `processed_at`
   - `status`, `error_message`

#### Modifications to Existing Tables

**`platform_accounts`** - Add fields:
- `tiktok_shop_id` (for shop cipher)
- `api_version` (default: '202309')
- `sync_status` (enum: 'idle', 'syncing', 'error')
- `last_error_at`, `last_error_message`

**`platform_api_credentials`** - Add fields:
- `token_metadata` (JSON: scopes, issued_at, etc.)

---

## Implementation Phases

### Phase 1: Foundation & OAuth (Week 1-2)

#### 1.1 Install TikTok Shop SDK
```bash
composer require ecomphp/tiktokshop-php
```

#### 1.2 Create TikTok Service Classes

**`app/Services/TikTok/TikTokAuthService.php`**
```php
class TikTokAuthService
{
    public function getAuthorizationUrl(string $state): string;
    public function handleCallback(string $code): array;
    public function refreshToken(PlatformAccount $account): bool;
    public function getAuthorizedShops(string $accessToken): array;
}
```

**`app/Services/TikTok/TikTokClientFactory.php`**
```php
class TikTokClientFactory
{
    public function createClient(PlatformAccount $account): Client;
    public function createClientFromCredentials(string $appKey, string $appSecret): Client;
}
```

#### 1.3 OAuth UI Components

- **Connect Account Page**: OAuth flow initiation
- **Callback Handler**: Process OAuth response
- **Account Selector**: Choose shop after auth (multi-shop support)

#### 1.4 Configuration
```php
// config/tiktok.php
return [
    'app_key' => env('TIKTOK_APP_KEY'),
    'app_secret' => env('TIKTOK_APP_SECRET'),
    'redirect_uri' => env('TIKTOK_REDIRECT_URI'),
    'api_version' => env('TIKTOK_API_VERSION', '202309'),
    'sandbox' => env('TIKTOK_SANDBOX', false),
];
```

### Phase 2: Order Synchronization (Week 3-4)

#### 2.1 Order Sync Service

**`app/Services/TikTok/TikTokOrderSyncService.php`**
```php
class TikTokOrderSyncService
{
    public function syncOrders(PlatformAccount $account, ?Carbon $since = null): SyncResult;
    public function syncSingleOrder(PlatformAccount $account, string $orderId): ProductOrder;
    public function getOrderDetails(PlatformAccount $account, string $orderId): array;
    public function updateOrderStatus(PlatformAccount $account, string $orderId, string $status): bool;
    public function uploadTrackingNumber(PlatformAccount $account, string $orderId, string $trackingNumber): bool;
}
```

#### 2.2 Sync Job

**`app/Jobs/SyncTikTokOrders.php`**
```php
class SyncTikTokOrders implements ShouldQueue
{
    public function __construct(
        public PlatformAccount $account,
        public ?Carbon $since = null
    ) {}

    public function handle(TikTokOrderSyncService $service): void;
}
```

#### 2.3 Sync Dashboard Component
- Manual sync trigger button
- Sync history and logs
- Error display and retry
- Progress indicator

### Phase 3: Product Synchronization (Week 5-6)

#### 3.1 Product Sync Service

**`app/Services/TikTok/TikTokProductSyncService.php`**
```php
class TikTokProductSyncService
{
    // Pull products from TikTok
    public function importProducts(PlatformAccount $account): SyncResult;

    // Push products to TikTok
    public function pushProduct(PlatformAccount $account, Product $product): bool;
    public function updateProduct(PlatformAccount $account, PlatformProduct $mapping): bool;
    public function deleteProduct(PlatformAccount $account, PlatformProduct $mapping): bool;

    // Category mapping
    public function getCategories(PlatformAccount $account): array;
    public function getCategoryAttributes(PlatformAccount $account, string $categoryId): array;
}
```

#### 3.2 Product Mapping UI
- Map internal products to TikTok products
- Category selection for new products
- Attribute mapping interface
- Bulk operations support

### Phase 4: Inventory Synchronization (Week 7)

#### 4.1 Inventory Sync Service

**`app/Services/TikTok/TikTokInventorySyncService.php`**
```php
class TikTokInventorySyncService
{
    public function syncInventory(PlatformAccount $account, Product $product): bool;
    public function batchSyncInventory(PlatformAccount $account, Collection $products): SyncResult;
    public function getInventoryLevels(PlatformAccount $account): array;
}
```

#### 4.2 Stock Event Listeners
```php
// Listen for stock changes and sync to TikTok
class SyncStockToTikTok
{
    public function handle(StockLevelChanged $event): void;
}
```

### Phase 5: Webhook Integration (Week 8)

#### 5.1 Webhook Controller

**`app/Http/Controllers/Webhooks/TikTokWebhookController.php`**
```php
class TikTokWebhookController extends Controller
{
    public function handle(Request $request): Response
    {
        // Verify webhook signature
        // Route to appropriate handler
        // Log event
    }
}
```

#### 5.2 Event Handlers
- `ORDER_STATUS_CHANGE` - Update order status
- `PRODUCT_STATUS_CHANGE` - Handle product approval/rejection
- `PACKAGE_UPDATE` - Shipping updates
- `RETURN_REQUEST` - Handle returns

#### 5.3 Webhook Configuration UI
- Webhook URL display
- Event subscription management
- Webhook logs viewer

### Phase 6: Scheduled Sync & Monitoring (Week 9)

#### 6.1 Scheduled Commands
```php
// app/Console/Commands/SyncTikTokOrders.php
$schedule->command('tiktok:sync-orders')->everyFifteenMinutes();
$schedule->command('tiktok:refresh-tokens')->daily();
$schedule->command('tiktok:sync-inventory')->hourly();
```

#### 6.2 Monitoring Dashboard
- Sync status for all accounts
- Error rate tracking
- API usage metrics
- Health checks

---

## Security Considerations

### Credential Security
1. All API credentials stored encrypted in `platform_api_credentials`
2. Never log sensitive data (tokens, secrets)
3. Use Laravel's `Crypt` facade for encryption
4. Automatic token rotation before expiry

### Webhook Security
1. Verify webhook signatures
2. Rate limit webhook endpoints
3. Log all webhook events
4. Implement idempotency

### API Rate Limiting
1. Implement rate limit tracking
2. Queue requests when approaching limits
3. Exponential backoff on errors
4. Per-account rate limit isolation

---

## Testing Strategy

### Unit Tests
- Service class methods
- Data transformation
- Validation logic

### Integration Tests
- OAuth flow (with mocks)
- API response handling
- Webhook processing

### Feature Tests
- End-to-end sync workflows
- UI component interactions
- Error handling scenarios

### Sandbox Testing
- TikTok provides sandbox environment
- Test all API calls before production
- Verify webhook handling

---

## Rollout Plan

### Stage 1: Internal Testing
- Deploy to staging environment
- Connect sandbox TikTok account
- Run through all workflows

### Stage 2: Pilot
- Select 1-2 accounts for pilot
- Monitor closely for issues
- Gather feedback

### Stage 3: Gradual Rollout
- Enable for additional accounts
- Monitor performance metrics
- Address any issues

### Stage 4: Full Release
- Enable for all TikTok Shop accounts
- Document features for users
- Provide training if needed

---

## Decisions Made

Based on user requirements:

1. **Scope**: Complete Integration (Orders + Products + Inventory + Webhooks)
2. **Sync Mode**: Webhooks + Scheduled Sync (real-time with backup)
3. **Credentials**: Need to register on TikTok Partner Center

---

## Step 0: TikTok Partner Center Registration

### Prerequisites
1. Active TikTok Seller Center account
2. Business email address
3. Company/business information

### Registration Steps

1. **Go to TikTok Shop Partner Center**
   - URL: https://partner.tiktokshop.com
   - Sign in with your TikTok business account

2. **Navigate to Developer Section**
   - Go to "Developer" in the Partner Center menu
   - Or directly: https://partner.tiktokshop.com/docv2/page/developer

3. **Create New App**
   - Click "Create App"
   - Fill in required information:
     - App Name: "Mudeer Bedaie Integration"
     - App Description: "E-commerce management system integration"
     - Category: Select "E-commerce/Order Management"

4. **Configure App Settings**
   - **Redirect URI**: `https://mudeerbedaie.test/tiktok/callback` (development)
   - **Production URI**: `https://yourdomain.com/tiktok/callback`
   - **Webhook URL**: `https://yourdomain.com/webhooks/tiktok`

5. **Request Permissions/Scopes**
   Required scopes for full integration:
   - `product.read` - Read product information
   - `product.write` - Create/update products
   - `order.read` - Read order information
   - `order.write` - Update order status
   - `inventory.read` - Read inventory levels
   - `inventory.write` - Update inventory
   - `fulfillment.read` - Read fulfillment info
   - `fulfillment.write` - Update shipping/tracking

6. **Submit for Review**
   - TikTok reviews apps before granting production access
   - Sandbox access is typically immediate

7. **Obtain Credentials**
   After approval, you'll receive:
   - **App Key** (Client ID)
   - **App Secret** (Client Secret)
   - **Service ID**

### Important Notes
- Sandbox environment is available for testing
- Keep credentials secure and never commit to git
- Token refresh must be implemented to maintain access

---

## Dependencies & Requirements

### Package Dependencies
```json
{
    "require": {
        "ecomphp/tiktokshop-php": "^2.6"
    }
}
```

### Environment Variables
```env
TIKTOK_APP_KEY=your_app_key
TIKTOK_APP_SECRET=your_app_secret
TIKTOK_REDIRECT_URI=https://yourdomain.com/tiktok/callback
TIKTOK_API_VERSION=202309
TIKTOK_SANDBOX=false
```

### TikTok Partner Center Setup
1. Create app in TikTok Partner Center
2. Request necessary permissions/scopes
3. Configure OAuth redirect URI
4. Set up webhook endpoints (optional)
5. Pass TikTok's app review (if required)

---

## Estimated Effort

| Phase | Description | Estimated Time |
|-------|-------------|----------------|
| 1 | Foundation & OAuth | 1-2 weeks |
| 2 | Order Synchronization | 1-2 weeks |
| 3 | Product Synchronization | 1-2 weeks |
| 4 | Inventory Synchronization | 1 week |
| 5 | Webhook Integration | 1 week |
| 6 | Scheduled Sync & Monitoring | 1 week |
| - | Testing & QA | 1-2 weeks |
| **Total** | | **7-11 weeks** |

---

## References

- [TikTok Shop Partner Center](https://partner.tiktokshop.com/docv2/page/seller-api-overview)
- [TikTok Developers](https://developers.tiktok.com)
- [TikTok Shop PHP SDK](https://github.com/EcomPHP/tiktokshop-php)
- [TikTok API Authorization](https://developers.tiktok.com/doc/oauth-user-access-token-management)

---

*Document created: January 2026*
*Last updated: January 2026*
