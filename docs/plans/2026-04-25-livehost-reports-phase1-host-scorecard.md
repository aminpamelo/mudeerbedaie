# Live Host Reports — Phase 1 (Host Scorecard) Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Ship the `/livehost/reports` landing page and a fully-featured Host Scorecard report (filters, KPIs, trend chart, sortable table, CSV export, drill-down to host detail).

**Architecture:** Inertia React pages under `resources/js/livehost/pages/reports/`, controllers under `app/Http/Controllers/LiveHost/Reports/`, query/aggregation logic isolated in `app/Services/LiveHost/Reports/` so the same code feeds both the page and the CSV export. Auth via existing `role:admin,admin_livehost` middleware. No new tables, no new columns — pure read layer.

**Tech Stack:** Laravel 12, Inertia 2 + React 19, Tailwind 4, recharts (already installed), Pest 4 for tests.

**Design doc:** [`docs/plans/2026-04-25-livehost-reports-design.md`](2026-04-25-livehost-reports-design.md) — read this first for context, decisions, and out-of-scope items.

**Important conventions (from existing code):**
- `LiveSession.status` values are `scheduled`, `live`, `ended`, `cancelled`, `missed` (NOT `completed`). "Completed" in business terms = `ended`.
- All routes live inside the `Route::middleware(['auth','role:admin,admin_livehost'])->prefix('livehost')->name('livehost.')` group at `routes/web.php:238`.
- Inertia pages render via `Inertia::render('reports/HostScorecard', [...])` — page paths are case-sensitive and resolve to `resources/js/livehost/pages/reports/HostScorecard.jsx`.
- Always `Model::query()`, never `DB::`.
- Run `vendor/bin/pint` before each commit per CLAUDE.md.
- Tests are Pest. Factories already exist for `LiveSession`, `LiveSchedule`, `LiveScheduleAssignment`, `User`, `PlatformAccount`, `LiveHostPayrollRun`, `LiveHostPayrollItem`, `SessionReplacementRequest`.

---

## Task 1: Reports landing page (route + controller + Inertia stub)

**Files:**
- Create: `app/Http/Controllers/LiveHost/Reports/ReportsController.php`
- Create: `resources/js/livehost/pages/reports/Index.jsx`
- Modify: `routes/web.php` (inside the existing `role:admin,admin_livehost` group around line 243)
- Test: `tests/Feature/LiveHost/Reports/ReportsLandingTest.php`

**Step 1: Write the failing test**

```php
<?php

use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

it('redirects guests away from the reports landing', function () {
    get('/livehost/reports')->assertRedirect('/login');
});

it('forbids non-admin users', function () {
    $user = User::factory()->create();
    $user->assignRole('user');

    actingAs($user)->get('/livehost/reports')->assertForbidden();
});

it('renders the reports landing for admin_livehost', function () {
    $user = User::factory()->create();
    $user->assignRole('admin_livehost');

    actingAs($user)
        ->get('/livehost/reports')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('reports/Index')
            ->has('reports', 4)
        );
});
```

**Step 2: Run test to confirm it fails**

Run: `php artisan test --compact tests/Feature/LiveHost/Reports/ReportsLandingTest.php`
Expected: FAIL — route `/livehost/reports` does not exist (404).

**Step 3: Create the controller**

Create `app/Http/Controllers/LiveHost/Reports/ReportsController.php`:

```php
<?php

namespace App\Http\Controllers\LiveHost\Reports;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class ReportsController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('reports/Index', [
            'reports' => [
                [
                    'key' => 'host-scorecard',
                    'title' => 'Host Scorecard',
                    'description' => 'Per-host hours live, GMV, commission, attendance, no-shows.',
                    'href' => '/livehost/reports/host-scorecard',
                    'available' => true,
                ],
                [
                    'key' => 'gmv',
                    'title' => 'GMV Performance',
                    'description' => 'Daily GMV trend by host, account, and platform.',
                    'href' => '/livehost/reports/gmv',
                    'available' => false,
                ],
                [
                    'key' => 'coverage',
                    'title' => 'Schedule Coverage',
                    'description' => 'Slots filled vs unassigned, weekly trend.',
                    'href' => '/livehost/reports/coverage',
                    'available' => false,
                ],
                [
                    'key' => 'replacements',
                    'title' => 'Replacement Activity',
                    'description' => 'Frequency, top requesters and coverers, fulfillment SLA.',
                    'href' => '/livehost/reports/replacements',
                    'available' => false,
                ],
            ],
        ]);
    }
}
```

**Step 4: Register the route**

In `routes/web.php`, inside the `Route::middleware('role:admin,admin_livehost')->group(function () {` block (around line 243, after the `replacements.*` routes), add:

```php
Route::prefix('reports')->name('reports.')->group(function () {
    Route::get('/', [\App\Http\Controllers\LiveHost\Reports\ReportsController::class, 'index'])
        ->name('index');
});
```

**Step 5: Create the Inertia page (stub)**

Create `resources/js/livehost/pages/reports/Index.jsx`:

```jsx
import { Head, Link } from '@inertiajs/react';
import { BarChart3, ChevronRight } from 'lucide-react';
import LiveHostLayout from '@/livehost/layouts/LiveHostLayout';

export default function ReportsIndex({ reports }) {
  return (
    <LiveHostLayout>
      <Head title="Reports" />
      <div className="space-y-6 p-6">
        <header>
          <h1 className="text-2xl font-semibold tracking-tight">Reports</h1>
          <p className="text-sm text-muted-foreground">
            Operational and financial views across the Live Host operation.
          </p>
        </header>
        <div className="grid gap-4 md:grid-cols-2">
          {reports.map((report) => (
            <ReportCard key={report.key} report={report} />
          ))}
        </div>
      </div>
    </LiveHostLayout>
  );
}

function ReportCard({ report }) {
  const className =
    'flex items-start gap-4 rounded-xl border p-5 transition ' +
    (report.available
      ? 'hover:border-foreground/30 hover:bg-muted/50'
      : 'cursor-not-allowed opacity-60');

  const content = (
    <>
      <div className="rounded-lg bg-muted p-2.5">
        <BarChart3 className="size-5" />
      </div>
      <div className="flex-1">
        <div className="flex items-center justify-between">
          <h3 className="font-medium">{report.title}</h3>
          {report.available ? (
            <ChevronRight className="size-4 text-muted-foreground" />
          ) : (
            <span className="text-xs uppercase tracking-wide text-muted-foreground">
              Coming soon
            </span>
          )}
        </div>
        <p className="mt-1 text-sm text-muted-foreground">{report.description}</p>
      </div>
    </>
  );

  return report.available ? (
    <Link href={report.href} className={className}>
      {content}
    </Link>
  ) : (
    <div className={className}>{content}</div>
  );
}
```

**Step 6: Run the tests; expect PASS**

Run: `php artisan test --compact tests/Feature/LiveHost/Reports/ReportsLandingTest.php`
Expected: 3 passing.

**Step 7: Pint + commit**

```bash
vendor/bin/pint --dirty
git add app/Http/Controllers/LiveHost/Reports/ReportsController.php \
        resources/js/livehost/pages/reports/Index.jsx \
        routes/web.php \
        tests/Feature/LiveHost/Reports/ReportsLandingTest.php
git commit -m "feat(live-host): add /livehost/reports landing page"
```

