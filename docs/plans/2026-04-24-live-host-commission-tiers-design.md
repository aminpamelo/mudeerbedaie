# Live Host Commission Tier System — Design

**Date:** 2026-04-24
**Scope:** Live Host Desk (`/livehost/*`, Inertia + React SPA)
**Replaces:** Flat per-platform Performance pay % + flat L1/L2 override % on the host profile.

---

## 1. Goal

Replace the current two flat-percentage controls (Performance pay, Override earnings) on the host detail page with a single **GMV-tier schedule** per host per platform. Each tier row is the single source of truth for a commission split: internal %, L1 override %, L2 override %.

Higher GMV unlocks higher override payouts to uplines, incentivising downline growth. Below the lowest tier's floor, no performance/override commission is generated for that platform that month.

---

## 2. Conceptual Model

For every payroll period, per platform:

1. Sum the host's attributed GMV on that platform.
2. Find the tier row where `min_gmv_myr ≤ monthly_gmv < max_gmv_myr` (null `max_gmv_myr` = open-ended top tier).
3. If no matching tier (GMV below the lowest `min_gmv_myr`): zero performance pay, zero override generated.
4. Otherwise, the matched row pays out three wallets against the same `monthly_gmv`:
   - `internal_percent × gmv` → the host
   - `l1_percent × gmv` → the host's direct upline (`upline_user_id`)
   - `l2_percent × gmv` → the direct upline's upline (2 hops up)

Fixed pay (`base_salary_myr`, `per_live_rate_myr`) is unaffected by tier logic.

---

## 3. Data Model

### New table: `live_host_platform_commission_tiers`

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `user_id` | FK `users` (cascade on delete) | host who owns this schedule |
| `platform_id` | FK `platforms` (cascade on delete) | scope the schedule |
| `tier_number` | unsigned tinyint | 1, 2, 3… ordering + label |
| `min_gmv_myr` | decimal(12,2) | floor, inclusive |
| `max_gmv_myr` | decimal(12,2) nullable | ceiling, exclusive. `null` = open top tier |
| `internal_percent` | decimal(5,2) | paid to host |
| `l1_percent` | decimal(5,2) | paid to direct upline |
| `l2_percent` | decimal(5,2) | paid to L2 upline |
| `effective_from` | date | activation boundary |
| `effective_to` | date nullable | deactivation boundary |
| `is_active` | boolean | convenience flag |
| `created_at`, `updated_at` | timestamps | |

**Indexes:**
- Unique: `(user_id, platform_id, tier_number, effective_from)` — prevents duplicate tier rows in the same effective window.
- Lookup: `(user_id, platform_id, is_active)` — used by the payroll calculator.

**Validation invariants (enforced at app layer + FormRequest):**
- Within one `(user_id, platform_id, effective_from)` group:
  - Tier numbers must be contiguous starting at 1.
  - Ranges must be non-overlapping and ordered ascending by `min_gmv_myr`.
  - Only the highest tier may have `max_gmv_myr = null`.
  - All `min_gmv_myr`, `max_gmv_myr`, `internal_percent`, `l1_percent`, `l2_percent` ≥ 0.
  - Percentages ≤ 100.

### Columns being deprecated

Still kept in the DB for backward compatibility during cutover, read no longer referenced by payroll calc after migration:

- `live_host_platform_commission_rates.commission_rate_percent` — superseded by `internal_percent` in each tier row.
- `live_host_commission_profiles.override_rate_l1_percent` — superseded by `l1_percent` (now tier-dependent, not a single flat host-level number).
- `live_host_commission_profiles.override_rate_l2_percent` — superseded by `l2_percent`.

A follow-up migration drops these columns once all hosts have tier schedules.

### Unchanged

`live_host_commission_profiles` keeps: `base_salary_myr`, `per_live_rate_myr`, `upline_user_id`, `notes`, `effective_from`, `effective_to`, `is_active`.

---

## 4. Migration of Existing Data

For every active `live_host_platform_commission_rates` row (host X, platform Y, rate R%), create one open-ended tier row:

```
tier_number       = 1
min_gmv_myr       = 0.00
max_gmv_myr       = null
internal_percent  = R
l1_percent        = (host's current override_rate_l1_percent, or 0)
l2_percent        = (host's current override_rate_l2_percent, or 0)
effective_from    = the old row's effective_from
effective_to      = the old row's effective_to
is_active         = the old row's is_active
```

This preserves current payroll behavior for already-configured hosts (everyone falls into a single zero-floor tier at their existing rate) until an admin defines a real tier schedule.

---

## 5. Payroll Calculation Integration

Existing `LiveHostPayrollItem` already stores `total_gmv_myr`, `gmv_commission_myr`, `override_l1_myr`, `override_l2_myr`, `net_payout_myr`, and `calculation_breakdown_json`.

### New calculator service: `App\Services\LiveHost\CommissionTierResolver`

Single entry point: `resolveTier(User $host, Platform $platform, float $monthlyGmv, Carbon $asOf): ?CommissionTier`

- Queries active tier rows for `(host, platform)` as of `$asOf`.
- Returns the row where `min_gmv_myr ≤ monthlyGmv < (max_gmv_myr ?? INF)`, or `null` if GMV is below the lowest floor.

### Payroll run flow changes

For each host × platform in a payroll period:

1. Sum `total_gmv_myr` from `live_sessions` for the period.
2. Call `CommissionTierResolver::resolveTier(...)`.
3. If null → set `gmv_commission_myr = 0`, append `{platform_id, monthly_gmv, tier_id: null, reason: 'below_tier_1_floor'}` to breakdown JSON.
4. Else → compute `internal_percent × monthly_gmv` for the host's line, enqueue override amounts against the upline and L2 upline's payroll items (they're processed in the same run).
5. Append `{platform_id, monthly_gmv, tier_id, internal_percent, l1_percent, l2_percent}` to the host's breakdown JSON. Append matching entries to the upline and L2 upline's breakdown JSON under an `overrides_received` key so the audit trail shows which downline tier each override came from.

### Why breakdown-JSON is load-bearing

Tier rows may be edited after a payroll run. The JSON snapshot inside each `LiveHostPayrollItem` locks in the percentages and tier ID used at the time, so historical payroll remains reproducible even if the schedule changes later.

---

## 6. UI Changes — Commission Panel

### Current layout (for reference)

```
01 Fixed pay           →  base salary, per-live rate
02 Performance pay     →  per-platform flat %
03 Override earnings   →  L1 % / L2 % (flat, on host profile)
04 Hierarchy & Notes
PROJ Monthly projection
```

### New layout

```
01 Fixed pay              →  unchanged
02 Commission tiers       →  per-platform tier table(s) — replaces old 02 + 03
03 Hierarchy & Notes      →  unchanged (was 04)
PROJ Monthly projection   →  updated to use tier lookup
```

### Section 02 — Commission tiers (new)

One card per platform the host operates on, with an "Add platform" button matching the current Performance pay UX. Inside each platform card:

- Header: `{platform.name}` + effective-from badge + menu (Add new effective-from row / Archive / Edit).
- Table with columns: `Tier` | `Monthly GMV (min – max)` | `Internal %` | `L1 %` | `L2 %`.
- Each row supports inline editing via the existing `<LiveField/>` pattern (number inputs with auto-save).
- Row actions: add tier (+ button at bottom), remove tier (only the last/highest tier is removable, to keep the tier numbering contiguous).
- Visual validation: overlapping ranges flagged in red; top tier's max field displays as "∞" when null.

### Monthly projection updates

- Per-platform GMV slider (already exists) now shows the **matched tier** as a badge ("Tier 3 — 60K–100K").
- Sub-card breakdown shows:
  - `Your earnings` = `internal_percent × GMV`
  - `Generated for upline (L1)` = `l1_percent × GMV` (informational, not part of this host's take-home)
  - `Generated for L2 upline` = `l2_percent × GMV` (informational)
- If slider is below Tier 1 floor: show a muted "Below Tier 1 — no performance commission" state.

### Editing semantics

- All inline edits on tier rows are validated server-side through a single `UpdateLiveHostPlatformCommissionTierRequest`.
- Adding a new effective-from schedule soft-archives the current schedule (set `effective_to = new schedule's effective_from - 1 day`, `is_active = false`) and inserts the new rows with `is_active = true`.

---

## 7. API / Backend Surface

### New model: `LiveHostPlatformCommissionTier`

- Relations: `user()`, `platform()`.
- Scope: `active()` (`is_active = true AND effective_from <= today AND (effective_to is null OR effective_to >= today)`).
- Casts: all monetary/percent fields as `decimal:2`, dates as `date`.

### New FormRequests (in `app/Http/Requests/LiveHost/`)

- `StoreLiveHostPlatformCommissionTierRequest` — creates a full schedule (array of tier rows) for a (host, platform).
- `UpdateLiveHostPlatformCommissionTierRequest` — edits a single tier row's fields.
- Validation covers the invariants in §3.

### HostController changes (`app/Http/Controllers/LiveHost/HostController.php`)

- `show()` — also loads tier schedules grouped by platform, passes as `commissionTiers` prop.
- New actions: `storeTier`, `updateTier`, `destroyTier` (or a bulk `syncTiers` endpoint — decide during planning).

### Routes

Add under the existing `/livehost/hosts/{host}` group (controllers in `app/Http/Controllers/LiveHost/`):

- `POST   /livehost/hosts/{host}/platforms/{platform}/tiers`
- `PATCH  /livehost/hosts/{host}/tiers/{tier}`
- `DELETE /livehost/hosts/{host}/tiers/{tier}`

---

## 8. Testing

Feature tests (Pest, `tests/Feature/LiveHost/`):

- `CommissionTierResolver` returns null below Tier 1 floor.
- Resolver returns correct tier for values at and across boundaries (including the open-top tier).
- Resolver only considers tier rows where `effective_from ≤ asOf ≤ (effective_to ?? asOf)`.
- Payroll run: host with GMV in Tier 3 receives `internal_percent × GMV`; upline receives `l1_percent × GMV`; L2 upline receives `l2_percent × GMV`.
- Payroll run: host below Tier 1 floor receives zero performance pay; upline receives zero L1 override from that platform.
- Payroll breakdown JSON snapshots the tier ID and percentages used.
- FormRequest validation rejects overlapping ranges, gaps, non-contiguous tier numbers.

Browser tests (Pest 4 browser, `tests/Browser/LiveHost/`):

- Admin can add a new platform tier schedule on the host detail page, saves inline, matches payroll projection card.
- Monthly projection slider shows correct tier highlight and earnings values as GMV changes.

---

## 9. Open Questions / Future Work

Not in scope for this design, but worth noting:

- **Template tier schedules.** If editing 5 rows per host × per platform becomes tedious, add a "Copy from another host" or "Apply global template" action. Deferred — per-host is the starting requirement.
- **More than L2.** Current system caps override at L2. If the tree ever needs L3+, the tier row schema would extend (`l3_percent`, etc.), and calculator walks one more hop.
- **Tier unlock based on trailing months.** Currently the tier resets every payroll period. If the business wants "once you hit Tier 5, you stay there for 3 months", that's a new concept (`tier_lock_months`) layered on top. Deferred.
- **Dropping deprecated columns.** A follow-up migration drops `commission_rate_percent`, `override_rate_l1_percent`, `override_rate_l2_percent` once the new system is verified in production.

---

## 10. Summary of Decisions (from brainstorming)

| Question | Decision |
|---|---|
| Tier table scope | Per-host (each host has their own schedule) |
| Tiered fields | All three — Internal %, L1 %, L2 % |
| GMV source for tier lookup | Per-platform (each platform has its own schedule, looked up by that platform's GMV) |
| Payout semantics | Single source of truth — downline's GMV picks the tier row, which pays all three wallets |
| Below Tier 1 minimum | Zero performance pay, zero override. Base + per-live still paid. |
