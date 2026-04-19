# Live Host Commission System — Design Document

**Date:** 2026-04-19
**Author:** Owner + Claude (brainstormed)
**Status:** Approved — ready for implementation planning
**Target area:** Live Host Desk (Inertia + React) at `/livehost/*`

---

## 1. Problem

We need to pay live hosts (staff who stream on TikTok Shop for our shop) a mix of:
- **Fixed base salary** — RM1,500 – RM2,500 monthly
- **Commission on GMV** — 3–6% of gross merchandise value they generate on live
- **Rate per live** — RM20 – RM50 flat per completed live session

In addition, we need a **2-level MLM override**: a host who recruits other hosts earns a small override on their downlines' GMV commission.

All current live-host pages exist (`/livehost/hosts`, `/livehost/schedules`, `/livehost/session-slots`, `/livehost/platform-accounts`, `/livehost/sessions`, `/live-host/*` pocket), but none of them compute or track commission. GMV is not captured today.

## 2. Decisions (recap from brainstorm)

| Decision | Chosen |
|---|---|
| How 3 layers combine | All 3 always combined, values configurable per host (set 0 to disable a layer) |
| GMV capture | Host enters manually on recap; PIC locks on verify; TikTok xlsx import overwrites when available |
| Returns / failed COD | Gross GMV + PIC adjustment field; monthly cutoff 14 days after month-end |
| API strategy | Schema designed for API plug-in; manual xlsx upload in v1 |
| MLM depth | 2 levels (direct upline + 2nd upline) |
| Override base | % of downline's GMV commission only (not salary, not per-live rate) |
| Upline assignment | PIC sets on host profile; override % is per-upline |
| Payout cadence | Monthly; only `verification_status=verified` sessions qualify |
| Per-platform rates | Schema supports it; v1 UI shows TikTok only |
| Missed sessions | Earn nothing (no rate, no commission) |
| Creator identity | Tracked on pivot + required on session slot |
| TikTok xlsx reconciliation | Included in v1 as Phase 6 |

## 3. Industry context

From research (see brainstorm transcript):
- Malaysian TikTok live host compensation typically combines RM1,500–3,500 base + RM20–50/hr + 2–6% commission. User's ranges match industry.
- Total payroll cost hovers at 12–18% of net GMV for healthy agencies.
- TikTok Partner API (Finance / Order / Return) exists but requires Partner-level approval — impractical for v1.
- Shopee Open API's LiveStream endpoint is not yet available for Malaysia.
- Manual xlsx export + upload is how most SEA agencies bridge the API gap today.

## 4. Data model

### 4.1 New tables

**`live_host_commission_profiles`** — one row per host
| column | type | notes |
|---|---|---|
| `id` | bigint PK | |
| `user_id` | FK users, unique | |
| `base_salary_myr` | decimal(10,2) | 0 if host has no salary |
| `per_live_rate_myr` | decimal(10,2) | 0 if not applicable |
| `upline_user_id` | FK users, nullable | circular-prevented |
| `override_rate_l1_percent` | decimal(5,2) | % this host earns from direct downlines' GMV commission |
| `override_rate_l2_percent` | decimal(5,2) | % this host earns from L2 downlines' GMV commission |
| `effective_from` | datetime | |
| `effective_to` | datetime, nullable | null = current |
| `is_active` | boolean | |
| `notes` | text, nullable | |
| timestamps | | |

**`live_host_platform_commission_rates`** — one row per (host × platform)
| column | type | notes |
|---|---|---|
| `id` | bigint PK | |
| `user_id` | FK users | |
| `platform_id` | FK platforms | |
| `commission_rate_percent` | decimal(5,2) | e.g., 4.00 |
| `effective_from` | datetime | |
| `effective_to` | datetime, nullable | |
| `is_active` | boolean | |
| timestamps | | |
| unique(user_id, platform_id, effective_from) | | |

**`live_session_gmv_adjustments`** — audit log for PIC return/refund adjustments
| column | type | notes |
|---|---|---|
| `id` | bigint PK | |
| `live_session_id` | FK live_sessions | |
| `amount_myr` | decimal(10,2) | negative for deductions |
| `reason` | string | |
| `adjusted_by` | FK users | PIC |
| `adjusted_at` | datetime | |
| timestamps | | |

**`live_host_payroll_runs`** — monthly batches
| column | type | notes |
|---|---|---|
| `id` | bigint PK | |
| `period_start` | date | e.g., 2026-04-01 |
| `period_end` | date | e.g., 2026-04-30 |
| `cutoff_date` | date | period_end + 14 days |
| `status` | enum | draft \| locked \| paid |
| `locked_at` | datetime, nullable | |
| `locked_by` | FK users, nullable | |
| `paid_at` | datetime, nullable | |
| `notes` | text, nullable | |
| timestamps | | |