---

## Task 2: Sidebar nav entry

**Files:**
- Modify: `resources/js/livehost/layouts/LiveHostLayout.jsx`

**Step 1: Add `BarChart3` to the lucide imports**

Edit the import block at the top to include `BarChart3`:

```js
import {
  // existing imports...
  BarChart3,
} from 'lucide-react';
```

**Step 2: Add the nav item to the `Records` group**

In the `NAV_GROUPS` array, the `Records` group's `items`, insert **between `sessions` and `commission`**:

```js
{ key: 'reports', label: 'Reports', href: '/livehost/reports', icon: BarChart3 },
```

**Step 3: Add the permission entry**

In the `NAV_ITEM_PERMISSION` map, add (matching the pattern of other read-only items):

```js
reports: null,
```

**Step 4: Verify build**

Run: `npm run build`
Expected: build completes with no errors.

**Step 5: Commit**

```bash
git add resources/js/livehost/layouts/LiveHostLayout.jsx
git commit -m "feat(live-host): add Reports sidebar entry"
```

---

## Task 3: ReportFilters value object + tests

This is the shared filter parser used by every report. Pure value object, no DB.

**Files:**
- Create: `app/Services/LiveHost/Reports/Filters/ReportFilters.php`
- Test: `tests/Unit/LiveHost/Reports/ReportFiltersTest.php`

**Step 1: Write the failing test**

```php
<?php

use App\Services\LiveHost\Reports\Filters\ReportFilters;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

it('defaults to current month when no dates given', function () {
    CarbonImmutable::setTestNow('2026-04-25 10:00:00');
    $request = Request::create('/test');

    $filters = ReportFilters::fromRequest($request);

    expect($filters->dateFrom->toDateString())->toBe('2026-04-01')
        ->and($filters->dateTo->toDateString())->toBe('2026-04-25')
        ->and($filters->hostIds)->toBe([])
        ->and($filters->platformAccountIds)->toBe([]);
});

it('parses explicit dates and arrays', function () {
    $request = Request::create('/test', 'GET', [
        'dateFrom' => '2026-03-01',
        'dateTo' => '2026-03-31',
        'hostIds' => ['1', '2'],
        'platformAccountIds' => ['7'],
    ]);

    $filters = ReportFilters::fromRequest($request);

    expect($filters->dateFrom->toDateString())->toBe('2026-03-01')
        ->and($filters->dateTo->toDateString())->toBe('2026-03-31')
        ->and($filters->hostIds)->toBe([1, 2])
        ->and($filters->platformAccountIds)->toBe([7]);
});

it('rejects an inverted date range', function () {
    $request = Request::create('/test', 'GET', [
        'dateFrom' => '2026-04-30',
        'dateTo' => '2026-04-01',
    ]);

    expect(fn () => ReportFilters::fromRequest($request))
        ->toThrow(\InvalidArgumentException::class);
});

it('computes the prior period of equal length', function () {
    $request = Request::create('/test', 'GET', [
        'dateFrom' => '2026-04-01',
        'dateTo' => '2026-04-10', // 10 days
    ]);
    $filters = ReportFilters::fromRequest($request);

    $prior = $filters->priorPeriod();

    expect($prior->dateFrom->toDateString())->toBe('2026-03-22')
        ->and($prior->dateTo->toDateString())->toBe('2026-03-31');
});
```

**Step 2: Run test, expect FAIL**

Run: `php artisan test --compact tests/Unit/LiveHost/Reports/ReportFiltersTest.php`
Expected: FAIL — class does not exist.

**Step 3: Implement**

Create `app/Services/LiveHost/Reports/Filters/ReportFilters.php`:

```php
<?php

namespace App\Services\LiveHost\Reports\Filters;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use InvalidArgumentException;

class ReportFilters
{
    /**
     * @param  array<int>  $hostIds
     * @param  array<int>  $platformAccountIds
     */
    public function __construct(
        public readonly CarbonImmutable $dateFrom,
        public readonly CarbonImmutable $dateTo,
        public readonly array $hostIds = [],
        public readonly array $platformAccountIds = [],
    ) {
        if ($this->dateFrom->greaterThan($this->dateTo)) {
            throw new InvalidArgumentException('dateFrom must be on or before dateTo.');
        }
    }

    public static function fromRequest(Request $request): self
    {
        $now = CarbonImmutable::now();
        $defaultFrom = $now->startOfMonth();
        $defaultTo = $now->startOfDay();

        $from = $request->query('dateFrom')
            ? CarbonImmutable::parse((string) $request->query('dateFrom'))->startOfDay()
            : $defaultFrom;

        $to = $request->query('dateTo')
            ? CarbonImmutable::parse((string) $request->query('dateTo'))->startOfDay()
            : $defaultTo;

        $hostIds = collect((array) $request->query('hostIds', []))
            ->map(fn ($v) => (int) $v)
            ->filter()
            ->values()
            ->all();

        $platformAccountIds = collect((array) $request->query('platformAccountIds', []))
            ->map(fn ($v) => (int) $v)
            ->filter()
            ->values()
            ->all();

        return new self($from, $to, $hostIds, $platformAccountIds);
    }

    public function priorPeriod(): self
    {
        $days = $this->dateFrom->diffInDays($this->dateTo) + 1;
        $priorTo = $this->dateFrom->subDay();
        $priorFrom = $priorTo->subDays($days - 1);

        return new self($priorFrom, $priorTo, $this->hostIds, $this->platformAccountIds);
    }
}
```

**Step 4: Run, expect PASS**

Run: `php artisan test --compact tests/Unit/LiveHost/Reports/ReportFiltersTest.php`
Expected: 4 passing.

**Step 5: Pint + commit**

```bash
vendor/bin/pint --dirty
git add app/Services/LiveHost/Reports/Filters/ReportFilters.php \
        tests/Unit/LiveHost/Reports/ReportFiltersTest.php
git commit -m "feat(live-host): add ReportFilters value object for reports module"
```

---

## Task 4: HostScorecardReport service — KPI aggregates only

Build the service incrementally: this task does only the four KPI cards. The next task adds the per-host table and trend.

**Files:**
- Create: `app/Services/LiveHost/Reports/HostScorecardReport.php`
- Test: `tests/Feature/LiveHost/Reports/HostScorecardReportTest.php`

**Step 1: Write the failing test**

Each test seeds factories, instantiates the service, and asserts the result-object KPIs.

