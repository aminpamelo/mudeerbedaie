# Live Host Assistant Role — Design

**Date:** 2026-04-24
**Status:** Design approved, ready for implementation plan
**Approach:** Reuse `/livehost/*` Inertia SPA with role-aware middleware groups, sidebar, and dashboard (Approach A)

## Problem

The Live Host Desk PIC (`admin_livehost`) currently owns the entire `/livehost/*` surface: dashboard, hosts, recruitment, time slots, session slots, platform accounts, creators, live sessions, commission, payroll, and TikTok imports. The operator wants to hire **external schedulers** whose only job is to fill out the weekly timetable and assign hosts to slots. Giving them the existing `admin_livehost` role would expose revenue, commission rates, payroll amounts, and hiring pipeline to outside contractors.

We need a narrower role that can run the calendar but sees nothing financial and no hiring data.

## Goals

- Introduce a `livehost_assistant` role whose access is limited to scheduling surfaces.
- Full CRUD on Time Slots and Session Slots (they own the calendar).
- Read-only visibility into Hosts, Platform Accounts, and Creators (so they can make informed assignments).
- Zero access to Live Sessions, Commission, Payroll, TikTok Imports, Recruitment, or any GMV/financial data.
- Dedicated scheduling-focused dashboard — no revenue cards.
- Reuse the existing `/livehost/*` Inertia SPA rather than forking a second mount.

## Non-goals

- No new scheduling features for this role — they use the existing Time Slots and Session Slots screens unchanged apart from permission-gated buttons.
- No separate branding / logo for the assistant view.
- No per-assistant scoping of which hosts or platform accounts they can touch — v1 treats all assistants as seeing the whole operation. Multi-tenant scoping is a future iteration.
- No audit log of assistant actions beyond Laravel's default request logs.

## Role landscape after this change

| Role | Scope |
|---|---|
| `admin` | Everything |
| `admin_livehost` | Full `/livehost/*` (unchanged) |
| `livehost_assistant` | Scheduling only: Dashboard, Time Slots, Session Slots + read-only Hosts / Platform Accounts / Creators |
| `live_host` | Pocket app (unchanged) |

## Section 1 — Role plumbing

### Migration

New migration: `database/migrations/YYYY_MM_DD_HHMMSS_add_livehost_assistant_role_to_users_table.php`.

Must work on both MySQL and SQLite using `DB::getDriverName()`. Pattern mirrors the existing `2026_03_26_085633_add_employee_role_to_users_table.php`:

- SQLite path: snapshot role rows, drop the `role` column, re-add with the new enum list, restore rows.
- MySQL path: short-circuit if the enum already contains `livehost_assistant` (to stay idempotent), otherwise `$table->enum('role', […])->change()`.

New enum list (full): `['admin', 'teacher', 'student', 'live_host', 'admin_livehost', 'class_admin', 'sales', 'employee', 'livehost_assistant']`.

Down migration removes `livehost_assistant` and demotes any existing assistants to `student` (mirrors how the employee migration demotes on rollback).

### User model helper

Add `isLiveHostAssistant(): bool` in `app/Models/User.php` next to `isAdminLiveHost()`:

```php
public function isLiveHostAssistant(): bool
{
    return $this->role === 'livehost_assistant';
}
```

### UserFactory state

Add a `liveHostAssistant()` state to `database/factories/UserFactory.php` (pattern matches whatever states already exist there — likely `->state(fn () => ['role' => 'livehost_assistant'])`). Required for the feature tests.

## Section 2 — Route split

`routes/web.php`, at the `/livehost` prefix block currently gated by `role:admin_livehost,admin`, becomes two inner groups under a shared `auth` outer middleware:

### Shared group — `role:admin,admin_livehost,livehost_assistant`

| Route name | Method | Notes |
|---|---|---|
| `livehost.dashboard` | GET `/livehost` | Controller picks the Inertia page per role |
| `livehost.hosts.index` | GET `/livehost/hosts` | Read-only list |
| `livehost.hosts.show` | GET `/livehost/hosts/{host}` | Read-only detail (Edit/Delete buttons hidden in UI) |
| `livehost.platform-accounts.index` | GET | Read-only list |
| `livehost.platform-accounts.show` | GET | Read-only detail |
| `livehost.creators.index` | GET | Read-only list (no `creators.show` exists today — only index) |
| `livehost.time-slots.*` | all | Full CRUD via `Route::resource` |
| `livehost.session-slots.calendar` | GET | |
| `livehost.session-slots.table` | GET | |
| `livehost.session-slots.preview` | GET | |
| `livehost.session-slots.index` | GET | |
| `livehost.session-slots.{store, show, edit, update, destroy, create}` | all | Full CRUD via `Route::resource` except `index` (already defined above) |

