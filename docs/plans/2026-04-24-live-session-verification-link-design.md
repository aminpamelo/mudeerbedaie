# Live Session Verification — Manual Link to TikTok Actual Record

**Date:** 2026-04-24
**Status:** Design approved, ready for implementation plan
**Related:** Live Host module, TikTok Shop integration, Commission/Payroll

## Problem

Live hosts sometimes key the wrong date/time on their scheduled session. When TikTok's "actual" live record arrives (CSV import today; API sync once the `data.shop_analytics.public.read` scope is unblocked), the current `LiveSessionMatcher` auto-matches by `creator_id + actual_start_at ± 30min`. If the host never set `actual_start_at`, or set it incorrectly, the heuristic fails or mis-matches. On Account 4 today: **134 imported TikTok live reports, zero matched.**

GMV drives commission, so the match being wrong is a payroll correctness issue, not a cosmetic one.

## Goal

Admin PIC chooses the TikTok actual record that corresponds to each scheduled session, during verification, explicitly. Verification is blocked until a record is linked. GMV on the session locks to the linked record's `live_attributed_gmv_myr`. Full audit trail of every link/unlink action.

## Non-goals

- Auto-matching improvements — the whole point is to make admin the authority. The old `LiveSessionMatcher` is retired from the critical path.
- Cross-shop linking — a TikTok record can only link to a session in the same `platform_account_id`.
- Custom GMV override — admin cannot type a GMV number; it always comes from the linked record.
- Historical backfill of 134 existing unmatched reports — they get migrated into the new table, admin links them going forward through the same UI.

## Architecture Overview

```
CSV import                 API sync (future, post-scope)
    │                              │
    └──────────────┬───────────────┘
                   ▼
        actual_live_records (new unified table)
                   │
                   │  admin picks from "suggestions" in
                   │  verification modal (same host + same day)
                   ▼
        live_sessions.matched_actual_live_record_id  (unique, nullable FK)
                   │
                   │  on verify-link, GMV locks:
                   │    gmv_amount        = record.live_attributed_gmv_myr
                   │    gmv_source        = 'tiktok_actual'
                   │    gmv_locked_at     = now()
                   │    verification_status = 'verified'
                   ▼
           Commission / Payroll pipeline
```

## Database schema

### New table `actual_live_records`

| Column | Type | Notes |
|---|---|---|
| `id` | bigint pk | |
| `platform_account_id` | fk → platform_accounts | which TikTok Shop |
| `source` | enum | `csv_import` \| `api_sync` |
| `source_record_id` | string, nullable | API: `live_id`. CSV: null (or hash of creator+launched_time for dedup) |
| `import_id` | fk → tiktok_report_imports, nullable | CSV only, traceability |
| `creator_platform_user_id` | string, nullable, indexed | matches `live_host_platform_account.creator_platform_user_id` |
| `creator_handle` | string, nullable | display name |
| `launched_time` | datetime, indexed | when live started |
| `ended_time` | datetime, nullable | |
| `duration_seconds` | integer, nullable | |
| `gmv_myr` | decimal(15,2) | total GMV during live window |
| `live_attributed_gmv_myr` | decimal(15,2) | GMV attributed to live — what locks onto session |
| `viewers`, `views`, `comments`, `shares`, `likes`, `new_followers` | int, nullable | engagement metrics |
| `products_added`, `products_sold`, `items_sold`, `sku_orders` | int, nullable | product metrics |
| `unique_customers`, `avg_price_myr`, `click_to_order_rate`, `ctr` | mixed, nullable | |
| `raw_json` | json | original payload (CSV row or API response) |
| `created_at`, `updated_at` | timestamps | |

**Indexes:**
- `(platform_account_id, creator_platform_user_id, launched_time)` — the candidate-search index
- Unique `(source, source_record_id) WHERE source_record_id IS NOT NULL` — API idempotency

### Addition to `live_sessions`

| Column | Type | Notes |
|---|---|---|
| `matched_actual_live_record_id` | fk → actual_live_records, nullable, **unique** | 1:1 enforcement — one TikTok record attaches to at most one session |

Existing `gmv_amount`, `gmv_source`, `gmv_locked_at`, `verification_status`, `verified_by`, `verified_at` columns reused.

### New table `live_session_verification_events` (audit trail)