```php
<?php

use App\Models\LiveHostPayrollItem;
use App\Models\LiveHostPayrollRun;
use App\Models\LiveSession;
use App\Models\PlatformAccount;
use App\Models\User;
use App\Services\LiveHost\Reports\Filters\ReportFilters;
use App\Services\LiveHost\Reports\HostScorecardReport;
use Carbon\CarbonImmutable;

beforeEach(function () {
    CarbonImmutable::setTestNow('2026-04-25 10:00:00');
});

it('aggregates KPI totals for the filter window', function () {
    $host = User::factory()->create();
    $host->assignRole('live_host');
    $account = PlatformAccount::factory()->create();

    LiveSession::factory()->create([
        'live_host_id' => $host->id,
        'platform_account_id' => $account->id,
        'status' => 'ended',
        'scheduled_start_at' => '2026-04-10 08:00:00',
        'actual_start_at' => '2026-04-10 08:00:00',
        'actual_end_at' => '2026-04-10 10:00:00',
        'duration_minutes' => 120,
        'gmv_amount' => 500.00,
        'gmv_adjustment' => 0,
    ]);

    LiveSession::factory()->create([
        'live_host_id' => $host->id,
        'platform_account_id' => $account->id,
        'status' => 'missed',
        'scheduled_start_at' => '2026-04-12 08:00:00',
        'duration_minutes' => 0,
        'gmv_amount' => 0,
    ]);

    // Out-of-window session — must not be counted.
    LiveSession::factory()->create([
        'live_host_id' => $host->id,
        'platform_account_id' => $account->id,
        'status' => 'ended',
        'scheduled_start_at' => '2026-03-30 08:00:00',
        'duration_minutes' => 60,
        'gmv_amount' => 999.00,
    ]);

    $run = LiveHostPayrollRun::factory()->create([
        'period_start' => '2026-04-01',
        'period_end' => '2026-04-30',
    ]);
    LiveHostPayrollItem::factory()->create([
        'payroll_run_id' => $run->id,
        'user_id' => $host->id,
        'gross_total_myr' => 80.00,
    ]);

    $filters = new ReportFilters(
        CarbonImmutable::parse('2026-04-01'),
        CarbonImmutable::parse('2026-04-25'),
    );
    $report = (new HostScorecardReport)->run($filters);

    expect($report->kpis['totalHours'])->toBe(2.0)
        ->and($report->kpis['totalGmv'])->toEqualWithDelta(500.00, 0.01)
        ->and($report->kpis['totalCommission'])->toEqualWithDelta(80.00, 0.01)
        ->and($report->kpis['attendanceRate'])->toEqualWithDelta(0.5, 0.001); // 1 of 2 in window ended
});

it('returns zeroed KPIs when no sessions exist in window', function () {
    $filters = new ReportFilters(
        CarbonImmutable::parse('2026-04-01'),
        CarbonImmutable::parse('2026-04-25'),
    );

    $report = (new HostScorecardReport)->run($filters);

    expect($report->kpis['totalHours'])->toBe(0.0)
        ->and($report->kpis['totalGmv'])->toBe(0.0)
        ->and($report->kpis['totalCommission'])->toBe(0.0)
        ->and($report->kpis['attendanceRate'])->toBe(0.0);
});

it('respects host filter', function () {
    $h1 = User::factory()->create(); $h1->assignRole('live_host');
    $h2 = User::factory()->create(); $h2->assignRole('live_host');
    $account = PlatformAccount::factory()->create();

    foreach ([$h1, $h2] as $h) {
        LiveSession::factory()->create([
            'live_host_id' => $h->id,
            'platform_account_id' => $account->id,
            'status' => 'ended',
            'scheduled_start_at' => '2026-04-10 08:00:00',
            'duration_minutes' => 60,
            'gmv_amount' => 100.00,
        ]);
    }

    $filters = new ReportFilters(
        CarbonImmutable::parse('2026-04-01'),
        CarbonImmutable::parse('2026-04-25'),
        hostIds: [$h1->id],
    );

    $report = (new HostScorecardReport)->run($filters);

    expect($report->kpis['totalGmv'])->toEqualWithDelta(100.00, 0.01);
});
```

**Step 2: Run, expect FAIL**

Run: `php artisan test --compact tests/Feature/LiveHost/Reports/HostScorecardReportTest.php`
Expected: FAIL (class missing).

**Step 3: Implement the service**

Create `app/Services/LiveHost/Reports/HostScorecardReport.php`:

```php
<?php

namespace App\Services\LiveHost\Reports;

use App\Models\LiveHostPayrollItem;
use App\Models\LiveSession;
use App\Services\LiveHost\Reports\Filters\ReportFilters;

class HostScorecardReport
{
    public function run(ReportFilters $filters): HostScorecardResult
    {
        $sessionsQuery = LiveSession::query()
            ->whereBetween('scheduled_start_at', [
                $filters->dateFrom->startOfDay(),
                $filters->dateTo->endOfDay(),
            ])
            ->when($filters->hostIds, fn ($q, $ids) => $q->whereIn('live_host_id', $ids))
            ->when($filters->platformAccountIds, fn ($q, $ids) => $q->whereIn('platform_account_id', $ids));

        $aggregates = (clone $sessionsQuery)
            ->selectRaw("
                COALESCE(SUM(CASE WHEN status = 'ended' THEN duration_minutes ELSE 0 END), 0) as total_minutes,
                COALESCE(SUM(CASE WHEN status = 'ended' THEN gmv_amount + COALESCE(gmv_adjustment, 0) ELSE 0 END), 0) as total_gmv,
                COUNT(*) as total_sessions,
                SUM(CASE WHEN status = 'ended' THEN 1 ELSE 0 END) as ended_sessions
            ")
            ->first();

        $totalHours = (float) $aggregates->total_minutes / 60.0;
        $totalGmv = (float) $aggregates->total_gmv;
        $totalSessions = (int) $aggregates->total_sessions;
        $endedSessions = (int) $aggregates->ended_sessions;
        $attendance = $totalSessions > 0 ? $endedSessions / $totalSessions : 0.0;

        $totalCommission = (float) LiveHostPayrollItem::query()
            ->whereHas('payrollRun', function ($q) use ($filters) {
                $q->where('period_start', '<=', $filters->dateTo->toDateString())
                  ->where('period_end', '>=', $filters->dateFrom->toDateString());
            })
            ->when($filters->hostIds, fn ($q, $ids) => $q->whereIn('user_id', $ids))
            ->sum('gross_total_myr');

        return new HostScorecardResult(
            kpis: [
                'totalHours' => round($totalHours, 2),
                'totalGmv' => round($totalGmv, 2),
                'totalCommission' => round($totalCommission, 2),
                'attendanceRate' => round($attendance, 4),
            ],
            rows: [],
            trend: [],
        );
    }
}
```

Create `app/Services/LiveHost/Reports/HostScorecardResult.php`:

```php
<?php

namespace App\Services\LiveHost\Reports;

class HostScorecardResult
{
    /**
     * @param  array{totalHours: float, totalGmv: float, totalCommission: float, attendanceRate: float}  $kpis
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<int, array{date: string, ended: int, missed: int}>  $trend
     */
    public function __construct(
        public readonly array $kpis,
        public readonly array $rows,
        public readonly array $trend,
    ) {}
}
```

**Step 4: Run tests, expect PASS**

Run: `php artisan test --compact tests/Feature/LiveHost/Reports/HostScorecardReportTest.php`
Expected: 3 passing.

**Step 5: Pint + commit**

```bash
vendor/bin/pint --dirty
git add app/Services/LiveHost/Reports/HostScorecardReport.php \
        app/Services/LiveHost/Reports/HostScorecardResult.php \
        tests/Feature/LiveHost/Reports/HostScorecardReportTest.php
git commit -m "feat(live-host): add HostScorecardReport service with KPI aggregates"
```

---

## Task 5: HostScorecardReport — per-host rows + trend

Extend the same service to populate `rows` (one per host) and `trend` (one per day).

**Files:**
- Modify: `app/Services/LiveHost/Reports/HostScorecardReport.php`
- Modify: `tests/Feature/LiveHost/Reports/HostScorecardReportTest.php` (add tests)

**Step 1: Add failing tests**

Append to the test file:

```php
it('produces per-host rows with all metrics', function () {
    $host = User::factory()->create(['name' => 'Sarah Chen']);
    $host->assignRole('live_host');
    $account = PlatformAccount::factory()->create();

    LiveSession::factory()->count(2)->create([
        'live_host_id' => $host->id,
        'platform_account_id' => $account->id,
        'status' => 'ended',
        'scheduled_start_at' => '2026-04-10 08:00:00',
        'actual_start_at' => '2026-04-10 08:00:00',
        'duration_minutes' => 60,
        'gmv_amount' => 200.00,
    ]);

    LiveSession::factory()->create([
        'live_host_id' => $host->id,
        'platform_account_id' => $account->id,
        'status' => 'missed',
        'scheduled_start_at' => '2026-04-15 08:00:00',
        'duration_minutes' => 0,
    ]);

    $filters = new ReportFilters(
        CarbonImmutable::parse('2026-04-01'),
        CarbonImmutable::parse('2026-04-25'),
    );

    $rows = (new HostScorecardReport)->run($filters)->rows;

    expect($rows)->toHaveCount(1);
    $row = $rows[0];
    expect($row['hostId'])->toBe($host->id)
        ->and($row['hostName'])->toBe('Sarah Chen')
        ->and($row['sessionsScheduled'])->toBe(3)
        ->and($row['sessionsEnded'])->toBe(2)
        ->and($row['hoursLive'])->toBe(2.0)
        ->and($row['gmv'])->toEqualWithDelta(400.00, 0.01)
        ->and($row['noShows'])->toBe(1)
        ->and($row['attendanceRate'])->toEqualWithDelta(2/3, 0.001);
});

it('produces a daily trend bucket for ended/missed', function () {
    $host = User::factory()->create();
    $host->assignRole('live_host');
    $account = PlatformAccount::factory()->create();

    LiveSession::factory()->create([
        'live_host_id' => $host->id,
        'platform_account_id' => $account->id,
        'status' => 'ended',
        'scheduled_start_at' => '2026-04-10 08:00:00',
        'duration_minutes' => 60,
    ]);
    LiveSession::factory()->create([
        'live_host_id' => $host->id,
        'platform_account_id' => $account->id,
        'status' => 'missed',
        'scheduled_start_at' => '2026-04-10 14:00:00',
        'duration_minutes' => 0,
    ]);

    $filters = new ReportFilters(
        CarbonImmutable::parse('2026-04-10'),
        CarbonImmutable::parse('2026-04-10'),
    );

    $trend = (new HostScorecardReport)->run($filters)->trend;

    expect($trend)->toHaveCount(1)
        ->and($trend[0]['date'])->toBe('2026-04-10')
        ->and($trend[0]['ended'])->toBe(1)
        ->and($trend[0]['missed'])->toBe(1);
});
```

**Step 2: Run, expect FAIL** (rows empty, trend empty).

**Step 3: Extend the service**

Replace `HostScorecardReport->run()` body so `$rows` and `$trend` are populated. Add a private helper:

```php
private function rowsFor(ReportFilters $filters): array
{
    return LiveSession::query()
        ->whereBetween('scheduled_start_at', [
            $filters->dateFrom->startOfDay(),
            $filters->dateTo->endOfDay(),
        ])
        ->when($filters->hostIds, fn ($q, $ids) => $q->whereIn('live_host_id', $ids))
        ->when($filters->platformAccountIds, fn ($q, $ids) => $q->whereIn('platform_account_id', $ids))
        ->whereNotNull('live_host_id')
        ->join('users', 'users.id', '=', 'live_sessions.live_host_id')
        ->groupBy('live_sessions.live_host_id', 'users.name')
        ->selectRaw("
            live_sessions.live_host_id as host_id,
            users.name as host_name,
            COUNT(*) as sessions_scheduled,
            SUM(CASE WHEN status = 'ended' THEN 1 ELSE 0 END) as sessions_ended,
            SUM(CASE WHEN status = 'missed' THEN 1 ELSE 0 END) as no_shows,
            COALESCE(SUM(CASE WHEN status = 'ended' THEN duration_minutes ELSE 0 END), 0) as ended_minutes,
            COALESCE(SUM(CASE WHEN status = 'ended' THEN gmv_amount + COALESCE(gmv_adjustment, 0) ELSE 0 END), 0) as gmv,
            SUM(
                CASE
                    WHEN actual_start_at IS NOT NULL
                     AND status = 'ended'
                     AND CAST((julianday(actual_start_at) - julianday(scheduled_start_at)) * 24 * 60 AS INTEGER) > 5
                    THEN 1 ELSE 0
                END
            ) as late_starts
        ")
        ->orderByDesc('gmv')
        ->get()
        ->map(function ($r) {
            $hours = (float) $r->ended_minutes / 60.0;
            return [
                'hostId' => (int) $r->host_id,
                'hostName' => $r->host_name,
                'sessionsScheduled' => (int) $r->sessions_scheduled,
                'sessionsEnded' => (int) $r->sessions_ended,
                'hoursLive' => round($hours, 2),
                'gmv' => round((float) $r->gmv, 2),
                'avgGmvPerHour' => $hours > 0 ? round((float) $r->gmv / $hours, 2) : 0.0,
                'noShows' => (int) $r->no_shows,
                'lateStarts' => (int) $r->late_starts,
                'attendanceRate' => $r->sessions_scheduled > 0
                    ? round($r->sessions_ended / $r->sessions_scheduled, 4)
                    : 0.0,
            ];
        })
        ->all();
}

private function trendFor(ReportFilters $filters): array
{
    return LiveSession::query()
        ->whereBetween('scheduled_start_at', [
            $filters->dateFrom->startOfDay(),
            $filters->dateTo->endOfDay(),
        ])
        ->when($filters->hostIds, fn ($q, $ids) => $q->whereIn('live_host_id', $ids))
        ->when($filters->platformAccountIds, fn ($q, $ids) => $q->whereIn('platform_account_id', $ids))
        ->selectRaw("
            DATE(scheduled_start_at) as day,
            SUM(CASE WHEN status = 'ended' THEN 1 ELSE 0 END) as ended,
            SUM(CASE WHEN status = 'missed' THEN 1 ELSE 0 END) as missed
        ")
        ->groupBy('day')
        ->orderBy('day')
        ->get()
        ->map(fn ($r) => [
            'date' => (string) $r->day,
            'ended' => (int) $r->ended,
            'missed' => (int) $r->missed,
        ])
        ->all();
}
```

> **Note on `julianday`:** that function is SQLite-specific. Per CLAUDE.md, MySQL is production. Use a portable late-start calculation:
> ```php
> 'late_starts' => SUM(CASE WHEN actual_start_at IS NOT NULL
>                            AND status = 'ended'
>                            AND actual_start_at > DATE_ADD(scheduled_start_at, INTERVAL 5 MINUTE)
>                            THEN 1 ELSE 0 END)
> ```
> But this differs across drivers too. The clean fix is to compute lateness in PHP after fetching, or use `whereRaw` with a driver branch via `DB::getDriverName()`. **Implement the PHP-side approach for late_starts**: drop the late_starts SQL, instead fetch a separate small query with `actual_start_at` and `scheduled_start_at` for ended sessions and count in PHP. This keeps the SQL portable.

Replace the late_starts SQL block with a separate query in the service:

```php
private function lateStartsByHost(ReportFilters $filters): array
{
    return LiveSession::query()
        ->whereBetween('scheduled_start_at', [
            $filters->dateFrom->startOfDay(),
            $filters->dateTo->endOfDay(),
        ])
        ->where('status', 'ended')
        ->whereNotNull('actual_start_at')
        ->when($filters->hostIds, fn ($q, $ids) => $q->whereIn('live_host_id', $ids))
        ->get(['live_host_id', 'scheduled_start_at', 'actual_start_at'])
        ->groupBy('live_host_id')
        ->map(fn ($group) => $group->filter(
            fn ($s) => $s->actual_start_at->diffInMinutes($s->scheduled_start_at, false) < -5
        )->count())
        ->all();
}
```

Then merge into rows:

```php
$lateStarts = $this->lateStartsByHost($filters);
$rows = array_map(fn ($row) => [
    ...$row,
    'lateStarts' => $lateStarts[$row['hostId']] ?? 0,
], $rowsWithoutLateness);
```

(Drop the `late_starts` line from the `selectRaw`.)

**Step 4: Run all report tests**

Run: `php artisan test --compact tests/Feature/LiveHost/Reports/HostScorecardReportTest.php`
Expected: all passing.

**Step 5: Pint + commit**

```bash
vendor/bin/pint --dirty
git add app/Services/LiveHost/Reports/HostScorecardReport.php \
        tests/Feature/LiveHost/Reports/HostScorecardReportTest.php
git commit -m "feat(live-host): add per-host rows and daily trend to HostScorecardReport"
```

---

## Task 6: HostScorecardController + route + filter options

**Files:**
- Create: `app/Http/Controllers/LiveHost/Reports/HostScorecardController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/LiveHost/Reports/HostScorecardControllerTest.php`

**Step 1: Write failing test**

```php
<?php

use App\Models\LiveSession;
use App\Models\PlatformAccount;
use App\Models\User;
use Carbon\CarbonImmutable;

use function Pest\Laravel\actingAs;

beforeEach(fn () => CarbonImmutable::setTestNow('2026-04-25 10:00:00'));

it('forbids unauthorised users', function () {
    $user = User::factory()->create();
    $user->assignRole('user');

    actingAs($user)->get('/livehost/reports/host-scorecard')->assertForbidden();
});

it('renders Inertia page with kpis, rows, trend, filter options', function () {
    $admin = User::factory()->create(); $admin->assignRole('admin_livehost');
    $host = User::factory()->create(['name' => 'Sarah Chen']);
    $host->assignRole('live_host');
    $account = PlatformAccount::factory()->create();

    LiveSession::factory()->create([
        'live_host_id' => $host->id,
        'platform_account_id' => $account->id,
        'status' => 'ended',
        'scheduled_start_at' => '2026-04-10 08:00:00',
        'duration_minutes' => 60,
        'gmv_amount' => 100,
    ]);

    actingAs($admin)
        ->get('/livehost/reports/host-scorecard')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('reports/HostScorecard')
            ->has('kpis')
            ->has('rows', 1)
            ->has('trend')
            ->has('filterOptions.hosts')
            ->has('filterOptions.platformAccounts')
            ->has('filters')
        );
});

it('honours dateFrom/dateTo query params', function () {
    $admin = User::factory()->create(); $admin->assignRole('admin_livehost');

    actingAs($admin)
        ->get('/livehost/reports/host-scorecard?dateFrom=2026-03-01&dateTo=2026-03-31')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('filters.dateFrom', '2026-03-01')
            ->where('filters.dateTo', '2026-03-31')
        );
});
```

**Step 2: Run, expect FAIL**

Run: `php artisan test --compact tests/Feature/LiveHost/Reports/HostScorecardControllerTest.php`

**Step 3: Implement controller**

Create `app/Http/Controllers/LiveHost/Reports/HostScorecardController.php`:

```php
<?php

namespace App\Http\Controllers\LiveHost\Reports;

use App\Http\Controllers\Controller;
use App\Models\PlatformAccount;
use App\Models\User;
use App\Services\LiveHost\Reports\Filters\ReportFilters;
use App\Services\LiveHost\Reports\HostScorecardReport;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HostScorecardController extends Controller
{
    public function index(Request $request, HostScorecardReport $report): Response
    {
        $filters = ReportFilters::fromRequest($request);
        $result = $report->run($filters);
        $prior = $report->run($filters->priorPeriod());

        return Inertia::render('reports/HostScorecard', [
            'kpis' => [
                'current' => $result->kpis,
                'prior' => $prior->kpis,
            ],
            'rows' => $result->rows,
            'trend' => $result->trend,
            'filters' => [
                'dateFrom' => $filters->dateFrom->toDateString(),
                'dateTo' => $filters->dateTo->toDateString(),
                'hostIds' => $filters->hostIds,
                'platformAccountIds' => $filters->platformAccountIds,
            ],
            'filterOptions' => [
                'hosts' => User::query()
                    ->whereHas('roles', fn ($q) => $q->where('name', 'live_host'))
                    ->orderBy('name')
                    ->get(['id', 'name'])
                    ->map(fn ($u) => ['id' => $u->id, 'name' => $u->name])
                    ->all(),
                'platformAccounts' => PlatformAccount::query()
                    ->orderBy('name')
                    ->get(['id', 'name'])
                    ->map(fn ($a) => ['id' => $a->id, 'name' => $a->name])
                    ->all(),
            ],
        ]);
    }
}
```

**Step 4: Add the route**

Inside the `Route::prefix('reports')` group from Task 1, add:

```php
Route::get('host-scorecard', [\App\Http\Controllers\LiveHost\Reports\HostScorecardController::class, 'index'])
    ->name('host-scorecard.index');
```

**Step 5: Run, expect PASS**

Run: `php artisan test --compact tests/Feature/LiveHost/Reports/HostScorecardControllerTest.php`

**Step 6: Pint + commit**

```bash
vendor/bin/pint --dirty
git add app/Http/Controllers/LiveHost/Reports/HostScorecardController.php \
        routes/web.php \
        tests/Feature/LiveHost/Reports/HostScorecardControllerTest.php
git commit -m "feat(live-host): add HostScorecardController with filters and prior-period KPI"
```

---

## Task 7: Shared frontend components for reports

Build the small primitives once. They're used by all 4 phases.

**Files:**
- Create: `resources/js/livehost/components/reports/DateRangePicker.jsx`
- Create: `resources/js/livehost/components/reports/ReportFilters.jsx`
- Create: `resources/js/livehost/components/reports/KpiCard.jsx`
- Create: `resources/js/livehost/components/reports/TrendChart.jsx`
- Create: `resources/js/livehost/components/reports/ExportCsvButton.jsx`

**Step 1: `KpiCard.jsx`**

