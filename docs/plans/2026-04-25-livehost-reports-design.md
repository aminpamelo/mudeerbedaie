# Live Host — Reports Module

**Date:** 2026-04-25
**Status:** Design approved, ready for implementation planning
**Owner:** —

---

## 1. Problem

The Live Host Desk (`/livehost/*`) collects rich operational data — `LiveSession` (with GMV, duration, status), `LiveSessionGmvAdjustment`, `LiveSchedule` + `LiveScheduleAssignment`, `SessionReplacementRequest`, `LiveHostPayrollRun`, `LiveHostCommissionProfile`, and TikTok import data — but the owner has no consolidated way to answer day-to-day business questions:

- Which hosts are pulling their weight? Who's a no-show risk?
- Is GMV trending up, and on which platform accounts?
- Are we covering our schedule? Where are the gaps?
- How much replacement churn is happening, and who's absorbing it?

Today the answers live across the Sessions list, Payroll runs, and the Replacements queue. The owner has to mentally stitch them together. We need a single **Reports** area that surfaces these answers with filters, trend charts, drill-downs, and CSV export.

## 2. Audience

**Primary:** Owner / management. Reports are tuned to financial and operational KPIs that the business owner cares about, not the day-to-day PIC view (which is already served by the existing Dashboard, Sessions, and Replacements pages).

Access: `role:admin,admin_livehost` — the same gate as the rest of `/livehost/*`. No new role is introduced.

## 3. Decisions

| # | Decision | Rationale |
|---|---|---|
| 1 | Single **Reports** nav item under the existing `Records` group; landing page lists 4 reports as cards, click to drill in | Keeps the sidebar tidy. Owner sees one entry point and picks what to read. |
| 2 | **Phased rollout**: Host Scorecard ships first, fully polished. GMV / Coverage / Replacements follow in subsequent phases reusing the same patterns | Validates the report shell (filters, charts, CSV, drill-down) on one report before replicating mistakes 4×. |
| 3 | **Query-on-demand** against existing tables. No materialized views, no caching layer | Data volume is small (31–100 staff, single-tenant). Premature optimization. Re-evaluate if a report query exceeds ~1s. |
| 4 | **Recharts** for all charts (already in `package.json`) | No new dependency. Composable, works well with React 19 + Inertia. |
| 5 | **Server-side CSV export** via `response()->streamDownload`, same data the page renders | No client-side conversion drift. Streams handle large datasets without memory spikes. |
| 6 | **Default date range = current month** (1st of month → today). Presets: Today, This week, This month, Last month, Last 30 days, Custom | Owner usually thinks in months for financial reporting. |
| 7 | All currency displayed in **MYR** (existing app convention). No multi-currency. | Matches the rest of the platform. |
| 8 | **No scheduled email digests** in v1 | Out of scope. Owner pulls reports on demand. Can add later if requested. |
| 9 | New routes live under `/livehost/reports/*` (Inertia React, PIC app) | Mirrors the existing PIC paradigm. No new paradigm. |
| 10 | Reports read existing data only — **no new tables, no new columns** | Reports are a view layer. Any data gap that surfaces should be fixed in the source domain, not the reports module. |

## 4. Architecture

### 4.1 New files

**Backend (`app/Http/Controllers/LiveHost/Reports/`)**
- `ReportsController.php` — landing page; lists all 4 reports
- `HostScorecardController.php` — `index` (page) + `export` (CSV)
- `GmvController.php` — phase 2
- `CoverageController.php` — phase 3
- `ReplacementsController.php` — phase 4

**Backend services (`app/Services/LiveHost/Reports/`)**
- `HostScorecardReport.php` — pure query/aggregation class. Takes filters, returns a typed result object. Reusable by both controller (for Inertia) and CSV exporter.
- `GmvReport.php`, `CoverageReport.php`, `ReplacementsReport.php` — phases 2–4.
- `Filters/ReportFilters.php` — value object for `dateFrom`, `dateTo`, `hostIds[]`, `platformAccountIds[]`, `status[]`.