**`live_host_payroll_items`** — line items per host per run
| column | type | notes |
|---|---|---|
| `id` | bigint PK | |
| `payroll_run_id` | FK payroll_runs | |
| `user_id` | FK users | |
| `base_salary_myr` | decimal(10,2) | |
| `sessions_count` | int | |
| `total_per_live_myr` | decimal(10,2) | |
| `total_gmv_myr` | decimal(12,2) | |
| `total_gmv_adjustment_myr` | decimal(12,2) | |
| `net_gmv_myr` | decimal(12,2) | |
| `gmv_commission_myr` | decimal(10,2) | |
| `override_l1_myr` | decimal(10,2) | earned from direct downlines |
| `override_l2_myr` | decimal(10,2) | earned from L2 downlines |
| `gross_total_myr` | decimal(10,2) | |
| `deductions_myr` | decimal(10,2) | |
| `net_payout_myr` | decimal(10,2) | |
| `calculation_breakdown_json` | json | per-session + per-downline detail |
| timestamps | | |
| unique(payroll_run_id, user_id) | | |

**`tiktok_report_imports`** — one row per xlsx upload
| column | type |
|---|---|
| `id` | bigint PK |
| `report_type` | enum (live_analysis \| order_list) |
| `file_path` | string |
| `uploaded_by` | FK users |
| `uploaded_at` | datetime |
| `period_start` | date |
| `period_end` | date |
| `status` | enum (pending \| processing \| completed \| failed) |
| `total_rows` | int |
| `matched_rows` | int |
| `unmatched_rows` | int |
| `error_log_json` | json, nullable |
| timestamps | |

**`tiktok_live_reports`** — from `Live Analysis.xlsx`
One row per live session row in the TikTok export. Columns mirror the xlsx schema:
`id`, `import_id`, `tiktok_creator_id`, `creator_nickname`, `creator_display_name`, `launched_time`, `duration_seconds`, `gmv_myr`, `live_attributed_gmv_myr`, `products_added`, `products_sold`, `sku_orders`, `items_sold`, `unique_customers`, `avg_price_myr`, `click_to_order_rate`, `viewers`, `views`, `avg_view_duration_sec`, `comments`, `shares`, `likes`, `new_followers`, `product_impressions`, `product_clicks`, `ctr`, `matched_live_session_id` (nullable FK), `raw_row_json`, timestamps.

**`tiktok_orders`** — from `All order.xlsx`
`id`, `import_id`, `tiktok_order_id` (unique), `order_status`, `order_substatus`, `cancelation_return_type`, `created_time`, `paid_time`, `rts_time`, `shipped_time`, `delivered_time`, `cancelled_time`, `order_amount_myr`, `order_refund_amount_myr`, `payment_method`, `fulfillment_type`, `product_category`, `matched_live_session_id` (nullable FK — matched by creator + time overlap), `raw_row_json`, timestamps.

### 4.2 Extensions to existing tables

**`live_sessions`** — add columns:
| column | type | notes |
|---|---|---|
| `live_host_platform_account_id` | FK pivot, nullable | which (host, shop, creator-handle) combo was used |
| `gmv_amount` | decimal(12,2), nullable | gross GMV from recap or TikTok import |
| `gmv_adjustment` | decimal(12,2), default 0 | sum of adjustments; cached for quick payroll |
| `gmv_source` | enum (manual \| tiktok_import \| tiktok_api \| shopee_api), default manual | |
| `gmv_locked_at` | datetime, nullable | set on PIC verify |
| `commission_snapshot_json` | json, nullable | frozen calc at verification |

**`live_session_slots`** — add column:
| column | type | notes |
|---|---|---|
| `live_host_platform_account_id` | FK pivot, not null for new slots | required for creator-identity reliability |

**`live_host_platform_account` (pivot)** — add columns:
| column | type | notes |
|---|---|---|
| `creator_handle` | string, nullable | e.g., `@amarmirzabedaie` |
| `creator_platform_user_id` | string, nullable | TikTok internal ID, e.g., `6526684195492729856` |
| `is_primary` | boolean, default false | default identity when host has multiple |

All migrations follow the MySQL+SQLite dual-driver pattern in CLAUDE.md.

## 5. Calculation rules

### 5.1 Per-session (snapshot on PIC verify)