```jsx
import { ArrowDown, ArrowUp, Minus } from 'lucide-react';

export default function KpiCard({ label, value, delta, format = (v) => v }) {
  const direction = delta == null ? 'flat' : delta > 0 ? 'up' : delta < 0 ? 'down' : 'flat';
  const Icon = direction === 'up' ? ArrowUp : direction === 'down' ? ArrowDown : Minus;
  const tone =
    direction === 'up'
      ? 'text-emerald-600'
      : direction === 'down'
        ? 'text-rose-600'
        : 'text-muted-foreground';

  return (
    <div className="rounded-xl border bg-card p-5">
      <div className="text-xs font-medium uppercase tracking-wide text-muted-foreground">
        {label}
      </div>
      <div className="mt-2 text-2xl font-semibold">{format(value)}</div>
      {delta != null && (
        <div className={`mt-1 flex items-center gap-1 text-xs ${tone}`}>
          <Icon className="size-3" />
          <span>{Math.abs(delta).toFixed(1)}% vs prior period</span>
        </div>
      )}
    </div>
  );
}
```

**Step 2: `DateRangePicker.jsx`**

A small preset-button row + custom inputs. Emits `{ dateFrom, dateTo }` strings via `onChange`.

```jsx
import { useMemo } from 'react';
import { Button } from '@/livehost/components/ui/button';

const PRESETS = [
  { key: 'today', label: 'Today' },
  { key: 'thisWeek', label: 'This week' },
  { key: 'thisMonth', label: 'This month' },
  { key: 'lastMonth', label: 'Last month' },
  { key: 'last30', label: 'Last 30 days' },
];

function preset(key) {
  const today = new Date();
  const iso = (d) => d.toISOString().slice(0, 10);
  const start = new Date(today);
  if (key === 'today') return { dateFrom: iso(today), dateTo: iso(today) };
  if (key === 'thisWeek') {
    const day = today.getDay() || 7;
    start.setDate(today.getDate() - day + 1);
    return { dateFrom: iso(start), dateTo: iso(today) };
  }
  if (key === 'thisMonth') {
    start.setDate(1);
    return { dateFrom: iso(start), dateTo: iso(today) };
  }
  if (key === 'lastMonth') {
    const first = new Date(today.getFullYear(), today.getMonth() - 1, 1);
    const last = new Date(today.getFullYear(), today.getMonth(), 0);
    return { dateFrom: iso(first), dateTo: iso(last) };
  }
  if (key === 'last30') {
    start.setDate(today.getDate() - 29);
    return { dateFrom: iso(start), dateTo: iso(today) };
  }
}

export default function DateRangePicker({ value, onChange }) {
  const presetMatches = useMemo(() => {
    for (const p of PRESETS) {
      const r = preset(p.key);
      if (r.dateFrom === value.dateFrom && r.dateTo === value.dateTo) return p.key;
    }
    return null;
  }, [value]);

  return (
    <div className="flex flex-wrap items-center gap-2">
      {PRESETS.map((p) => (
        <Button
          key={p.key}
          variant={presetMatches === p.key ? 'default' : 'outline'}
          size="sm"
          onClick={() => onChange(preset(p.key))}
        >
          {p.label}
        </Button>
      ))}
      <div className="ml-2 flex items-center gap-2">
        <input
          type="date"
          value={value.dateFrom}
          onChange={(e) => onChange({ ...value, dateFrom: e.target.value })}
          className="h-9 rounded-md border bg-background px-2 text-sm"
        />
        <span className="text-muted-foreground">→</span>
        <input
          type="date"
          value={value.dateTo}
          onChange={(e) => onChange({ ...value, dateTo: e.target.value })}
          className="h-9 rounded-md border bg-background px-2 text-sm"
        />
      </div>
    </div>
  );
}
```

**Step 3: `TrendChart.jsx`**

```jsx
import {
  Bar,
  BarChart,
  CartesianGrid,
  Legend,
  ResponsiveContainer,
  Tooltip,
  XAxis,
  YAxis,
} from 'recharts';

export default function TrendChart({ data, height = 240 }) {
  return (
    <div className="rounded-xl border bg-card p-4">
      <div className="text-sm font-medium">Daily session counts</div>
      <div className="mt-3" style={{ height }}>
        <ResponsiveContainer width="100%" height="100%">
          <BarChart data={data}>
            <CartesianGrid strokeDasharray="3 3" vertical={false} />
            <XAxis dataKey="date" fontSize={11} />
            <YAxis fontSize={11} allowDecimals={false} />
            <Tooltip />
            <Legend />
            <Bar dataKey="ended" stackId="a" name="Ended" fill="#10b981" />
            <Bar dataKey="missed" stackId="a" name="Missed" fill="#ef4444" />
          </BarChart>
        </ResponsiveContainer>
      </div>
    </div>
  );
}
```

**Step 4: `ReportFilters.jsx` and `ExportCsvButton.jsx`**

`ReportFilters.jsx` — combines `DateRangePicker` + two multi-selects. Uses Inertia `router.get(currentUrl, params, { preserveState: true })` to refetch:

```jsx
import { router } from '@inertiajs/react';
import DateRangePicker from './DateRangePicker';

export default function ReportFilters({ filters, options, basePath }) {
  const apply = (next) => {
    router.get(basePath, { ...filters, ...next }, {
      preserveState: true,
      preserveScroll: true,
      replace: true,
    });
  };

  return (
    <div className="flex flex-wrap items-center gap-3 rounded-xl border bg-card p-4">
      <DateRangePicker
        value={{ dateFrom: filters.dateFrom, dateTo: filters.dateTo }}
        onChange={apply}
      />
      <MultiSelect
        label="Hosts"
        options={options.hosts}
        selected={filters.hostIds}
        onChange={(ids) => apply({ hostIds: ids })}
      />
      <MultiSelect
        label="Accounts"
        options={options.platformAccounts}
        selected={filters.platformAccountIds}
        onChange={(ids) => apply({ platformAccountIds: ids })}
      />
    </div>
  );
}

function MultiSelect({ label, options, selected, onChange }) {
  // Minimal native-select multi for v1. Replace with Radix Select in v2 if needed.
  return (
    <label className="flex items-center gap-2 text-sm">
      <span className="text-muted-foreground">{label}:</span>
      <select
        multiple
        value={selected.map(String)}
        onChange={(e) => onChange(Array.from(e.target.selectedOptions).map((o) => Number(o.value)))}
        className="h-9 min-w-32 rounded-md border bg-background px-2 text-sm"
      >
        {options.map((o) => (
          <option key={o.id} value={o.id}>{o.name}</option>
        ))}
      </select>
    </label>
  );
}
```

`ExportCsvButton.jsx`:

```jsx
import { Download } from 'lucide-react';
import { Button } from '@/livehost/components/ui/button';

export default function ExportCsvButton({ exportPath, filters }) {
  const params = new URLSearchParams();
  Object.entries(filters).forEach(([key, value]) => {
    if (Array.isArray(value)) value.forEach((v) => params.append(`${key}[]`, v));
    else if (value != null) params.append(key, value);
  });
  const href = `${exportPath}?${params.toString()}`;

  return (
    <Button asChild variant="outline" size="sm">
      <a href={href} download>
        <Download className="mr-1.5 size-4" /> Export CSV
      </a>
    </Button>
  );
}
```

**Step 5: Verify build**

Run: `npm run build`
Expected: success.

**Step 6: Commit**

```bash
git add resources/js/livehost/components/reports/
git commit -m "feat(live-host): add shared report components (filters, KPI, chart, export)"
```

---

## Task 8: HostScorecard React page

**Files:**
- Create: `resources/js/livehost/pages/reports/HostScorecard.jsx`

**Step 1: Implement**

