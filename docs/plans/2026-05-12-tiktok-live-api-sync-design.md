# TikTok LIVE Performance — API Auto-Sync Design

**Date:** 2026-05-12
**Status:** Approved — proceed to implementation plan
**Owner:** Live Host Desk module

## Problem

Per-LIVE session data (host, GMV, viewers, products sold, duration, etc.) currently enters the system only via manual CSV upload at `/livehost/tiktok-imports`. Admins download "Live Analysis" CSVs from TikTok Seller Center and upload them on a cadence — a chore that delays commission calculation and is the only manual step left in an otherwise automated analytics pipeline.

TikTok Shop now exposes two endpoints that return the same data:

- `GET /analytics/202508/shop_lives/performance` — per-LIVE list (paginated)
- `GET /analytics/202508/shop_lives/overview_performance` — aggregate LIVE metrics

Adding these endpoints removes the manual step for the common case while keeping CSV as a safety net.

## Scope

In scope:

- New service + queued job + scheduled cron that pulls per-LIVE rows from the TikTok Shop API and upserts into `tiktok_live_reports`.
- Schema additions to dedupe API rows by TikTok's stream id and scope them to a `PlatformAccount`.
- Reusing the existing `App\Services\LiveHost\Tiktok\LiveSessionMatcher` for row → session matching (no extraction needed — it's already a service).
- Creating a parallel `ActualLiveRecord` row for each new `TiktokLiveReport`, mirroring what `ProcessTiktokImportJob` does for CSV rows so host scorecards and commission flows behave identically.
- Minimal UI: a "Sync LIVE Performance" button on the Platform Account → Analytics tab, plus a one-line notice on the TikTok Imports page.

Out of scope:

- Replacing the CSV upload workflow (kept as fallback).
- Changing the commission calculation logic.
- Building a separate "LIVEs" admin page (data continues to surface through existing Live Sessions / Live Host pages).
- Backfilling years of historical LIVEs from the API (the API does not retain unbounded history; CSV remains the only path for older data).

## Decisions

| # | Decision | Why |
|---|---|---|
| 1 | API is primary; CSV remains as fallback | Survives TikTok blocking the API for a region, lets us backfill periods the API can't reach, preserves admin's ability to upload hand-corrected data. |
| 2 | Scheduled (nightly per active account) + manual "Sync Now" button | Mirrors the existing `SyncTikTokAnalytics` pattern admins are already familiar with. |
| 3 | 30-day window, upsert by `tiktok_live_id`, preserve `matched_live_session_id` on update | Same window the rest of analytics uses. Idempotent re-runs. Commission decisions you've already made on a row are never wiped by a re-sync. |
| 4 | Extend `EcomPHP\TiktokShop\Resources\Analytics` via a small subclass | Reuse the SDK's signing, error handling, and version routing. Drops away cleanly if upstream adds these methods later. |

## Architecture

```
┌──────────────────────────────────────────────────────────────────────┐
│                       Cron / Manual Trigger                          │
│   schedule:run nightly per active TikTok Shop account                │
│   OR  Admin clicks "Sync LIVE Performance" on Analytics tab          │
└──────────────────────┬───────────────────────────────────────────────┘
                       ▼
        ┌──────────────────────────────────┐
        │   App\Jobs\SyncTikTokLive        │
        │   (queued, ShouldQueue, 3 tries) │
        └──────────────┬───────────────────┘
                       ▼
   ┌─────────────────────────────────────────────────────────┐
   │   App\Services\TikTok\TikTokLiveSyncService             │
   │   - syncLivePerformance(PlatformAccount, ?fromDate)     │
   │   - paginates next_page_token, upserts by tiktok_live_id│
   │   - runs MatchingService over fresh rows                │
   └──┬────────────────────────────────────────┬─────────────┘
      │                                        │
      ▼                                        ▼
┌──────────────────────────────┐   ┌─────────────────────────────────┐
│ App\Services\TikTok\Sdk\     │   │ App\Services\LiveHost\          │
│ AnalyticsExtended            │   │ LiveSessionMatcher (existing)         │
│ (extends SDK's Analytics)    │   │ (extracted from existing        │
│ + getShopLivePerformanceList │   │  TiktokReportImportController)  │
│ + getShopLivePerformance     │   │  - resolve LiveHost by creator  │
│   Overview                   │   │  - link to LiveSession by time  │
│ Pinned to version 202508     │   │                                 │
└──────────────────────────────┘   └─────────────────────────────────┘
                       │
                       ▼
              ┌─────────────────────┐
              │  tiktok_live_reports│
              │  (existing table +  │
              │   new columns)      │
              └─────────────────────┘
```

### Why two services, not one

`TikTokAnalyticsSyncService` stores **aggregate shop snapshots**; `TikTokLiveSyncService` stores **per-session detail rows**. The persistence shape, retry semantics, and matching logic differ enough that combining them would force conditionals everywhere. Keeping them parallel keeps each focused.

### Why extend the SDK's `Analytics` resource

The SDK creates resources through `Client::__get($name)` which checks against a hardcoded `self::resources` allowlist. We sidestep that by instantiating `AnalyticsExtended` directly and wiring it to the same `Client::httpClient()` (which already carries auth + signing middleware). Roughly 20 lines of glue; no fork, no vendor patch.

## Schema changes

Additions to `tiktok_live_reports`:

```
tiktok_live_id          string, nullable                        ← API's `id` field (dedup key)
platform_account_id     foreignId, nullable, index              ← scopes API rows to a shop
source                  enum('csv', 'api'), default 'csv'       ← provenance
synced_at               timestamp, nullable                     ← last API touch
UNIQUE(platform_account_id, tiktok_live_id)
```

**Dual-driver migration:** the project rule requires MySQL + SQLite compatibility. The unique index uses the plain `(platform_account_id, tiktok_live_id)` form on both drivers; both allow multiple rows where both columns are NULL, which preserves legacy CSV rows unchanged.

**Backfill (single migration step):**

- Existing rows: `source = 'csv'`, `tiktok_live_id = null`, `synced_at = null`.
- Optional fill of `platform_account_id` from `matched_live_session_id → live_sessions.platform_account_id` (only for already-matched rows). Done as a one-shot `DB::table()->update()` inside the same migration's `up()`.

**Down:** drops the four columns and the index. Symmetric on both drivers.

## Data flow per sync run

```
1. Job picks up
   App\Jobs\SyncTikTokLive {account_id, ?fromDate, ?toDate}
   → TikTokLiveSyncService::syncLivePerformance($account, $from, $to)

2. Client setup
   resolveApp($account, CATEGORY_ANALYTICS_REPORTING)
   if (needsTokenRefresh) refreshToken()
   $client = createClientForAccount()
   $client->useVersion('202508')                       ← pinned constant
   $analytics = new AnalyticsExtended()
   $analytics->useHttpClient($client->getHttpClient())
   $analytics->useVersion('202508')

3. Paginated fetch (page_size: 100, max 50 pages)
   loop:
     GET /analytics/202508/shop_lives/performance
       ?start_date_ge=...&end_date_lt=...&page_size=100&page_token=...
     for each live in response.data.live_stream_sessions:
       normalize() → array matching tiktok_live_reports columns
       upsert by (platform_account_id, tiktok_live_id):
         on create: source='api', synced_at=now()
         on update: preserve matched_live_session_id, import_id
                    update metric columns + source='api'
                    + synced_at=now(), raw_row_json=$raw
       never touch rows where source='csv' AND tiktok_live_id IS NULL

4. Match newly upserted rows + write ActualLiveRecord
   for each freshly upserted TiktokLiveReport without matched_live_session_id:
     LiveSessionMatcher->match($report, $account->id)    ← existing service
     on hit: set matched_live_session_id, save
     on miss: leave null (unmatched_count++)
   create a paired ActualLiveRecord row with
     source='api', source_record_id=tiktok_live_id,
     all the same metric columns
   (mirrors what ProcessTiktokImportJob does for CSV rows)

5. Persist sync result on PlatformAccount
   $account->updateSyncStatus('completed', 'live_analytics')
   metadata.last_live_sync_result = {synced, created, updated,
                                     matched, unmatched, pages, duration_ms,
                                     fetched_at}
```

### Normalization rules

- **GMV currency:** API returns `gmv.amount` as a string in the seller's local currency by default. Store into `gmv_myr` only when `gmv.currency === 'MYR'`; otherwise write `null` and rely on `raw_row_json` so reports never silently mix currencies. Same rule for `avg_price.amount` → `avg_price_myr` and `24h_live_gmv.amount` → `live_attributed_gmv_myr`.
- **Timestamps:** `start_time` / `end_time` come as Unix timestamp strings → `Carbon::createFromTimestamp()`. `launched_time = start_time`. `duration_seconds = end_time - start_time`.
- **Creator linkage:** API response includes `username` but no creator UUID. The existing matcher already resolves a host from `creator_nickname` / `creator_display_name`, so we feed `username` into both fields.
- **Field mapping:**

| API field | DB column |
|---|---|
| `id` | `tiktok_live_id` *(new)* |
| `title` | (stored in `raw_row_json` only — no column today) |
| `username` | `creator_nickname` AND `creator_display_name` |
| `start_time` | `launched_time` |
| `end_time - start_time` | `duration_seconds` |
| `gmv.amount` (if MYR) | `gmv_myr` |
| `24h_live_gmv.amount` (if MYR) | `live_attributed_gmv_myr` |
| `products_added` | `products_added` |
| `different_products_sold` | `products_sold` |
| `sku_orders` | `sku_orders` |
| `unit_sold` | `items_sold` |
| `customers` | `unique_customers` |
| `avg_price.amount` (if MYR) | `avg_price_myr` |
| `click_to_order_rate` | `click_to_order_rate` |
| `viewers` | `viewers` |
| `views` | `views` |
| `avg_viewing_duration` | `avg_view_duration_sec` |
| `comments`, `shares`, `likes`, `new_followers` | same names |
| `product_impressions`, `product_clicks` | same names |
| `click_through_rate` | `ctr` |
| `acu`, `pcu` | (stored in `raw_row_json` only — no column today) |

## Error handling

| Failure | Where | Recovery |
|---|---|---|
| Invalid API version | `AnalyticsExtended` | Pinned to `202508` in code constant, not env. One-place bump on TikTok deprecation. |
| Expired access token | SDK signing layer (`TokenException`) | `TikTokAuthService::refreshToken()` → retry once in-job, then surface to job retry. |
| Rate limit (10001) | Service loop | Honor `retry-after`; sleep then retry; outer job backoff covers worst case. |
| Region not enabled for LIVE API | First call returns `not_authorized` | Log once, set `account.metadata.live_api_supported = false`, skip silently on future runs until manually re-enabled. CSV remains available. |
| Duplicate `tiktok_live_id` race | DB unique constraint | Catch `UniqueConstraintViolationException`, re-fetch and update. |
| Partial pagination failure | Service loop | Earlier pages already committed; re-running re-fetches and upserts are idempotent. |

Logging uses `[TikTokLiveSync]` prefix, mirroring `[SyncTikTokAnalytics]`.

## Testing

- **`tests/Feature/Services/TikTok/TikTokLiveSyncServiceTest.php`** (Pest, Feature, fake-client pattern matching `TikTokAnalyticsSyncServiceTest`):
  - upserts a new LIVE row from API response → all fields populated correctly
  - re-sync same LIVE → metrics updated, `matched_live_session_id` preserved
  - paginates over `next_page_token`
  - skips rows where `source='csv' AND tiktok_live_id IS NULL`
  - `not_authorized` short-circuits cleanly and flags the account
  - non-MYR `gmv.currency` leaves `gmv_myr` null, preserves `raw_row_json`
- **`tests/Feature/Services/LiveHost/LiveSessionMatcher (existing)Test.php`** — coverage for the extracted matching service, independent of either ingestion path.
- **`tests/Feature/Jobs/SyncTikTokLiveTest.php`** — job dispatches, retries, failed() hook updates account error state.
- **Migration roundtrip:** run `php artisan migrate` and `php artisan migrate:rollback` against both SQLite and MySQL (manual checklist documented in implementation plan).

## UI surface

Minimal:

1. **Platform Account → Analytics tab** (Volt component): add a "LIVE Performance" panel alongside "Channel Breakdown" — shows aggregate LIVE GMV / sessions / matched-vs-unmatched count from the most recent `last_live_sync_result`. Includes a `Sync Now` button that dispatches `SyncTikTokLive` for that account.
2. **TikTok Imports page** (Inertia React, `/livehost/tiktok-imports`): one-line passive notice at the top:
   > _LIVE performance data now syncs automatically from TikTok. CSV upload remains available for backfilling older periods or accounts where the API is not yet enabled._

No new admin nav entry. No new React pages. No new Volt routes.

## Rollout

Single migration; can deploy in one shot. Sequence:

1. Migration adds columns + index, backfills `source='csv'` on existing rows.
2. Code deploy adds service, job, matcher, command, schedule entry (disabled by default via config flag).
3. Smoke-test the manual button on one TikTok Shop account.
4. Flip the schedule config flag on once smoke passes.
5. Add the passive UI notice to TikTok Imports page.

Rollback: disable the schedule + revert the deploy. The migration's columns are additive and harmless to leave in place.

## Risks and unknowns

- **TikTok LIVE API regional availability** — the changelog says "all markets", but real-world enablement varies. The `not_authorized` handler treats this as a no-op, not a failure, so it degrades cleanly.
- **API rate limits unknown for LIVE endpoints specifically** — TikTok publishes global rate limits but not per-endpoint. Mitigation: the existing per-account 60-rpm setting in config covers all Analytics calls together; the new sync inherits it.
- **`24h_live_gmv` semantics** — TikTok counts GMV attributed to a LIVE for 24 hours after end. CSV exports use the same metric, so swap is direct. Worth verifying once in production by comparing one CSV row against the API equivalent.
- **SDK upstream** — if EcomPHP adds native `getShopLivePerformanceList` later, `AnalyticsExtended` can be deleted and the service points at `Analytics` directly. Migration is trivial.

## Out of scope (future, not blocking)

- A dedicated "LIVE Sessions" report page surfacing the new data outside of host scorecards.
- Auto-creating `LiveSession` records from unmatched API rows (today: leave unmatched, surface count in the sync result).
- A `tiktok_live_id` index on `actual_live_records` for cross-table joins.
- Storing `acu` / `pcu` as first-class columns (currently captured only in `raw_row_json`).