### Admin-only group — `role:admin,admin_livehost`

Everything else currently under `/livehost`, including:

- `livehost.live-now` (may surface GMV)
- `livehost.hosts.{create, store, edit, update, destroy}`
- `livehost.hosts.commission-profile.*`, `livehost.hosts.platform-rates.*`, `livehost.hosts.tiers.*`, `livehost.hosts.platform-accounts.*`
- `livehost.schedules.*` (legacy resource — admin-only)
- `livehost.platform-accounts.{create, store, edit, update, destroy}`
- `livehost.creators.{store, update, destroy}`
- `livehost.sessions.*` (all verbs) + session attachments + adjustments
- `livehost.commission.*`
- `livehost.payroll.*`
- `livehost.tiktok-imports.*`
- `livehost.recruitment.*` + associated admin endpoints

### Implementation shape

```php
Route::middleware(['auth'])->prefix('livehost')->name('livehost.')->group(function () {
    Route::middleware('role:admin,admin_livehost,livehost_assistant')->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

        Route::get('hosts', [HostController::class, 'index'])->name('hosts.index');
        Route::get('hosts/{host}', [HostController::class, 'show'])->name('hosts.show');

        Route::get('platform-accounts', [PlatformAccountController::class, 'index'])->name('platform-accounts.index');
        Route::get('platform-accounts/{platformAccount}', [PlatformAccountController::class, 'show'])->name('platform-accounts.show');

        Route::get('creators', [CreatorController::class, 'index'])->name('creators.index');

        Route::resource('time-slots', TimeSlotController::class)
            ->except(['show'])
            ->parameters(['time-slots' => 'timeSlot']);

        Route::get('session-slots/calendar', [SessionSlotController::class, 'calendar'])->name('session-slots.calendar');
        Route::get('session-slots/table', [SessionSlotController::class, 'index'])->name('session-slots.table');
        Route::get('session-slots/preview', fn () => Inertia::render('session-slots/CalendarPreview'))->name('session-slots.preview');
        Route::get('session-slots', [SessionSlotController::class, 'calendar'])->name('session-slots.index');

        Route::resource('session-slots', SessionSlotController::class)
            ->except(['index'])
            ->parameters(['session-slots' => 'sessionSlot']);
    });

    Route::middleware('role:admin,admin_livehost')->group(function () {
        // all previously-listed admin-only routes
    });
});
```

### Laravel quirk notes

- `Route::resource` cannot be split across middleware groups; therefore `hosts`, `platform-accounts`, and `creators` get expanded to explicit `Route::get / post / put / delete` calls across both groups. Named routes (`livehost.hosts.store`, etc.) stay identical so all `route()` callers keep working.
- `Route::resource('time-slots', …)` moves wholesale into the shared group. Same for `session-slots`.

## Section 3 — Controller & policy enforcement (defense-in-depth)

Middleware is the primary gate, but each admin-only controller action also gets a top-of-method guard:

```php
abort_if(request()->user()?->isLiveHostAssistant(), 403);
```

Added to:

- `SessionController@index, show, update, verify, verifyLink, storeAttachment, destroyAttachment`
- `LiveSessionGmvAdjustmentController@store, destroy, approve, reject`
- `CommissionOverviewController@index, export`
- `LiveHostPayrollRunController@*`
- `TiktokReportImportController@*`
- `RecruitmentCampaignController@*`, `RecruitmentApplicantController@*`, `RecruitmentStageController@*`
- `HostController@create, store, edit, update, destroy`
- `LiveHostCommissionProfileController@*`, `LiveHostPlatformCommissionRateController@*`
- `HostPlatformAccountController@*` (`attach, update, detach`)
- `PlatformAccountController@create, store, edit, update, destroy`
- `CreatorController@store, update, destroy`
- `DashboardController@liveNowJson`

The redundant check is cheap insurance — if a future route refactor accidentally drops the admin-only middleware, controllers still refuse. Form Request `authorize()` methods stay as-is; they already require authenticated users and the middleware handles role.

### LiveHostPolicy

Unchanged. `isPic()` remains `admin_livehost || admin`. The assistant is not a PIC — they cannot edit hosts, and they cannot reach the edit controllers in the first place because of the route split.

## Section 4 — Inertia shared props + sidebar

### Shared props — `HandleInertiaRequests::share()`

Extend the `auth` block to include a derived `permissions` object computed from the user's role:

```php
'auth' => [
    'user' => fn () => $request->user() ? [
        'id' => ...,
        'role' => $request->user()->role,
        // ...
    ] : null,
    'permissions' => fn () => $this->permissions($request->user()),
],
```