| Column | Notes |
|---|---|
| `id` | pk |
| `live_session_id` | fk |
| `actual_live_record_id` | fk, nullable (null on reject/unverify) |
| `action` | enum: `verify_link`, `unverify`, `reject`, `link_changed` |
| `user_id` | fk → users |
| `gmv_snapshot` | decimal — GMV at the moment of action |
| `notes` | text, nullable |
| `created_at` | |

Append-only. No `updated_at`, no soft deletes.

### Migration from `tiktok_live_reports`

1. Create `actual_live_records` + `matched_actual_live_record_id` + `live_session_verification_events`.
2. Data migration: copy all 134 existing `tiktok_live_reports` rows → `actual_live_records` with `source='csv_import'`, `import_id` preserved, all metric columns mapped 1:1.
3. `tiktok_live_reports` stays in schema as raw import log; not queried by verification UI going forward. Eligible for removal in a future cleanup after safety period.
4. MySQL + SQLite compatible — only `create` operations, no enum alterations, no renames.

## Candidate search

Given a `LiveSession`:

```sql
SELECT * FROM actual_live_records
WHERE platform_account_id = :session_platform_account_id
  AND creator_platform_user_id = :session_creator_id
  AND DATE(launched_time AT TIME ZONE 'Asia/Kuala_Lumpur')
    = DATE(:scheduled_start_at AT TIME ZONE 'Asia/Kuala_Lumpur')
  AND id NOT IN (
    SELECT matched_actual_live_record_id
    FROM live_sessions
    WHERE matched_actual_live_record_id IS NOT NULL
      AND id != :current_session_id
  )
ORDER BY ABS(strftime('%s', launched_time) - strftime('%s', :scheduled_start_at)) ASC
LIMIT 20;
```

(SQLite syntax above; Eloquent builder expresses the same idea DB-agnostically.)

Ranking: closest `launched_time` to `scheduled_start_at` first. Top candidate gets a "Suggested" badge in the UI; admin still clicks to confirm.

### Edge cases

| Situation | Handling |
|---|---|
| Session has no `live_host_platform_account_id` | Modal: "Assign a host first, then reopen." No query runs. |
| Host pivot has no `creator_platform_user_id` | Modal: "Host profile missing TikTok creator ID — set it on host profile." |
| Same-day returns empty | "[Show ±3 days]" button widens window. |
| Wider search still empty | "No TikTok records found — upload CSV or wait for API sync, then retry." Cannot verify (strict gate). |

## API surface

### `POST /livehost/sessions/{session}/verify-link` (new)

Body: `{ actual_live_record_id: integer }`

Validates:
1. Session `verification_status === 'pending'`
2. Record exists + same `platform_account_id` as session
3. Record not already linked to another session (pre-check + unique index safety net)

Transaction:
```php
$session->update([
    'matched_actual_live_record_id' => $record->id,
    'gmv_amount'          => $record->live_attributed_gmv_myr,
    'gmv_source'          => 'tiktok_actual',
    'gmv_locked_at'       => now(),
    'verification_status' => 'verified',
    'verified_by'         => $request->user()->id,
    'verified_at'         => now(),
]);
LiveSessionVerificationEvent::create([
    'live_session_id'        => $session->id,
    'actual_live_record_id'  => $record->id,
    'action'                 => 'verify_link',
    'user_id'                => $request->user()->id,
    'gmv_snapshot'           => $record->live_attributed_gmv_myr,
]);
```

### `POST /livehost/sessions/{session}/verify` (existing, tightened)

- Body `{ verification_status: 'rejected' }` — allowed without a linked record (rejection = session not valid).
- Body `{ verification_status: 'verified' }` — **returns 422** with message "Use verify-link with an actual_live_record_id." Closes the back door that the UI already disables.
- Body `{ verification_status: 'pending' }` — unverify path (below).

### `POST /livehost/sessions/{session}/verify` with `{ status: 'pending' }` (unverify)

Transaction:
```php
$session->update([
    'matched_actual_live_record_id' => null,
    'gmv_amount'          => 0,
    'gmv_source'          => null,
    'gmv_locked_at'       => null,
    'verification_status' => 'pending',
    'verified_by'         => null,
    'verified_at'         => null,
]);
LiveSessionVerificationEvent::create([
    'live_session_id'       => $session->id,
    'actual_live_record_id' => null,
    'action'                => 'unverify',
    'user_id'               => $request->user()->id,
    'gmv_snapshot'          => 0,
]);
```

## State machine