```
net_gmv           = gmv_amount + gmv_adjustment     (adjustment negative)
platform_rate     = active row in live_host_platform_commission_rates for session's platform
                    at session's actual_start_at
gmv_commission    = net_gmv × platform_rate
per_live_rate     = host's profile.per_live_rate_myr (0 if session is missed)
session_total     = gmv_commission + per_live_rate
```

Missed sessions (recap "No, I did not go live"): `gmv_amount` forced to 0, `per_live_rate` contributes 0. Session contributes 0 to earnings.

### 5.2 Monthly payroll

```
For each active host H in period:
  base_salary     = profile active on period_end .base_salary_myr
  own_earnings    = Σ verified session_totals within period
  
  override_l1     = Σ over direct downlines D of H:
                      D.gmv_commission_in_period × H.profile.override_rate_l1_percent
  override_l2     = Σ over L2 downlines D2 of H:
                      D2.gmv_commission_in_period × H.profile.override_rate_l2_percent
  
  gross_total     = base_salary + own_earnings + override_l1 + override_l2
  net_payout      = gross_total − deductions
```

Override is on GMV commission only — not on base salary, not on per-live rate.

Upline chain walks `live_host_commission_profiles.upline_user_id` at the time the session ended (historical fidelity).

### 5.3 Worked example (April 2026)

Three hosts:
- Ahmad (top): RM2,000 salary, 4% TikTok, RM30/live, L1=10%, L2=5%
- Sarah (under Ahmad): RM1,800 salary, 5% TikTok, RM25/live, L1=10%, L2=5%
- Amin (under Sarah): RM0 salary, 6% TikTok, RM50/live

April: Ahmad 8 sessions RM12k GMV (returns RM200 → net RM11,800). Sarah 12 sessions RM18k GMV (returns RM500 → net RM17,500). Amin 10 sessions RM22k GMV (returns RM300 → net RM21,700).

Own earnings:
- Ahmad: 11,800 × 4% = RM472 + 8×30 = RM240 → RM712
- Sarah: 17,500 × 5% = RM875 + 12×25 = RM300 → RM1,175
- Amin: 21,700 × 6% = RM1,302 + 10×50 = RM500 → RM1,802

Overrides (on GMV commission only):
- Sarah L1 on Amin: 1,302 × 10% = RM130.20
- Ahmad L1 on Sarah: 875 × 10% = RM87.50
- Ahmad L2 on Amin: 1,302 × 5% = RM65.10

Payslips:
| Host | Salary | Own | L1 | L2 | **Net** |
|---|---|---|---|---|---|
| Ahmad | 2,000 | 712 | 87.50 | 65.10 | **2,864.60** |
| Sarah | 1,800 | 1,175 | 130.20 | — | **3,105.20** |
| Amin | 0 | 1,802 | — | — | **1,802.00** |
| **Total** | | | | | **7,771.80** |

Effective payroll cost = 15.2% of net GMV (RM51,000). Aligns with SEA agency norms.

## 6. Workflow

1. **PIC creates session slot** on `/livehost/session-slots`: picks host + time + `live_host_platform_account_id` (the shop + creator-handle combo). Required.
2. **Host conducts live** → session transitions from `scheduled` → `live` → `ended`.
3. **Host submits recap** on Live Host Pocket: "Did you go live? Yes/No" + GMV (RM) + TikTok Shop backend screenshot (required attachment type when "Yes").
4. **PIC reviews on Session Detail**: sees GMV, screenshot, and the creator identity used. Can verify (locks `gmv_amount`, snapshots commission) or reject with note.
5. **(Optional at any point before payroll lock)** PIC uploads `Live Analysis.xlsx` on `/livehost/tiktok-imports`. System auto-matches rows to sessions by `(tiktok_creator_id, launched_time ±30min)`. Variance report shown. PIC clicks "Apply TikTok values" → sessions update to `gmv_source='tiktok_import'`. Unmatched rows can be used to create retroactive sessions.
6. **(End of month)** PIC uploads `All order.xlsx`. System identifies refunds (`Order Refund Amount > 0` or `Cancelled Time` set), matches to sessions by creator + time overlap, proposes `gmv_adjustment` entries. PIC reviews/approves.
7. **Monthly payroll** at `/livehost/payroll` — draft run on cutoff date (period_end + 14 days). PIC previews, adjusts deductions, clicks **Lock**. After Lock: session adjustments blocked. **Mark Paid** is a final step (ties to an export for now).

## 7. UI surfaces

### 7.1 Existing pages — extensions