```php
private function permissions(?User $user): array
{
    $isPic = $user && in_array($user->role, ['admin', 'admin_livehost'], true);

    return [
        'canManageHosts' => $isPic,
        'canManagePlatformAccounts' => $isPic,
        'canManageCreators' => $isPic,
        'canSeeSessions' => $isPic,
        'canSeeFinancials' => $isPic,
        'canSeePayroll' => $isPic,
        'canRecruit' => $isPic,
        'canSeeTiktokImports' => $isPic,
    ];
}
```

Also extend `navCounts()` — the current early-return `in_array($user->role, ['admin', 'admin_livehost'], true)` must include `livehost_assistant`. Assistants need `hosts` and `platformAccounts` counts for their sidebar, but we should not return `sessions` count (leaks operational scale) — return a filtered shape when the role is the assistant:

```php
if ($user->role === 'livehost_assistant') {
    return Cache::remember('livehost.navCounts.assistant', 60, fn () => [
        'hosts' => User::query()->where('role', 'live_host')->count(),
        'platformAccounts' => PlatformAccount::query()->count(),
        'creators' => LiveHostPlatformAccount::query()->count(),
    ]);
}
```

### Sidebar — `LiveHostLayout.jsx`

The `NAV_GROUPS` constant stays static; filtering happens at render time based on `auth.permissions`:

```jsx
const { auth } = usePage().props;
const perms = auth?.permissions ?? {};

const visibleGroups = NAV_GROUPS
  .map(group => ({
    ...group,
    items: group.items.filter(item => canSee(item.key, perms)),
  }))
  .filter(group => group.items.length > 0);
```

Each NAV item gains a `key` that `canSee` maps to a permission flag:

| Item key | Shown when |
|---|---|
| `dashboard` | always |
| `hosts` | always (read-only for assistant) |
| `recruitment` | `perms.canRecruit` |
| `time-slots` | always |
| `session-slots` | always |
| `platform-accounts` | always |
| `creators` | always |
| `sessions` | `perms.canSeeSessions` |
| `commission` | `perms.canSeeFinancials` |
| `payroll` | `perms.canSeePayroll` |
| `tiktok-imports` | `perms.canSeeTiktokImports` |

Final sidebar for `livehost_assistant`:

- **Operations** — Dashboard, Live Hosts
- **Allocation** — Time Slots, Session Slots, Platform Accounts, Creators
- **Records** — *(group hidden entirely because all items are gated)*

### Per-page action button hiding

On the pages the assistant can reach, action buttons that belong to PIC-only flows are hidden behind the same `auth.permissions` flags:

- `hosts/Index.jsx` — hide "Create host", "Edit", "Delete", commission/platform-rate editors
- `hosts/Show.jsx` — hide Edit link, commission editors, tier editors, platform-account attach/update/detach
- `platform-accounts/Index.jsx` — hide "New account", row actions
- `platform-accounts/Show.jsx` — hide edit/delete
- `creators/Index.jsx` — hide add/edit/delete

The read-only API endpoints (`hosts.index`, `hosts.show`, etc.) themselves remain full-fat — they already don't expose commission rates beyond what the UI renders, and the UI hides the sensitive sections via the permission flags.

## Section 5 — Scheduler Dashboard

New Inertia page: `resources/js/livehost/pages/SchedulerDashboard.jsx`.

`DashboardController@index` branches:

```php
public function index(Request $request)
{
    $user = $request->user();

    if ($user->isLiveHostAssistant()) {
        return Inertia::render('SchedulerDashboard', $this->schedulerStats());
    }

    return Inertia::render('Dashboard', $this->picStats());
}
```

### SchedulerDashboard contents

Focused on the scheduler's job — no GMV, no commission, no payroll, no live-now stream.

**Top metric row (4 cards):**
1. **This Week's Coverage %** — `(assigned session slots) / (total session slots)` for the current week.
2. **Unassigned Slots** — count of session slots this week with no host; click-through to the calendar filtered to unassigned.
3. **Active Hosts** — count of users with role `live_host` and status active.
4. **Platform Accounts** — count of accounts.

**Left column:**
- **Unassigned slots — this week** table: time, platform account, status. Each row links to `session-slots/{id}/edit`.

**Right column:**
- **Today's slots** table: time, host name, platform account, status (scheduled / live / ended). Shows up to ~12 rows — no financial columns.

**Bottom quick actions:**
- "New session slot" button → `session-slots/create`
- "Manage time slots" button → `time-slots`
- "Open calendar" button → `session-slots`

### DashboardController helper method