```jsx
import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import LiveHostLayout from '@/livehost/layouts/LiveHostLayout';
import KpiCard from '@/livehost/components/reports/KpiCard';
import TrendChart from '@/livehost/components/reports/TrendChart';
import ReportFilters from '@/livehost/components/reports/ReportFilters';
import ExportCsvButton from '@/livehost/components/reports/ExportCsvButton';
import { Button } from '@/livehost/components/ui/button';

const fmtMyr = (n) => `RM ${Number(n).toLocaleString('en-MY', { minimumFractionDigits: 2 })}`;
const fmtPct = (n) => `${(Number(n) * 100).toFixed(1)}%`;
const fmtHours = (n) => `${Number(n).toFixed(1)} hr`;

function delta(current, prior) {
  if (!prior) return null;
  return ((current - prior) / prior) * 100;
}

export default function HostScorecard({ kpis, rows, trend, filters, filterOptions }) {
  return (
    <LiveHostLayout>
      <Head title="Host Scorecard" />
      <div className="space-y-6 p-6">
        <header className="flex items-start justify-between gap-4">
          <div>
            <Link
              href="/livehost/reports"
              className="inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground"
            >
              <ArrowLeft className="size-3" /> Reports
            </Link>
            <h1 className="mt-1 text-2xl font-semibold tracking-tight">Host Scorecard</h1>
            <p className="text-sm text-muted-foreground">
              Per-host hours live, GMV, commission, attendance.
            </p>
          </div>
          <ExportCsvButton
            exportPath="/livehost/reports/host-scorecard/export"
            filters={filters}
          />
        </header>

        <ReportFilters
          filters={filters}
          options={filterOptions}
          basePath="/livehost/reports/host-scorecard"
        />

        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <KpiCard
            label="Total live hours"
            value={kpis.current.totalHours}
            delta={delta(kpis.current.totalHours, kpis.prior.totalHours)}
            format={fmtHours}
          />
          <KpiCard
            label="Total GMV"
            value={kpis.current.totalGmv}
            delta={delta(kpis.current.totalGmv, kpis.prior.totalGmv)}
            format={fmtMyr}
          />
          <KpiCard
            label="Commission paid"
            value={kpis.current.totalCommission}
            delta={delta(kpis.current.totalCommission, kpis.prior.totalCommission)}
            format={fmtMyr}
          />
          <KpiCard
            label="Attendance rate"
            value={kpis.current.attendanceRate}
            delta={delta(kpis.current.attendanceRate, kpis.prior.attendanceRate)}
            format={fmtPct}
          />
        </div>

        <TrendChart data={trend} />

        <ScorecardTable rows={rows} />
      </div>
    </LiveHostLayout>
  );
}

function ScorecardTable({ rows }) {
  if (rows.length === 0) {
    return (
      <div className="rounded-xl border bg-card p-10 text-center text-sm text-muted-foreground">
        No host activity in this date range.
      </div>
    );
  }

  return (
    <div className="overflow-hidden rounded-xl border bg-card">
      <table className="w-full text-sm">
        <thead className="bg-muted/50 text-xs uppercase tracking-wide text-muted-foreground">
          <tr>
            <Th>Host</Th>
            <Th align="right">Sched</Th>
            <Th align="right">Ended</Th>
            <Th align="right">Hours</Th>
            <Th align="right">GMV</Th>
            <Th align="right">Avg/hr</Th>
            <Th align="right">Comm.</Th>
            <Th align="right">No-shows</Th>
            <Th align="right">Late</Th>
            <Th align="right">Att%</Th>
          </tr>
        </thead>
        <tbody>
          {rows.map((r) => (
            <tr key={r.hostId} className="border-t">
              <td className="px-4 py-2.5">
                <Link
                  href={`/livehost/hosts/${r.hostId}/edit`}
                  className="font-medium hover:underline"
                >
                  {r.hostName}
                </Link>
              </td>
              <Td align="right">{r.sessionsScheduled}</Td>
              <Td align="right">{r.sessionsEnded}</Td>
              <Td align="right">{r.hoursLive.toFixed(1)}</Td>
              <Td align="right">{fmtMyr(r.gmv)}</Td>
              <Td align="right">{fmtMyr(r.avgGmvPerHour)}</Td>
              <Td align="right">{fmtMyr(r.commissionEarned ?? 0)}</Td>
              <Td align="right">{r.noShows}</Td>
              <Td align="right">{r.lateStarts}</Td>
              <Td align="right">{fmtPct(r.attendanceRate)}</Td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}

function Th({ children, align = 'left' }) {
  return <th className={`px-4 py-2.5 text-${align} font-medium`}>{children}</th>;
}
function Td({ children, align = 'left' }) {
  return <td className={`px-4 py-2.5 text-${align}`}>{children}</td>;
}
```

> **Note:** the table renders `commissionEarned` per-row, but the service from Task 5 doesn't yet emit it (it only emits aggregate commission in KPIs). For row-level commission, in **Task 5b (below) — fold into Task 5 if not yet committed, otherwise a follow-up commit** — extend `rowsFor()` to add `commissionEarned` by joining `live_host_payroll_items` filtered to runs in window. For v1 if rushed, send `commissionEarned: 0` and ship — annotate with a TODO.

**Step 2: Verify build + page renders**

Run: `npm run build`
Expected: success.

Boot: `composer run dev`, visit `https://mudeerbedaie.test/livehost/reports/host-scorecard` (use `mcp__laravel-boost__get-absolute-url` if unsure of the URL).
Expected: page renders, KPIs show, chart renders, table populated by seed data.

**Step 3: Commit**

```bash
git add resources/js/livehost/pages/reports/HostScorecard.jsx
git commit -m "feat(live-host): add Host Scorecard React page"
```

---

## Task 9: CSV export endpoint

**Files:**
- Modify: `app/Http/Controllers/LiveHost/Reports/HostScorecardController.php` (add `export` method)
- Modify: `routes/web.php`
- Test: `tests/Feature/LiveHost/Reports/HostScorecardExportTest.php`

**Step 1: Failing test**

```php
<?php

use App\Models\LiveSession;
use App\Models\PlatformAccount;
use App\Models\User;
use Carbon\CarbonImmutable;

use function Pest\Laravel\actingAs;

beforeEach(fn () => CarbonImmutable::setTestNow('2026-04-25 10:00:00'));

it('streams a CSV with header + one row per host', function () {
    $admin = User::factory()->create(); $admin->assignRole('admin_livehost');
    $host = User::factory()->create(['name' => 'Sarah Chen']);
    $host->assignRole('live_host');
    $account = PlatformAccount::factory()->create();

    LiveSession::factory()->create([
        'live_host_id' => $host->id,
        'platform_account_id' => $account->id,
        'status' => 'ended',
        'scheduled_start_at' => '2026-04-10 08:00:00',
        'duration_minutes' => 120,
        'gmv_amount' => 500.00,
    ]);

    $response = actingAs($admin)
        ->get('/livehost/reports/host-scorecard/export?dateFrom=2026-04-01&dateTo=2026-04-25')
        ->assertOk();

    expect($response->headers->get('Content-Type'))->toContain('text/csv');
    $content = $response->streamedContent();
    $lines = array_values(array_filter(explode("\n", $content)));
    expect($lines[0])->toContain('Host')->toContain('GMV');
    expect($lines[1])->toContain('Sarah Chen')->toContain('500');
});
```

**Step 2: Run, expect FAIL**