- **Live Hosts Index** (`/livehost/hosts`): new column "Commission Plan" (`RM2000 + 4% + RM30`). Filter by upline/downline/no-plan.
- **Host detail / edit**: new "Commission" tab (profile fields, platform-rate table, upline, override %). Read-only "Commission history" subsection shows prior effective versions.
- **Session Slots** (`/livehost/session-slots`): creator-identity dropdown required (auto-picks primary).
- **Session Detail** (`/livehost/sessions/{id}`): new PIC-only "Commission" panel (GMV, screenshot, adjustments list, computed breakdown, snapshot JSON).
- **Live Host Pocket recap form**: new GMV numeric input + required TikTok screenshot when "Yes I went live". Show an estimate: "You'll earn ~RM{x} when verified."

### 7.2 New pages

- **Commission Overview** (`/livehost/commission`): matrix table of all hosts × their 3 layers + upline. Inline-editable. CSV export.
- **Monthly Payroll** (`/livehost/payroll`): list + run detail. Columns per row: Salary, Sessions, Per-live Total, GMV, Adjustments, Net GMV, Commission, L1, L2, Gross, Deductions, Net. Side drawer with per-session and per-downline breakdown. Actions: Recompute / Lock / Mark Paid / Export CSV.
- **TikTok Imports** (`/livehost/tiktok-imports`): list of imports. Upload form (xlsx). Per-import detail page with matched/unmatched tables, variance report, bulk-apply button.

### 7.3 Permissions

| Role | Commission pages | Verify sessions | Adjust GMV | Lock payroll |
|---|---|---|---|---|
| `admin` | ✓ | ✓ | ✓ | ✓ |
| `admin_livehost` | ✓ | ✓ | ✓ | ✓ |
| `live_host` | Own earnings only (read) | ✗ | ✗ | ✗ |

## 8. Build sequence

Each phase is independently shippable. Test after each.

| Phase | Scope | Est. days |
|---|---|---|
| **1. Schema & Profiles** | All migrations; Eloquent models; circular-upline validation; seeders for test data | 1–2 |
| **2. Host-facing GMV entry** | Recap form additions; required screenshot attachment type | 1 |
| **3. PIC verification + snapshot** | Session Detail commission panel; `CommissionCalculator` service; GMV adjustments CRUD + audit log | 2 |
| **4. Admin surfaces** | Host commission tab; Commission Overview page; upline dropdown | 2 |
| **5. Monthly Payroll** | Payroll run lifecycle (draft/lock/paid); list + detail pages; CSV export | 2–3 |
| **6. TikTok reconciliation** | xlsx parser; import page; match logic; variance review; auto-adjustment from order refunds | 2–3 |
| **Total v1** | | **10–13** |

Deferred to v1.1:
- Host earnings view in Live Host Pocket
- TikTok Partner API sync (replaces xlsx import, same schema)
- Shopee platform rates + live streaming integration
- Mid-month salary prorating, minimum-GMV thresholds, inactive-upline policy flag

## 9. Testing strategy

Pest (Feature + Browser) per CLAUDE.md. Tests land alongside their phase:

- `LiveHostCommissionProfileTest` — circular upline prevention, effective-dating
- `LiveSessionGmvEntryTest` — host recap validation, screenshot required
- `PicVerifyCommissionSnapshotTest` — snapshot matches formula, locks correctly
- `GmvAdjustmentTest` — audit log, blocked post-lock
- `MonthlyPayrollCalculationTest` — golden test with Section 5.3 fixture; payroll run must produce exact RM values
- `UplineOverrideTest` — 2-level walk, override excludes salary/rate, historical upline fidelity
- `TiktokLiveReportImportTest` — xlsx parse, row matching, variance calc
- `TiktokOrderReconciliationTest` — refund-to-session matching, auto-proposed adjustments

Browser tests:
- Host recap submission flow
- PIC verify-and-snapshot flow
- Payroll draft → lock → paid flow
- xlsx upload → variance review → apply

Run `php artisan test --compact --filter=Commission` after each phase.

## 10. Open questions (deferred)

Not blocking v1. Revisit when usage data is in:
- Should inactive uplines still earn override? (Currently yes, for historical fidelity.)
- Minimum GMV threshold before commission applies? (Currently no.)
- Mid-month prorating of base salary? (Currently no — manual note/deduction.)
- Host-facing earnings transparency in pocket app? (Deferred to v1.1.)
- Shop Affiliate attribution mode? (Only linked-creator model in v1.)

---

**Approved for implementation planning.** Next step: writing-plans skill to produce the detailed step-by-step plan.