```php
private function schedulerStats(): array
{
    $weekStart = now()->startOfWeek();
    $weekEnd = now()->endOfWeek();

    $weekSlots = SessionSlot::query()
        ->whereBetween('scheduled_at', [$weekStart, $weekEnd])
        ->get(['id', 'scheduled_at', 'host_id', 'platform_account_id', 'status']);

    return [
        'stats' => [
            'coveragePercent' => $weekSlots->isEmpty()
                ? 0
                : (int) round($weekSlots->whereNotNull('host_id')->count() / $weekSlots->count() * 100),
            'unassignedCount' => $weekSlots->whereNull('host_id')->count(),
            'activeHosts' => User::query()->where('role', 'live_host')->count(),
            'platformAccounts' => PlatformAccount::query()->count(),
        ],
        'unassignedThisWeek' => $weekSlots
            ->whereNull('host_id')
            ->take(20)
            ->map(fn ($s) => [...]),
        'todaySlots' => SessionSlot::query()
            ->whereDate('scheduled_at', today())
            ->with(['host:id,name', 'platformAccount:id,label'])
            ->orderBy('scheduled_at')
            ->get(['id', 'scheduled_at', 'host_id', 'platform_account_id', 'status'])
            ->map(fn ($s) => [...]),
    ];
}
```

(Field names `host_id`, `platform_account_id`, `scheduled_at` are placeholders — the implementation plan validates against the actual `session_slots` schema before writing this.)

## Section 6 — Testing

Pest feature tests, per project convention (`tests/Feature/LiveHost/`):

### `LiveHostAssistantAccessTest.php`

- Assistant can GET each shared route and receives 200.
- Assistant can POST / PUT / DELETE on `time-slots` and `session-slots` and receives 2xx.
- Assistant gets 403 on:
  - `livehost.live-now`
  - `livehost.sessions.index`, `sessions.show`, `sessions.update`, `sessions.verify`, `sessions.attachments.store`, `sessions.adjustments.*`
  - `livehost.commission.index`, `commission.export`
  - `livehost.payroll.index`
  - `livehost.tiktok-imports.*`
  - `livehost.recruitment.*`
  - `livehost.hosts.create`, `hosts.store`, `hosts.edit`, `hosts.update`, `hosts.destroy`
  - `livehost.hosts.commission-profile.*`, `platform-rates.*`, `tiers.*`, `platform-accounts.*`
  - `livehost.platform-accounts.store`, `update`, `destroy`
  - `livehost.creators.store`, `update`, `destroy`
- Assistant visiting `/livehost` resolves the `SchedulerDashboard` Inertia component, not `Dashboard`.
- Inertia shared props for an assistant include `permissions.canManageHosts === false`, `canSeeFinancials === false`, etc.
- Sidebar smoke: the Inertia response for `/livehost/session-slots` includes the permissions object with the expected shape for an assistant vs. a PIC (dataset-driven test with roles `admin_livehost`, `livehost_assistant`).

### Update existing tests

Any existing feature test asserting `role:admin_livehost,admin` middleware behavior (expecting a `live_host` or `student` to 403) should be re-run to confirm the assistant 403s where expected — we add dataset rows rather than rewriting.

### Factory usage

`User::factory()->liveHostAssistant()->create()` — the new factory state added in Section 1.

## Migration & rollout

- No data migration needed — new role, no existing rows with that value.
- Admin UI: `/admin/users` already supports role assignment via the user-management flow (confirm during implementation; if not, that's a trivial `flux:select` option add — not part of this design doc's critical path).
- Seed data: no default assistant seeded. Operator creates the first one manually after deploy.

## Risks

1. **Silent privilege escalation via forgotten routes.** Any admin-only controller action without the `abort_if` belt-and-braces check could be reachable if a future middleware refactor drops the role constraint. Mitigation: the feature test explicitly 403s against every admin-only route name — if a new route is added and left under the shared middleware group, the test catches it only if we also add an assertion for the new route. Mitigation 2: a dataset-driven test walks `Route::getRoutes()` and asserts each admin-only-prefix route returns 403 for an assistant token — catches new routes automatically. Include this "all routes" walker in the test file.
2. **Inertia shared props leaking counts.** `navCounts()` today returns `sessions` and `schedules` counts. For the assistant, filter the return shape (Section 4). Test asserts the assistant's `navCounts` payload does not include a `sessions` key.
3. **Calendar reveals host identities across platform accounts.** The calendar intentionally shows host names across all accounts — this is operationally necessary for scheduling and is considered acceptable disclosure to the external scheduler per the operator's answers.

## Open follow-ups (out of scope for v1)

- Per-assistant scoping to a subset of platform accounts or host pools.
- Audit log of assistant actions (who reassigned what, when).
- Restricting assistant to read-only view on Time Slots if the operator changes their mind about template ownership.
