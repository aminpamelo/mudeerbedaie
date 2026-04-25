# Live Host Reports — Phases 2–4 Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Ship the remaining three reports — GMV Performance, Schedule Coverage, Replacement Activity — sitting alongside the live Host Scorecard. After this plan, the four "Coming soon" cards on `/livehost/reports` are all clickable.

**Reference Phase 1:** Phase 1 (`docs/plans/2026-04-25-livehost-reports-phase1-host-scorecard.md`, commits `32de8fa..b362413`) established the patterns this plan re-uses. Read those files before each task — they're the templates:

- Service shape: `app/Services/LiveHost/Reports/HostScorecardReport.php` + `HostScorecardResult.php`
- Controller shape: `app/Http/Controllers/LiveHost/Reports/HostScorecardController.php`
- Page shape: `resources/js/livehost/pages/reports/HostScorecard.jsx`
- Filter parsing: reuse `App\Services\LiveHost\Reports\Filters\ReportFilters` as-is
- Frontend primitives: reuse `KpiCard`, `DateRangePicker`, `TrendChart`, `ReportFilters`, `ExportCsvButton`
- Layout pattern: fragment + `<TopBar>` + `.layout` static property + `p-8`
- Routes nest inside `Route::prefix('reports')->name('reports.')` group at `routes/web.php`