```
    ┌───────────┐      verify-link        ┌──────────┐
    │  pending  │ ──────────────────────▶ │ verified │
    │ (no link) │ ◀────────────────────── │ (linked) │
    └────┬──────┘        unverify         └──────────┘
         │
         │ verify{status:rejected}
         ▼
    ┌──────────┐
    │ rejected │
    │ (no link)│
    └──────────┘
```

Rejected sessions do not appear as candidates-linkable. To re-verify a rejected session, admin flips back to `pending` (existing pattern).

## Error handling

| Risk | Mitigation |
|---|---|
| Two admins link same record simultaneously | Unique index on `live_sessions.matched_actual_live_record_id`; controller catches `QueryException` → 409 "Record just linked elsewhere — refresh." |
| Candidate stale between modal load and submit | Pre-check + unique index. Same 409. |
| Record deleted after candidate load | `findOrFail` → 404. |
| Duplicate import (re-upload same CSV / re-sync API) | API path: unique `(source, source_record_id)`. CSV path: dedup by `(import_id, creator_platform_user_id, launched_time)` at ingest, skip + log "already imported." |
| Re-imported record changes GMV on verified session | No overwrite. `gmv_locked_at IS NOT NULL` guard at ingest. Admin must `unverify` → `verify-link` to adopt the new value. |
| Admin edits `scheduled_start_at` on verified session | Blocked in controller when `gmv_locked_at` is set. Existing pattern. |

## Auto-matcher disposition

The existing `LiveSessionMatcher` service (`app/Services/LiveHost/Tiktok/LiveSessionMatcher.php`) continues to populate `tiktok_live_reports.matched_live_session_id` for raw-import audit purposes, but that column no longer drives GMV or verification. New `matched_actual_live_record_id` on `live_sessions` is **admin-driven only** — no heuristic auto-fill. Rationale: admin oversight is the entire point of this feature; auto-linking undermines it.

Future cleanup: once verification UI is live for 1 release cycle and commission correctness confirmed, `LiveSessionMatcher` + its column can be deleted.

## Testing

Pest feature tests under `tests/Feature/LiveHost/`:

1. `candidate_search_returns_same_host_same_day_records_excluding_already_linked`
2. `verify_link_atomically_sets_match_gmv_and_verified_fields`
3. `verify_link_rejects_when_session_not_pending`
4. `verify_link_rejects_when_record_platform_account_mismatches_session`
5. `verify_link_returns_409_when_record_already_linked_to_another_session`
6. `verify_endpoint_returns_422_when_status_is_verified_without_link`
7. `verify_endpoint_allows_reject_without_link`
8. `unverify_clears_all_linked_fields_and_returns_status_to_pending`
9. `unverify_does_not_delete_actual_live_record`
10. `csv_import_creates_actual_live_records_with_source_csv_import`
11. `verification_event_row_written_on_each_action`

Plus one unit test: `candidate_search_uses_asia_kuala_lumpur_timezone_for_date_boundary` (a live launched at 23:45 KL time stays on that day, not the next UTC day).

## Future considerations

- **API sync ingest** — once `data.shop_analytics.public.read` scope unblocks, a new job writes `GET /analytics/202509/shop_lives/performance` rows into `actual_live_records` with `source='api_sync'`. No verification-UI changes needed.
- **Per-minute breakdown endpoint** — TikTok also exposes `shop_lives/performance_per_minutes`. If ever needed, goes into a separate table keyed by `actual_live_record_id` (1:N).
- **Commission re-computation on unverify** — if payroll has already consumed the GMV, unverifying needs to reverse the commission entry. Out of scope for this design; address when payroll pipeline integrates with verification.

## Out of scope

- Payroll reversal flow on unverify (noted above).
- Bulk verify-link (admin linking many sessions at once).
- Cross-market / cross-shop linking.
- CSV re-import conflict resolution UI (for now, dedup silently at ingest).

## Acceptance criteria

1. Admin opens a session with matching TikTok data — sees candidate list in modal, top candidate badged "Suggested", clicks "Link & verify", session flips to verified with correct GMV.
2. Admin opens a session with no matching data — sees guidance message, cannot verify.
3. Admin rejects a session without picking a record — works (no data required to reject).
4. Admin unverifies a verified session — returns to pending, GMV cleared, record unlinked, can be re-picked.
5. Trying to link the same TikTok record to two sessions — second attempt returns 409.
6. All 134 existing `tiktok_live_reports` rows appear as candidates after migration.
7. Every action writes a `live_session_verification_events` row with correct user + GMV snapshot.