**Frontend (`resources/js/livehost/pages/reports/`)**
- `Index.jsx` — landing with 4 cards
- `HostScorecard.jsx` — phase 1
- `Gmv.jsx`, `Coverage.jsx`, `Replacements.jsx` — phases 2–4

**Shared frontend components (`resources/js/livehost/components/reports/`)**
- `DateRangePicker.jsx` — preset buttons + custom range
- `ReportFilters.jsx` — date range + multi-select host / account filters
- `KpiCard.jsx` — large stat with delta vs prior period
- `TrendChart.jsx` — recharts wrapper (line / bar)
- `ExportCsvButton.jsx` — POSTs current filters, triggers download

**Routes (`routes/web.php`, inside the `livehost` group, `role:admin,admin_livehost` middleware)**
- `GET  /livehost/reports` → `ReportsController@index`
- `GET  /livehost/reports/host-scorecard` → `HostScorecardController@index`
- `GET  /livehost/reports/host-scorecard/export` → `HostScorecardController@export`
- (Phases 2–4 add the same pair for GMV / Coverage / Replacements)

**Sidebar (`resources/js/livehost/layouts/LiveHostLayout.jsx`)**
- Add `{ key: 'reports', label: 'Reports', href: '/livehost/reports', icon: BarChart3 }` to the `Records` group, between `Live Sessions` and `Commission`.

### 4.2 Phase 1: Host Scorecard — full spec

**Page header:** "Host Scorecard" + date range picker + filter strip (host multi-select, platform-account multi-select) + "Export CSV" button.

**KPI cards (4, top of page, computed for filter window):**
- Total live hours (sum of `duration_minutes / 60` from `live_sessions` where `status='completed'`)
- Total GMV (`SUM(gmv_amount + gmv_adjustment)`)
- Total commission paid (`SUM` from `live_host_payroll_items` joined to runs in window)
- Avg attendance rate (% of scheduled sessions completed; replacements count for the replacement host)

Each card shows the value plus % delta vs the prior equal-length window.

**Trend chart:** stacked bar, daily — bars stack `completed` / `missed` / `replacement` session counts. Filter window respected.

**Main table** (one row per host, sortable, paginated):

| Column | Source |
|---|---|
| Host | `users.name` (link → `/livehost/hosts/{id}`) |
| Sessions scheduled | count of `live_schedule_assignments` in window for this host |
| Sessions completed | count of `live_sessions` where `status='completed'` |
| Hours live | `SUM(duration_minutes)/60` |
| GMV | `SUM(gmv_amount + gmv_adjustment)` |
| Avg GMV / hr | GMV / hours live |
| Commission earned | `SUM` from `live_host_payroll_items` |
| No-shows | count of `live_sessions` where `status='missed'` |
| Late starts | count where `actual_start_at > scheduled_start_at + 5 min` |
| Replacement requests | count of `session_replacement_requests` where `original_host_id = host.id` |
| Attendance % | completed / scheduled |

Click a host name → existing `/livehost/hosts/{id}` page.

**CSV export:** identical columns to the table, all matching rows (no pagination), filename `host-scorecard_{YYYY-MM-DD}_{YYYY-MM-DD}.csv`.

**Empty state:** "No host activity in this date range." with a button to widen to "Last 30 days".

### 4.3 Phase 2: GMV Performance — outline

- KPI cards: Total GMV, GMV per session, top platform account, top host
- Trend chart: line, daily GMV split by platform account (one line per account)
- Two side-by-side tables: by host, by platform account
- Drill-down: click a session row in a "recent high-GMV sessions" panel → `/livehost/sessions/{id}`

### 4.4 Phase 3: Schedule Coverage — outline

- KPI cards: % slots filled, unassigned slots count, replacement-covered count, no-show rate
- Trend chart: stacked bar, weekly — `assigned` / `unassigned` / `replaced` / `missed` slot counts
- Table by platform account: total slots, filled, unassigned, coverage %
- Drill-down: click an unassigned count → filtered Session Slots page for that account/week

### 4.5 Phase 4: Replacement Activity — outline