**Step 3: Implement `export` method**

Add to `HostScorecardController`:

```php
public function export(Request $request, HostScorecardReport $report): StreamedResponse
{
    $filters = ReportFilters::fromRequest($request);
    $result = $report->run($filters);

    $filename = sprintf(
        'host-scorecard_%s_%s.csv',
        $filters->dateFrom->toDateString(),
        $filters->dateTo->toDateString(),
    );

    return response()->streamDownload(function () use ($result) {
        $out = fopen('php://output', 'w');
        fputcsv($out, [
            'Host', 'Sessions Scheduled', 'Sessions Ended', 'Hours Live',
            'GMV (MYR)', 'Avg GMV/Hr (MYR)', 'No-Shows', 'Late Starts', 'Attendance %',
        ]);
        foreach ($result->rows as $row) {
            fputcsv($out, [
                $row['hostName'],
                $row['sessionsScheduled'],
                $row['sessionsEnded'],
                $row['hoursLive'],
                $row['gmv'],
                $row['avgGmvPerHour'],
                $row['noShows'],
                $row['lateStarts'],
                round($row['attendanceRate'] * 100, 1),
            ]);
        }
        fclose($out);
    }, $filename, [
        'Content-Type' => 'text/csv; charset=UTF-8',
    ]);
}
```

Add the import: `use Symfony\Component\HttpFoundation\StreamedResponse;` and `use Illuminate\Http\Request;` (if missing).

**Step 4: Add the route**

Inside the `Route::prefix('reports')` group:

```php
Route::get('host-scorecard/export', [\App\Http\Controllers\LiveHost\Reports\HostScorecardController::class, 'export'])
    ->name('host-scorecard.export');
```

**Step 5: Run, expect PASS**

Run: `php artisan test --compact tests/Feature/LiveHost/Reports/HostScorecardExportTest.php`

**Step 6: Pint + commit**

```bash
vendor/bin/pint --dirty
git add app/Http/Controllers/LiveHost/Reports/HostScorecardController.php \
        routes/web.php \
        tests/Feature/LiveHost/Reports/HostScorecardExportTest.php
git commit -m "feat(live-host): add CSV export for Host Scorecard"
```

---

## Task 10: N+1 guard test

Cheap insurance. Add a query-count assertion to ensure aggregation stays in SQL.

**Files:**
- Modify: `tests/Feature/LiveHost/Reports/HostScorecardReportTest.php`

**Step 1: Add the test**

```php
it('runs in a bounded number of queries on a realistic fixture', function () {
    $accounts = PlatformAccount::factory()->count(3)->create();
    $hosts = User::factory()->count(20)->create()->each(fn ($u) => $u->assignRole('live_host'));

    foreach ($hosts as $host) {
        LiveSession::factory()->count(10)->create([
            'live_host_id' => $host->id,
            'platform_account_id' => $accounts->random()->id,
            'status' => fake()->randomElement(['ended', 'missed', 'cancelled']),
            'scheduled_start_at' => fake()->dateTimeBetween('2026-04-01', '2026-04-25'),
            'duration_minutes' => 60,
            'gmv_amount' => fake()->randomFloat(2, 0, 500),
        ]);
    }

    \DB::enableQueryLog();
    (new HostScorecardReport)->run(new ReportFilters(
        CarbonImmutable::parse('2026-04-01'),
        CarbonImmutable::parse('2026-04-25'),
    ));
    $count = count(\DB::getQueryLog());
    \DB::disableQueryLog();

    expect($count)->toBeLessThanOrEqual(5);
});
```

**Step 2: Run, expect PASS** (current implementation does ~3 queries: aggregates, rows, trend, late starts).

If it fails, refactor the service to consolidate queries — never paper over by raising the bound.

**Step 3: Commit**

```bash
git add tests/Feature/LiveHost/Reports/HostScorecardReportTest.php
git commit -m "test(live-host): add N+1 guard for HostScorecardReport"
```

---

## Task 11: Manual verification + Coming-soon stubs for sibling reports

**Step 1: Add stub Inertia routes for the 3 future reports**

So clicking "Coming soon" cards from Task 1 doesn't 404 if a curious user types the URL. Each route renders a "Coming soon" Inertia page.

Modify `routes/web.php` inside the `reports` group:

```php
foreach (['gmv', 'coverage', 'replacements'] as $stub) {
    Route::get($stub, fn () => Inertia::render('reports/ComingSoon', [
        'title' => ucfirst($stub),
        'href' => '/livehost/reports',
    ]))->name("$stub.index");
}
```

Create `resources/js/livehost/pages/reports/ComingSoon.jsx`:

```jsx
import { Head, Link } from '@inertiajs/react';
import LiveHostLayout from '@/livehost/layouts/LiveHostLayout';

export default function ComingSoon({ title, href }) {
  return (
    <LiveHostLayout>
      <Head title={`${title} — Coming soon`} />
      <div className="flex min-h-[60vh] flex-col items-center justify-center gap-3 p-6 text-center">
        <h1 className="text-xl font-semibold">{title} report</h1>
        <p className="text-sm text-muted-foreground">This report is on the roadmap.</p>
        <Link href={href} className="text-sm underline">← Back to Reports</Link>
      </div>
    </LiveHostLayout>
  );
}
```

**Step 2: Smoke-test in the browser**

Manual checklist (do these in a real browser, not curl):

- [ ] `/livehost/reports` loads, 4 cards visible, only Host Scorecard is clickable.
- [ ] Click Host Scorecard → page renders without console errors.
- [ ] KPIs reflect seed data; deltas display.
- [ ] Trend chart draws bars.
- [ ] Date preset buttons update URL + data.
- [ ] Host filter narrows the table.
- [ ] CSV export downloads a file with rows.
- [ ] Click a host row → goes to `/livehost/hosts/{id}/edit`.
- [ ] Empty range (e.g. far-past dates) shows the empty state.
- [ ] As `user` role: 403 on both endpoints.
- [ ] As `admin_livehost` role: full access.

If any item fails, fix in place + add a regression Pest test.

**Step 3: Run the full report test suite one more time**

Run: `php artisan test --compact tests/Feature/LiveHost/Reports tests/Unit/LiveHost/Reports`
Expected: green.

**Step 4: Final commit**

```bash
vendor/bin/pint --dirty
git add routes/web.php resources/js/livehost/pages/reports/ComingSoon.jsx
git commit -m "feat(live-host): add coming-soon stubs for GMV, Coverage, Replacements reports"
```

---

## Out of scope for Phase 1

These are deliberately deferred per the design doc. **Do not pull them in.** If you discover a need for one, stop and discuss before expanding scope:

- The other three reports (GMV, Coverage, Replacements) — phases 2–4.
- Saved/named report views.
- Period-over-period comparison mode.
- PDF export.
- Scheduled email digests.
- Per-session row-level commission attribution (uses run-period overlap for now; design doc §7 flags this).
- TikTok-specific viewer/like/watch-time analytics.

## Open questions to flag during review

These three are unanswered in the design — implement the documented defaults, but raise them to the user during PR review:

1. Late-start threshold (currently 5 minutes).
2. Commission-window attribution (currently by overlapping payroll run period — confirm whether session-date attribution is preferred).
3. Replacement attribution in attendance % (currently: replaced sessions don't count against the original host's attendance — confirm).
