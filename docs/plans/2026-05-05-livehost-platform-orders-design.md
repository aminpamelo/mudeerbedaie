# Live Host Platform Orders — Design

**Date:** 2026-05-05
**Owner:** @aminpamelo
**Status:** Approved, ready for implementation

## Problem

Live host commission and payroll currently depend on two manual TikTok xlsx
imports at `/livehost/tiktok-imports`:

1. **Live Analysis** — per-session GMV/viewer stats. Sets
   `LiveSession.gmv_amount`, which `CommissionCalculator` reads.
2. **All Orders** (`order_list`) — line-by-line order export. Stored in
   `tiktok_orders`. Used by `OrderRefundReconciler` to subtract refunds /
   cancellations from session GMV via `LiveSessionGmvAdjustment` rows.

The "All Orders" import is wasted work: a TikTok Shop API integration
already exists (`TikTokOrderSyncService`) and auto-syncs every order into
`product_orders` (`source = 'tiktok_shop'`). Operators are uploading the
same data twice.

## Goal

- Treat `product_orders` as the single source of truth for TikTok Shop
  order data.
- Stop accepting the `order_list` xlsx upload.
- Surface platform orders inside the Live Host Desk so PICs can see what
  rolls into commission/refund calculations.
- Keep manual xlsx upload only for Live Analysis (no API equivalent).

## Non-goals

- Changing the commission formula in `CommissionCalculator`.
- Replacing `LiveSession.gmv_amount` with a sum of `ProductOrder` totals.
  TikTok's "live-attributed GMV" uses their own attribution that we can't
  reproduce from raw orders, so Live Analysis stays as the GMV source.
- Touching `/admin/product-orders` (Volt). Live Host PIC gets its own
  Inertia React surface.
- Migrating or deleting the legacy `tiktok_orders` table. Past imports
  remain visible read-only.

## Solution overview

Eight pieces, no surprises:

1. **DB:** add `matched_live_session_id` (nullable FK) to `product_orders`.
2. **Auto-matcher:** new action that maps a ProductOrder to its
   LiveSession by `platform_account_id` + `paid_time ∈ session window`.
3. **Sync hook:** `TikTokOrderSyncService` calls the matcher after each
   order create/update.
4. **Backfill:** artisan command tags existing `tiktok_shop` orders.
5. **Refund reconciler rewrite:** `OrderRefundReconciler` reads
   `product_orders` instead of `tiktok_orders`. Same output shape.
6. **New page:** `/livehost/orders` — Inertia React list of
   `tiktok_shop`-sourced ProductOrder rows with filters and matched
   session column.
7. **Remove order_list import:** drop from Create form, reject in
   controller. Index page keeps showing past imports read-only.
8. **Sidebar nav:** add "Orders" under RECORDS group in `LiveHostLayout`.

## Architecture

### 1. Schema change

```
product_orders
  + matched_live_session_id  unsignedBigInteger NULLABLE
                             FK → live_sessions.id ON DELETE SET NULL
  + INDEX (platform_account_id, matched_live_session_id)
```

Migration must support both MySQL and SQLite (per CLAUDE.md). For SQLite,
use `Schema::table->foreign(...)->nullable()` directly. For MySQL we'll
add the column + index normally.

### 2. Auto-matcher

`App\Actions\LiveHost\MatchProductOrderToLiveSession`

```php
public function handle(ProductOrder $order): ?int
```

Logic:
- Return `null` if `source !== 'tiktok_shop'` or `platform_account_id`
  is null.
- Use `paid_time` if set, else fall back to `created_time` from the
  order's metadata, else `created_at`.
- Find first `LiveSession` where:
  - `platform_account_id == order.platform_account_id`
  - `actual_start_at <= reference_time <= COALESCE(actual_end_at, actual_start_at + 12h)`
  - `status` is one of `ongoing|completed|missed` (skip cancelled)
- Persist the matched id (or null) and return it.

This mirrors the matching logic already used by
`LiveAnalysisXlsxParser` / `OrderListXlsxParser` to keep behavior
consistent across paths.

### 3. Sync hook