- KPI cards: total requests, fulfilled, expired, avg time-to-assign
- Trend chart: line — daily request count, with a second line for fulfilled
- Two tables: top requesters (original_host_id), top coverers (replacement_host_id), with reason-category breakdown
- Drill-down: click a request → existing `/livehost/replacements/{id}` page

### 4.6 Data flow (Host Scorecard, representative)

```
Browser
  → GET /livehost/reports/host-scorecard?dateFrom=…&dateTo=…&hostIds[]=…
HostScorecardController@index
  → ReportFilters::fromRequest($request)
  → HostScorecardReport->run($filters)  // returns typed result
  → Inertia::render('reports/HostScorecard', [data])
HostScorecard.jsx
  → renders KpiCard × 4, TrendChart, sortable table
  → ExportCsvButton submits same filters to /export
HostScorecardController@export
  → same ReportFilters, same Report service
  → response()->streamDownload(fn() => fputcsv-loop, filename)
```

### 4.7 Query strategy

- All queries go through Eloquent (`Model::query()`), not `DB::`.
- Always eager-load relationships used in the row (`with(['platformAccount','liveHost'])`).
- Aggregations done in SQL, not PHP (`selectRaw('SUM(...) as gmv')` + `groupBy`). Single round-trip per report.
- Date filtering uses `whereBetween('scheduled_start_at', [from, to])` — index-friendly on existing columns.
- N+1 guard: every report has a Pest test that wraps the query block in `DB::enableQueryLog()` and asserts query count is bounded.

### 4.8 Authorization

- Routes wrapped in the existing `role:admin,admin_livehost` middleware.
- No per-report policy needed in v1 — owner audience, single role gate.
- If a future "host can see their own scorecard" requirement appears, add a `HostScorecardPolicy` then.

## 5. Testing

- **Pest feature tests** per controller: unauthenticated → redirect, wrong role → 403, correct role → 200 with expected props.
- **Pest unit tests** per Report service class: seed sessions/assignments/replacements, run with various filter combinations, assert aggregates.
- **N+1 guard test**: each report runs under a query-count assertion (≤ 5 queries on a fixture of 50 hosts × 200 sessions).
- **CSV export test**: hits the export route, asserts `Content-Type: text/csv`, parses the body, asserts row count and a known row's values.
- No browser tests in v1 — Pest 4 browser is available but Inertia + recharts is well-trodden ground; cost-benefit doesn't justify it for the MVP.

## 6. Out of scope (v1)

- Scheduled email digests of reports.
- Per-host self-service scorecard at `/live-host/me/scorecard`.
- PDF export.
- Multi-currency.
- Saved/named report views.
- Comparison mode (period A vs period B side-by-side).
- TikTok-specific analytics report (viewers, likes, watch time) — depends on which fields TikTok imports actually populate; deferred until that's audited.

## 7. Open questions

- **Late-start threshold:** I've assumed `> 5 min` past `scheduled_start_at` counts as "late." If the team has a different operational definition, swap the constant in `HostScorecardReport`.
- **Commission window:** "Commission earned in date range" — do we attribute by session date (when the work happened) or by payroll run period (when it was paid out)? I've assumed **session date** in the spec; flag if you'd prefer the payroll-period attribution.
- **Replacement attribution in attendance %:** When host A asks host B to cover, and B completes the session, A's attendance is calculated against scheduled-minus-replaced. B gets credit for completing. Confirm this matches how you think about it.

## 8. Phased delivery

| Phase | Scope | Why this order |
|---|---|---|
| **1** | Reports landing page + Host Scorecard (full: filters, KPIs, chart, table, CSV, drill-down) + sidebar nav + shared components | Highest-value report; touches the most data sources; validates the shell |
| **2** | GMV Performance | Reuses date filter + chart wrapper; pure aggregation, no new patterns |
| **3** | Schedule Coverage | Adds a slot-vs-session join pattern; reuses everything else |
| **4** | Replacement Activity | Smallest report; ships last as a quick win |

Each phase is a separate branch + PR.
