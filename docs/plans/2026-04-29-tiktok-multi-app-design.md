# TikTok Shop Multi-App Architecture — Design

**Date:** 2026-04-29
**Status:** Approved, ready for implementation plan

## Problem

TikTok Shop's Partner Center categorizes apps and gates scopes by category:

- **Multi-Channel Management** → orders, products, fulfillment, inventory
- **Analytics & Reporting** → shop performance, video analytics, product analytics
- **Affiliate**, **Customer Service**, etc. each expose their own scope sets

Our current integration registers a single TikTok app (`aminpamelo`, Multi-Channel category). The OAuth token it issues cannot call the `Analytics` endpoints — TikTok rejects them as out-of-scope. This is why the Analytics tab on the account detail page shows no data despite the sync command running successfully on the Multi-Channel side.

The shop owner already has a second app (`Pamelo Marketing & Analytics`) registered in the Analytics & Reporting category, currently in Draft. The system has no way to use it.

## Goal

Support multiple TikTok apps per platform account, route API calls to the right app based on scope category, and ship Analytics sync as the first beneficiary. Architecture must be generic enough that Affiliate / Customer Service / future categories drop in without schema changes.

## Architecture

### Data model

**New table: `platform_apps`**

| column | type | notes |
|---|---|---|
| `id` | bigint PK | |
| `platform_id` | FK → platforms | |
| `slug` | string unique-per-platform | e.g. `tiktok-multi-channel`, `tiktok-analytics-reporting` |
| `name` | string | display name |
| `category` | string | `multi_channel`, `analytics_reporting`, `affiliate`, `customer_service` |
| `app_key` | string | |
| `encrypted_app_secret` | text | encrypted at rest |
| `redirect_uri` | string nullable | per-app override |
| `scopes` | json | informational |
| `is_active` | bool | toggle without deletion |
| `metadata` | json | partner center notes |

Unique constraint: `(platform_id, category)` — one active app per category per platform.

**Modify `platform_api_credentials`:** add `platform_app_id` FK (nullable initially for backfill). Existing uniqueness constraints are extended to include `platform_app_id` so a single account can hold multiple active credentials — one per app.

### Routing

`TikTokClientFactory::createClientForAccount($account, string $category)` looks up:

1. The active `PlatformApp` for `(account.platform_id, $category)` → app_key + decrypted app_secret
2. The active `PlatformApiCredential` for `(account.id, platformApp.id)` → access token + shop_cipher

If either is missing, throw `MissingPlatformAppConnectionException($account, $category)` — caught at controller/job layer and surfaced as a UI banner with a "Connect [Category]" CTA.

Sync services declare their required category via a class constant:

```php
class TikTokAnalyticsSyncService {
    protected const REQUIRED_CATEGORY = 'analytics_reporting';
}
```

| Service | Required category |
|---|---|
| `TikTokOrderSyncService` | `multi_channel` |
| `TikTokProductSyncService` | `multi_channel` |
| `TikTokFinanceSyncService` | `multi_channel` *(today)* |
| `TikTokAffiliateSyncService` | `multi_channel` *(today)* |
| `TikTokAnalyticsSyncService` | `analytics_reporting` |

### OAuth flow

OAuth state payload extended:

```php
$payload = json_encode([
    'user_id' => $userId,
    'link_account_id' => $linkAccountId,
    'platform_app_id' => $appId,
]);
```

`TikTokAuthService::getAuthorizationUrl()`, `handleCallback()`, `refreshToken()` all accept a `PlatformApp` (or `string $category` resolved internally) so the right `app_key`/`app_secret` is used for the OAuth round-trip and code exchange.

The credential row created in the callback gets `platform_app_id` set to the app it was issued for.

Token refresh uses the same `platform_app_id` lookup — failures mark only that credential inactive, not the whole account.

### UX

**App Connections panel** on the platform account detail page (new tab next to Overview/Orders/Products/Analytics/Finance):

```
TikTok Multi-Channel Management   ✅ Connected      [Refresh] [Disconnect]
TikTok Analytics & Reporting      ⚠ Not connected   [Connect]
TikTok Affiliate                  — Optional        [Connect]
```

Each "Connect" kicks off OAuth with `platform_app_id` in state.

**Analytics tab empty state** changes from "Run `php artisan tiktok:sync-analytics`" to:
> "Analytics requires a separate TikTok app connection. [Connect Analytics & Reporting →]"

**Admin: registering apps.** New screen at Platform Management → Platforms → [TikTok Shop] → "Apps" sub-tab. Lists `platform_apps` rows; admin can create/edit/toggle them, paste app_key/app_secret from Partner Center.

## Error handling

| Failure | Behavior |
|---|---|
| Sync called, no credential for category | `MissingPlatformAppConnectionException`; UI banner with Connect CTA |
| Token expired for one app | Existing refresh path scoped by `platform_app_id`; failure marks only that credential inactive |
| App deactivated while credentials exist | Block sync, don't auto-delete; admin can re-enable |
| Runtime scope mismatch from TikTok | Log to `TikTokApiLog`, mark credential as needing re-auth, surface in UI |

## Migration / rollout

Single bundled deploy:

1. Migration: create `platform_apps`, add `platform_app_id` to `platform_api_credentials`
2. Seeder: create the Multi-Channel `platform_apps` row from current env vars; backfill `platform_app_id` on all existing `platform_api_credentials` to point at it
3. Factory + auth service + sync services land in same release
4. Post-deploy, admin uses the new UI to register the Analytics & Reporting app (paste app_key/app_secret from Partner Center)
5. Admin clicks "Connect Analytics & Reporting" on their TikTok Shop account → OAuth flow → analytics sync starts working

Migrations must work on both MySQL + SQLite per project convention (use `DB::getDriverName()` branching where needed).

## Testing

- **Unit:** `TikTokClientFactory::createClientForAccount()` picks the right credential for a category; throws cleanly when missing
- **Feature (Pest):** OAuth callback uses correct app_secret based on `platform_app_id` in state; credential is stored with correct `platform_app_id`
- **Feature:** Each sync service throws `MissingPlatformAppConnectionException` when its required category is not connected; succeeds when it is
- **Feature:** Regression — existing Multi-Channel sync paths (orders, finance, affiliate) pass after migration
- **Browser (Pest 4):** App Connections panel renders status correctly; "Connect Analytics" redirects to OAuth with right `platform_app_id`

## Out of scope

- Migrating Finance/Affiliate to their own app categories — they stay on Multi-Channel until TikTok forces a change
- Building this for other platforms (Shopee, Lazada) — architecture supports it, but no implementation until needed
- Granular scope selection within a category — TikTok controls scope membership per category; we don't subset