In `TikTokOrderSyncService::syncOrder()`, after each `ProductOrder::create`
or update, dispatch to `MatchProductOrderToLiveSession`. Done synchronously
(it's a single indexed query — cheap).

### 4. Backfill command

`php artisan livehost:match-product-orders {--since=}`

- Iterates all `product_orders` where `source = 'tiktok_shop'` and
  `matched_live_session_id IS NULL`.
- Optional `--since` filter on `created_at`.
- Calls the matcher per row.
- Logs match rate.

### 5. Refund reconciler rewrite

`App\Services\LiveHost\Tiktok\OrderRefundReconciler`

Current source query:
```php
TiktokOrder::query()
    ->where('import_id', $import->id)
    ->whereNotNull('matched_live_session_id')
```

Becomes:
```php
ProductOrder::query()
    ->where('source', 'tiktok_shop')
    ->whereNotNull('matched_live_session_id')
    ->whereBetween('paid_time', [$import->period_start, $import->period_end])
```

The reconciler is invoked when a Live Analysis import is applied — the
period is taken from the import to scope the work. Refund-amount
calculation is reused: refund/cancel statuses on `ProductOrder` map to
the same logic (`order_status`, refund amount field — verify exact
column names during implementation).

### 6. New page — `/livehost/orders`

**Route:**
```
GET /livehost/orders → LiveHost\PlatformOrderController@index
```
Lives in the existing admin/admin_livehost middleware group.

**Controller responsibilities:**
- Eager-load `platformAccount`, `matchedLiveSession.liveHost`, `items`.
- Filter to `source = 'tiktok_shop'`.
- Accept query params: `shop`, `status`, `payment_status`, `date_from`,
  `date_to`, `search`, `unmatched_only`.
- Paginate (25/page, configurable).
- Return Inertia view `LiveHost/Orders/Index` with rows + filter options.

**React page:** `resources/js/livehost/pages/orders/Index.jsx`

Layout reuses `LiveHostLayout` + `TopBar` (matches existing pages like
`tiktok-imports/Index.jsx`). Table columns:

| Order # | Shop | Customer (masked) | Items | Total | Status | Payment | Matched session | Paid at |

- "Matched session" links to `/livehost/sessions/{id}` when set, otherwise
  shows "—" with a tooltip.
- Click row → opens existing admin order detail in a new tab
  (`/admin/product-orders/{id}`). Avoids rebuilding the order detail
  surface in v1.
- Header summary cards (counts only): Total, Matched, Unmatched,
  Refunded.
- "Re-run matching" button → POST `/livehost/orders/match` (admin-only)
  triggers the backfill command via a queued job.

### 7. Remove order_list import

- `resources/js/livehost/pages/tiktok-imports/Create.jsx`:
  - Drop the `order_list` entry from `REPORT_TYPES`.
  - With one type left, replace radio with a static label or hidden field.
- `App\Http\Controllers\LiveHost\TiktokReportImportController::store`:
  - Validation: `report_type` must equal `live_analysis`.
  - Reject other values with 422.
- Index/Show pages keep the `imp.report_type === 'live_analysis'` and
  `=== 'order_list'` branches — past `order_list` imports remain visible.

### 8. Sidebar nav

`resources/js/livehost/layouts/LiveHostLayout.jsx` — add an "Orders" item
under the RECORDS group, between "Live Sessions" and "Commission". Icon:
`ShoppingBag` from lucide-react. Badge shows unmatched count.

## Data flow (after change)

```
TikTok Shop API
  └─► TikTokOrderSyncService
       ├─► product_orders (create/update)
       └─► MatchProductOrderToLiveSession
            └─► sets product_orders.matched_live_session_id

PIC uploads Live Analysis xlsx
  └─► TiktokReportImportController@apply
       ├─► Updates LiveSession.gmv_amount (existing)
       └─► OrderRefundReconciler (rewritten)
            └─► reads product_orders WHERE matched_live_session_id IS NOT NULL
                 └─► proposes LiveSessionGmvAdjustment for refunded orders
```

## Edge cases

- **Order paid before session start / after end** → unmatched; visible
  in the new page's "Unmatched" filter; PIC can decide if a session
  needs adjustment.
- **Multiple sessions on same shop overlap** → impossible by current
  scheduling rules, but matcher picks the earliest match deterministically.
- **Order arrives via webhook before the session is marked
  `actual_start_at`** → matcher returns null. The hook re-runs on every
  update from TikTok (e.g. status changes), so it'll pick up later. We
  also re-match on `actual_start_at` write (LiveSession observer hook —
  optional in v2 if needed).
- **Refund happens after the import period** → out of scope for the
  current import-period-bound reconciler. Same as today.
- **Soft-deleted ProductOrder** → excluded by default scopes.

## Testing

Pest tests:
- `MatchProductOrderToLiveSessionTest` — in-window, before-window,
  after-window, different-account, no-paid-time-fallback, source filter.
- `OrderRefundReconcilerTest` — adapt existing test to use
  `ProductOrder` factory.
- `LiveHost\PlatformOrderControllerTest` — auth, filter params,
  pagination, unmatched filter.
- Browser test: visit `/livehost/orders`, assert table renders, filter
  by shop, click a row.

Migration test on both MySQL and SQLite (`DB::getDriverName()` branch
verified).

## Rollout

1. Merge migration + matcher + sync hook.
2. Run backfill in production: `php artisan livehost:match-product-orders`.
3. Confirm match rate ≥ expected baseline (compare to legacy
   `tiktok_orders.matched_live_session_id` ratio).
4. Switch reconciler to product_orders source.
5. Remove import option from UI.
6. Communicate to PIC team that "All Orders" upload is gone.

## Risks

- **Matcher disagreement with legacy xlsx:** if the API's `paid_time`
  differs from xlsx `paid_time` we may match different sessions.
  Mitigation: log mismatches during backfill; spot-check before
  switching reconciler.
- **Webhook latency:** orders may not appear in `product_orders` for a
  few minutes after a sale. Live Analysis import typically runs end-of-
  day, so this is fine.
- **Scope creep:** keep order detail out of the new page — link to
  `/admin/product-orders/{id}` for now.