**Codebase quirks already discovered (don't re-discover them):**
- Roles: `User::factory()->create(['role' => 'live_host'])` — NOT Spatie.
- `LiveSession.status` values: `scheduled`, `live`, `ended`, `cancelled`, `missed`. "Completed" = `ended`.
- `LiveHostPayrollRun` / `LiveHostPayrollItem` have NO factories — use `Model::create([...])` in tests.
- Inertia tests use `->component('reports/Foo', false)` — pass `false` as 2nd arg.
- DB: SQLite (test) + MySQL (prod). Avoid driver-specific functions; `DATE()` is portable, `julianday()` and `DATE_ADD()` are not. For driver-specific date arithmetic, do it in PHP.
- `users` table has a `status` column. SQL aggregates on `live_sessions` MUST qualify `live_sessions.status`, `live_sessions.duration_minutes`, etc., when joining `users`.
- Trend bucket dates from `selectRaw('DATE(...) as day')` come back as strings — cast `(string) $r->day`.
- N+1 guard: each new service has a Pest test asserting `≤ 5` queries on a 20-host × 10-session fixture. Keep the bound; refactor the service if it ever fails.
- Run `vendor/bin/pint --dirty` before each commit.

**Phase 1 review fixes also apply** (commit `b362413`): `ReportFilters` has a `parseOrFallback` helper that swallows malformed dates. No need to repeat that work; just reuse the value object.

---

## Phase 2 — GMV Performance

**Goal:** Daily GMV trend + per-host and per-account breakdowns, drill-down on top sessions.

### Phase 2 / Task 1: `GmvReport` service + tests

**Files:**
- Create: `app/Services/LiveHost/Reports/GmvReport.php`
- Create: `app/Services/LiveHost/Reports/GmvResult.php`
- Test: `tests/Feature/LiveHost/Reports/GmvReportTest.php`

**Result shape:**

```php
class GmvResult
{
    public function __construct(
        public readonly array $kpis,         // ['totalGmv' => float, 'gmvPerSession' => float, 'topAccountId' => ?int, 'topAccountGmv' => float, 'topHostId' => ?int, 'topHostGmv' => float]
        public readonly array $trendByAccount, // [['date' => 'YYYY-MM-DD', 'series' => [account_id => gmv, ...]], ...]  one row per day in window
        public readonly array $accountSeries,  // [['accountId' => int, 'name' => string, 'totalGmv' => float], ...] — used by the chart legend + per-account table
        public readonly array $hostRows,       // [['hostId' => int, 'hostName' => string, 'sessions' => int, 'gmv' => float, 'avgGmvPerSession' => float], ...]
        public readonly array $topSessions,    // up to 10 sessions: ['sessionId' => int, 'date' => 'YYYY-MM-DD', 'hostName' => string, 'accountName' => string, 'gmv' => float]
    ) {}
}
```

Only count `live_sessions` where `status = 'ended'` for all GMV figures. `gmv = gmv_amount + COALESCE(gmv_adjustment, 0)`.

**Required tests** (Pest, append-style):

1. `it('aggregates totalGmv and gmvPerSession over the window')` — seed 3 ended sessions across 2 days totalling 1500 GMV → expect `totalGmv=1500, gmvPerSession=500`.
2. `it('identifies top account and top host by GMV')` — 2 hosts, 2 accounts, deterministic GMV; assert correct IDs/totals.
3. `it('produces daily trend split by account')` — assert `trendByAccount` has one entry per day with the right account sub-totals.
4. `it('returns top sessions ordered by GMV desc, capped at 10')` — seed 12 sessions, assert 10 returned, descending.
5. `it('respects host and account filters')` — same pattern as Phase 1.

**TDD cycle:** failing tests → implement service → all green → pint → commit.

Commit message: `feat(live-host): add GmvReport service for GMV Performance report`

### Phase 2 / Task 2: `GmvController` + route + tests

**Files:**
- Create: `app/Http/Controllers/LiveHost/Reports/GmvController.php` (mirror `HostScorecardController` exactly: `index` returns Inertia, `export` streams CSV, prior-period KPI computed by re-running the service against `priorPeriod()`)
- Modify: `routes/web.php` — REPLACE the existing `gmv` coming-soon stub route inside the `Route::prefix('reports')` group with two real routes: `gmv` → `index`, `gmv/export` → `export`.
- Test: `tests/Feature/LiveHost/Reports/GmvControllerTest.php` — auth, Inertia component name `reports/Gmv`, filter echo, CSV export streams.

CSV columns for export: `Host`, `Sessions`, `GMV (MYR)`, `Avg GMV/Session (MYR)`. Plus a separate per-account CSV section is overkill for v1 — owner can re-run with account filter applied to drill in.

Commit message: `feat(live-host): add GmvController with KPI cards and CSV export`

### Phase 2 / Task 3: `Gmv.jsx` page

**Files:**
- Create: `resources/js/livehost/pages/reports/Gmv.jsx` (use the same fragment + TopBar + .layout pattern as HostScorecard.jsx)

**Layout:**

- Header: back link to `/livehost/reports`, title "GMV Performance", `<ExportCsvButton>` pointing at `/livehost/reports/gmv/export`.
- `<ReportFilters>` strip.
- 4 KPI cards: Total GMV (MYR), GMV/session (MYR), Top account (name + amount), Top host (name + amount).
- Multi-line trend chart — daily GMV per account (one line per `accountSeries` entry). Reuse recharts; this is a `LineChart`, not `BarChart`. Add a `MultiLineChart` component to `resources/js/livehost/components/reports/` — keep it small.
- Per-host table: Host (link → `/livehost/hosts/{id}/edit`), Sessions, GMV (MYR), Avg/session (MYR).
- "Top sessions" panel: 10 rows, each linking to `/livehost/sessions/{id}`.

`npm run build` must succeed.

Commit message: `feat(live-host): add GMV Performance report page`

### Phase 2 / Task 4: GMV CSV export + N+1 guard

**Files:**
- Modify: `tests/Feature/LiveHost/Reports/GmvControllerTest.php` — add a CSV-streams test (mirror `HostScorecardExportTest`).
- Modify: `tests/Feature/LiveHost/Reports/GmvReportTest.php` — append the N+1 guard (≤ 5 queries on 20 hosts × 10 sessions).

If `GmvController@export` was already shipped in Task 2, this task is just the additional tests + N+1 guard.

Commit message: `test(live-host): add CSV export coverage and N+1 guard for GMV report`

---

## Phase 3 — Schedule Coverage

**Goal:** Show whether schedule slots are getting filled. Slots = dated `LiveScheduleAssignment` rows (where `is_template = false`).

### Phase 3 / Task 1: `CoverageReport` service + tests

**Files:**
- Create: `app/Services/LiveHost/Reports/CoverageReport.php`
- Create: `app/Services/LiveHost/Reports/CoverageResult.php`
- Test: `tests/Feature/LiveHost/Reports/CoverageReportTest.php`

**Definitions:**

A coverage "slot" = `LiveScheduleAssignment` where `is_template = false` and `schedule_date` is inside the filter window.

For each slot, classify by joining the matching `LiveSession` (matched on `live_schedule_assignment_id` if available, else fall back to `live_schedule_id` + same `schedule_date`):

- `assigned` — slot has a `live_host_id` AND its session has `status` ∈ {`scheduled`, `live`, `ended`}
- `unassigned` — slot has `live_host_id IS NULL` (no host)
- `replaced` — slot has an `assigned` `SessionReplacementRequest` for `target_date = schedule_date` AND the session's `live_host_id` differs from the slot's `live_host_id`
- `missed` — slot's session has `status = 'missed'` OR there is no matching session and the date has passed without an active replacement

Note: a slot can ONLY be in one bucket. Order of precedence when classifying: `unassigned` → `replaced` → `missed` → `assigned`. Document this in a comment on the classifier.

**Result shape:**

```php
class CoverageResult
{
    public function __construct(
        public readonly array $kpis,            // ['percentFilled' => float (0..1), 'unassignedCount' => int, 'replacedCount' => int, 'noShowRate' => float]
        public readonly array $weeklyTrend,     // [['weekStart' => 'YYYY-MM-DD', 'assigned' => int, 'unassigned' => int, 'replaced' => int, 'missed' => int], ...]
        public readonly array $accountRows,     // [['accountId' => int, 'name' => string, 'totalSlots' => int, 'filled' => int, 'unassigned' => int, 'replaced' => int, 'missed' => int, 'coverageRate' => float], ...]
    ) {}
}
```

`percentFilled = (assigned + replaced) / totalSlots` (replaced still counts as filled — someone showed up).

**Required tests:**

1. `it('classifies slots into the four buckets correctly')` — seed one slot of each kind, assert KPIs.
2. `it('groups weekly trend by ISO week start (Monday)')` — ensure week boundaries are stable.
3. `it('produces account rows with correct coverage rate')`
4. `it('respects host and account filters')`
5. N+1 guard.

**Implementation tip:** For the weekly trend, group by `strftime('%Y-%W', schedule_date)` (SQLite) is NOT portable. Instead: select all classified slots, then bucket weeks in PHP using Carbon's `startOfWeek(Carbon::MONDAY)`. Keep SQL portable.

Commit message: `feat(live-host): add CoverageReport service for Schedule Coverage report`

### Phase 3 / Task 2: `CoverageController` + route + tests

Same shape as Phase 2 Task 2 — replace the `coverage` coming-soon stub with real `index`/`export` routes.

Commit message: `feat(live-host): add CoverageController with KPI cards and CSV export`

### Phase 3 / Task 3: `Coverage.jsx` page

**Layout:**

- 4 KPI cards: % filled, unassigned slots, replaced, no-show rate.
- Stacked bar chart (weekly): use the existing `TrendChart` component? Currently it stacks `ended`/`missed` only. Either extend the component or create a `StackedBarChart` that takes a series-config prop. Pick the lower-overhead option. **Recommendation:** add `<StackedBarChart series={[...]} data={...} />` as a new shared component at `resources/js/livehost/components/reports/StackedBarChart.jsx` — generic enough for Phase 4 too.
- Per-account table with coverage % column.
- Drill-down: clicking the unassigned count for a row → `/livehost/session-slots?account=ID&from=...&to=...&unassignedOnly=1` (link only; the session-slots page can ignore extra params for now).

CSV columns: `Account`, `Total Slots`, `Filled`, `Unassigned`, `Replaced`, `Missed`, `Coverage %`.

Commit message: `feat(live-host): add Schedule Coverage report page`

### Phase 3 / Task 4: Coverage CSV export + N+1 guard

Same pattern. Mirror Phase 1 Task 9 / Phase 2 Task 4.

Commit message: `test(live-host): add CSV export coverage and N+1 guard for Coverage report`

---

## Phase 4 — Replacement Activity

**Goal:** Surface who's asking for replacements, who's covering, how fast we're filling them.

### Phase 4 / Task 1: `ReplacementsReport` service + tests

**Files:**
- Create: `app/Services/LiveHost/Reports/ReplacementsReport.php`
- Create: `app/Services/LiveHost/Reports/ReplacementsResult.php`
- Test: `tests/Feature/LiveHost/Reports/ReplacementsReportTest.php`

**Window definition:** filter by `requested_at` between `dateFrom` and `dateTo` (NOT `target_date`). The owner cares about request *activity* in a period, not the dates being covered.

**Result shape:**

```php
class ReplacementsResult
{
    public function __construct(
        public readonly array $kpis,             // ['total' => int, 'fulfilled' => int, 'expired' => int, 'avgTimeToAssignMinutes' => ?float]
        public readonly array $trend,            // [['date' => 'YYYY-MM-DD', 'requested' => int, 'fulfilled' => int], ...]
        public readonly array $topRequesters,    // [['hostId' => int, 'hostName' => string, 'requestCount' => int, 'reasons' => ['sick' => int, 'family' => int, ...]]] top 10
        public readonly array $topCoverers,      // [['hostId' => int, 'hostName' => string, 'coverCount' => int]] top 10
    ) {}
}
```

- `fulfilled` = status `assigned` (someone covered before slot start)
- `expired` = status `expired` (no one assigned in time)
- `avgTimeToAssignMinutes` = average of `(assigned_at - requested_at)` in minutes for status `assigned`. NULL if no fulfilled requests in window.
- `trend.fulfilled` = count of requests requested on day X that ended up `assigned` (regardless of when assigned).

**Required tests:**

1. `it('counts total/fulfilled/expired')`
2. `it('computes avgTimeToAssignMinutes correctly')`
3. `it('groups daily request and fulfillment counts')`
4. `it('produces top requesters and coverers with reason breakdown')`
5. N+1 guard.

Commit message: `feat(live-host): add ReplacementsReport service`

### Phase 4 / Task 2: `ReplacementsController` + route + tests

Same shape. Replace the `replacements` coming-soon stub.

**Important:** the existing PIC page `/livehost/replacements/{id}` is the drill-down target. Don't conflate `/livehost/reports/replacements` (this report) with `/livehost/replacements` (the PIC queue). Two different surfaces.

Commit message: `feat(live-host): add ReplacementsController with KPI cards and CSV export`

### Phase 4 / Task 3: `Replacements.jsx` page

**Layout:**

- 4 KPI cards: Total requests, Fulfilled, Expired, Avg time to assign (formatted as "Xh Ym" or "—").
- Trend chart: daily — two series, `requested` and `fulfilled`. Reuse `<MultiLineChart>` from Phase 2.
- Two side-by-side tables: top requesters (with reason breakdown shown as small inline pills/badges), top coverers.
- Drill-down: tap a request → `/livehost/replacements/{id}` (existing page).

CSV: top requesters AND top coverers in a single CSV with a section divider — or two separate exports? **Decision:** single CSV with `Type,Host,Count,Reasons` columns where Type is `Requester` or `Coverer`. Simpler than two endpoints.

Commit message: `feat(live-host): add Replacement Activity report page`

### Phase 4 / Task 4: CSV export + N+1 guard

Same pattern.

Commit message: `test(live-host): add CSV export coverage and N+1 guard for Replacements report`

---

## Final wire-up Task

**Files:**
- Modify: `app/Http/Controllers/LiveHost/Reports/ReportsController.php` — flip `available: false` to `available: true` for `gmv`, `coverage`, `replacements`.
- Modify: `routes/web.php` — DELETE the `foreach (['gmv', 'coverage', 'replacements'] as $stub) { ... }` coming-soon block (the real routes already replaced each entry one by one in earlier tasks; double-check none remain).
- Modify: `tests/Feature/LiveHost/Reports/ReportsLandingTest.php` — update the third assertion. The current test only asserts `->has('reports', 4)`; that still passes. Add a stronger assertion: `->where('reports.0.available', true)->where('reports.1.available', true)->where('reports.2.available', true)->where('reports.3.available', true)`.
- Optional cleanup: `resources/js/livehost/pages/reports/ComingSoon.jsx` is now unused. Either delete it OR keep for v3 (when more reports get added). Decision: keep — it's small, and useful for any future "coming soon" report.

Run the full suite: `php artisan test --compact tests/Feature/LiveHost/Reports tests/Unit/LiveHost/Reports`. Should be ~30+ tests, all green.

Commit message: `feat(live-host): activate GMV/Coverage/Replacements reports on landing`

---

## Out of scope

- Saved/named report views.
- Period-over-period comparison mode.
- PDF export.
- Per-host commission row data (still deferred from Phase 1).
- Scheduled email digests.
- TikTok-specific viewer/like/watch-time analytics.

These remain out of scope per the design doc §6.

---

## Open product questions (still unresolved from Phase 1)

These don't block implementation but should surface during PR review:

1. Late-start threshold (5 minutes) — applies to Host Scorecard only, but worth confirming.
2. Commission attribution by overlapping payroll-run period — applies to Host Scorecard only.
3. Replacement effect on attendance % — applies to Host Scorecard only.

For Phases 2–4 specifically:

4. **GMV "top session" cap** — currently 10. Owner may want this configurable per page.
5. **Coverage classification precedence** — the rule "unassigned → replaced → missed → assigned" is my judgment call; confirm during review.
6. **Replacement window definition** — uses `requested_at`, not `target_date`. Surface this so the owner knows what they're looking at.

## Phased delivery summary

| Phase | Branch behavior | Tasks | Commits |
|---|---|---|---|
| 2 (GMV) | Straight to main per user pref | 4 | ~4-5 |
| 3 (Coverage) | Straight to main per user pref | 4 | ~4-5 |
| 4 (Replacements) | Straight to main per user pref | 4 | ~4-5 |
| Wire-up | Straight to main per user pref | 1 | 1 |

Total: ~13-16 commits, 30+ new Pest tests, 3 new pages, 3 new services, 3 new controllers, 1-2 new shared frontend components.
