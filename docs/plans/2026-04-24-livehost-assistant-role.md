# Live Host Assistant Role Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Add a `livehost_assistant` role that can fully manage Time Slots + Session Slots and read-only view Hosts/Platform Accounts/Creators, with zero access to Sessions/Commission/Payroll/Recruitment/TikTok Imports, and a dedicated scheduler dashboard.

**Architecture:** Reuse the existing `/livehost/*` Inertia SPA. Split `routes/web.php` into two inner middleware groups (shared vs admin-only) under a shared `auth` wrapper. Enforce in three layers: route middleware, per-controller `abort_if`, and UI filtering via a new `auth.permissions` Inertia shared prop. Branch the `/livehost` dashboard controller to render a new `SchedulerDashboard.jsx` when the user is an assistant.

**Tech Stack:** Laravel 12, Inertia.js + React 19, Livewire Volt (unused here), Pest 4, Tailwind v4, Lucide icons. DB: MySQL (prod) + SQLite (dev/tests).

**Design doc:** `docs/plans/2026-04-24-livehost-assistant-role-design.md`

**Working branch:** `main` (operator directive — no feature branch).

---

## Pre-flight reading

Before Task 1, the executing agent should skim these to understand conventions:

- `app/Http/Middleware/HandleInertiaRequests.php` — where `auth` shared props live.
- `app/Policies/LiveHostPolicy.php` — `isPic()` helper, the existing PIC concept.
- `database/migrations/2026_03_26_085633_add_employee_role_to_users_table.php` — the dual-driver enum pattern we must mirror.
- `routes/web.php` lines 216–345 — the `/livehost` route block we're restructuring.
- `app/Http/Controllers/LiveHost/SessionSlotController.php` — it uses the `LiveScheduleAssignment` model, not a `SessionSlot` model. Columns used: `live_host_id`, `platform_account_id`, `status`, `day_of_week`, `schedule_date`, `is_template`.
- `resources/js/livehost/layouts/LiveHostLayout.jsx` — sidebar `NAV_GROUPS` we're filtering.
- `database/factories/UserFactory.php` — `sales()` state is the pattern for our new state.
- `tests/Feature/LiveHost/AccessTest.php` — role-based middleware assertion patterns.

---

## Task 1 — Role enum migration + User helper + Factory state

**Files:**
- Create: `database/migrations/2026_04_24_HHMMSS_add_livehost_assistant_role_to_users_table.php` (filename timestamp chosen by `php artisan make:migration`)
- Modify: `app/Models/User.php` (add method next to `isAdminLiveHost()` — that method lives around line 108–114)
- Modify: `database/factories/UserFactory.php` (add state next to `sales()`)
- Test: `tests/Feature/LiveHost/LiveHostAssistantRoleTest.php`

**Step 1: Write the failing test**

Create `tests/Feature/LiveHost/LiveHostAssistantRoleTest.php`:

```php
<?php

declare(strict_types=1);

use App\Models\User;

it('can create a livehost_assistant user and detect the role', function () {
    $user = User::factory()->liveHostAssistant()->create();

    expect($user->role)->toBe('livehost_assistant');
    expect($user->isLiveHostAssistant())->toBeTrue();
    expect($user->isAdminLiveHost())->toBeFalse();
    expect($user->isLiveHost())->toBeFalse();
});
```

**Step 2: Run test to verify it fails**

```bash
php artisan test --compact --filter=LiveHostAssistantRoleTest
```

Expected: FAIL — "Call to undefined method liveHostAssistant()" or enum constraint violation.

**Step 3: Create the migration**

```bash
php artisan make:migration add_livehost_assistant_role_to_users_table --no-interaction
```

Populate the generated file, mirroring `2026_03_26_085633_add_employee_role_to_users_table.php` exactly but with the new enum list:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            $roles = DB::table('users')->select('id', 'role')->get();

            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('role');
            });

            Schema::table('users', function (Blueprint $table) {
                $table->enum('role', ['admin', 'teacher', 'student', 'live_host', 'admin_livehost', 'class_admin', 'sales', 'employee', 'livehost_assistant'])
                    ->default('student')
                    ->after('email_verified_at');
            });

            foreach ($roles as $row) {
                DB::table('users')->where('id', $row->id)->update(['role' => $row->role]);
            }

            return;
        }

        $columns = DB::select("SHOW COLUMNS FROM users WHERE Field = 'role' AND Type LIKE '%livehost_assistant%'");
        if (! empty($columns)) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['admin', 'teacher', 'student', 'live_host', 'admin_livehost', 'class_admin', 'sales', 'employee', 'livehost_assistant'])
                ->default('student')
                ->change();
        });
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            $roles = DB::table('users')->select('id', 'role')->get();

            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('role');
            });

            Schema::table('users', function (Blueprint $table) {
                $table->enum('role', ['admin', 'teacher', 'student', 'live_host', 'admin_livehost', 'class_admin', 'sales', 'employee'])
                    ->default('student')
                    ->after('email_verified_at');
            });

            foreach ($roles as $row) {
                $role = $row->role === 'livehost_assistant' ? 'student' : $row->role;
                DB::table('users')->where('id', $row->id)->update(['role' => $role]);
            }

            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['admin', 'teacher', 'student', 'live_host', 'admin_livehost', 'class_admin', 'sales', 'employee'])
                ->default('student')
                ->change();
        });
    }
};
```

**Step 4: Add the User helper**

In `app/Models/User.php`, next to `isAdminLiveHost()`:

```php
/**
 * Check if user is an external live host scheduler (assistant to PIC).
 */
public function isLiveHostAssistant(): bool
{
    return $this->role === 'livehost_assistant';
}
```

**Step 5: Add the Factory state**

In `database/factories/UserFactory.php`, right after the `sales()` state:

```php
public function liveHostAssistant(): static
{
    return $this->state(fn () => [
        'role' => 'livehost_assistant',
    ]);
}
```

**Step 6: Run migration**

```bash
php artisan migrate --no-interaction
```

Expected: "Migrating: … add_livehost_assistant_role_to_users_table" and "Migrated".

**CRITICAL:** Do NOT run `php artisan migrate:fresh`. The operator has explicitly forbidden it in memory. Only plain `migrate`.

**Step 7: Re-run test**

```bash
php artisan test --compact --filter=LiveHostAssistantRoleTest
```

Expected: PASS (1 test, 3 assertions).

**Step 8: Commit**

```bash
git add database/migrations app/Models/User.php database/factories/UserFactory.php tests/Feature/LiveHost/LiveHostAssistantRoleTest.php
git commit -m "$(cat <<'EOF'
feat(livehost): add livehost_assistant role to user enum

Adds the external-scheduler role with a dual-driver migration
(MySQL + SQLite), a User::isLiveHostAssistant() helper, and a
UserFactory::liveHostAssistant() state. No route wiring yet —
that follows in the next task.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

## Task 2 — Route split: admin-only routes 403 for assistant

This task restructures `routes/web.php` so that the assistant's attempt to hit admin-only endpoints returns 403, while the shared endpoints remain accessible. We do it in one pass because the existing `Route::resource` calls have to be surgically split.

**Files:**
- Modify: `routes/web.php` (the `/livehost` block — currently lines ~216–345)
- Modify: `tests/Feature/LiveHost/LiveHostAssistantRoleTest.php`

**Step 1: Write the failing tests**

Append to `LiveHostAssistantRoleTest.php`:

```php
it('403s when assistant tries to hit admin-only livehost routes', function (string $routeName, string $method, array $params) {
    $assistant = User::factory()->liveHostAssistant()->create();

    $url = route($routeName, $params);
    $response = $this->actingAs($assistant)->call($method, $url);

    expect($response->status())->toBe(403);
})->with([
    ['livehost.live-now', 'GET', []],
    ['livehost.sessions.index', 'GET', []],
    ['livehost.commission.index', 'GET', []],
    ['livehost.commission.export', 'GET', []],
    ['livehost.payroll.index', 'GET', []],
    ['livehost.tiktok-imports.index', 'GET', []],
    ['livehost.recruitment.campaigns.index', 'GET', []],
    ['livehost.hosts.create', 'GET', []],
    ['livehost.schedules.index', 'GET', []],
]);

it('allows assistant to reach shared livehost routes', function (string $routeName) {
    $assistant = User::factory()->liveHostAssistant()->create();

    $response = $this->actingAs($assistant)->get(route($routeName));

    // 200 OK for Inertia pages; do not assert response body shape yet.
    expect($response->status())->toBe(200);
})->with([
    'livehost.dashboard',
    'livehost.hosts.index',
    'livehost.platform-accounts.index',
    'livehost.creators.index',
    'livehost.time-slots.index',
    'livehost.session-slots.index',
    'livehost.session-slots.calendar',
    'livehost.session-slots.table',
]);
```

If any of the admin-only route names above do not exist yet in the codebase, the execution agent must grep `routes/web.php` and use the actual name — the test should cover whatever route names are genuinely there. The names listed match current definitions as of the design doc.

**Step 2: Run tests to verify they fail**

```bash
php artisan test --compact --filter=LiveHostAssistantRoleTest
```

Expected: several FAILs — assistants currently cannot reach `/livehost` at all (middleware blocks them), so the "shared routes" tests fail with 403; and the admin-only tests also fail because the 403 currently comes from the blanket middleware, which is correct — but the tests will pass. The point is that after Task 2 the admin-only tests still pass AND the shared-route tests flip to passing.

Realistically: expect admin-only assertions to already pass (assistant blocked everywhere). Expect shared-route assertions to fail with 403. That's the red state for this task.

**Step 3: Restructure `routes/web.php`**

Locate the block currently reading:

```php
// Live Host PIC (Inertia) — accessible by admin_livehost + admin
Route::middleware(['auth', 'role:admin_livehost,admin'])
    ->prefix('livehost')
    ->name('livehost.')
    ->group(function () {
        // … everything through payroll/tiktok-imports/recruitment …
    });
```

Replace it with two inner groups inside a single `auth` + prefix + name wrapper:

```php
// Live Host PIC (Inertia) — shared surface for admin + admin_livehost + livehost_assistant,
// with admin-only endpoints gated further inside.
Route::middleware(['auth'])
    ->prefix('livehost')
    ->name('livehost.')
    ->group(function () {

        // Shared: scheduling + read-only reference data
        Route::middleware('role:admin,admin_livehost,livehost_assistant')->group(function () {
            Route::get('/', [\App\Http\Controllers\LiveHost\DashboardController::class, 'index'])
                ->name('dashboard');

            Route::get('hosts', [\App\Http\Controllers\LiveHost\HostController::class, 'index'])
                ->name('hosts.index');
            Route::get('hosts/{host}', [\App\Http\Controllers\LiveHost\HostController::class, 'show'])
                ->name('hosts.show');

            Route::get('platform-accounts', [\App\Http\Controllers\LiveHost\PlatformAccountController::class, 'index'])
                ->name('platform-accounts.index');
            Route::get('platform-accounts/{platformAccount}', [\App\Http\Controllers\LiveHost\PlatformAccountController::class, 'show'])
                ->name('platform-accounts.show');

            Route::get('creators', [\App\Http\Controllers\LiveHost\CreatorController::class, 'index'])
                ->name('creators.index');

            Route::resource('time-slots', \App\Http\Controllers\LiveHost\TimeSlotController::class)
                ->except(['show'])
                ->parameters(['time-slots' => 'timeSlot']);

            Route::get('session-slots/calendar', [\App\Http\Controllers\LiveHost\SessionSlotController::class, 'calendar'])
                ->name('session-slots.calendar');
            Route::get('session-slots/table', [\App\Http\Controllers\LiveHost\SessionSlotController::class, 'index'])
                ->name('session-slots.table');
            Route::get('session-slots/preview', fn () => \Inertia\Inertia::render('session-slots/CalendarPreview'))
                ->name('session-slots.preview');
            Route::get('session-slots', [\App\Http\Controllers\LiveHost\SessionSlotController::class, 'calendar'])
                ->name('session-slots.index');

            Route::resource('session-slots', \App\Http\Controllers\LiveHost\SessionSlotController::class)
                ->except(['index'])
                ->parameters(['session-slots' => 'sessionSlot']);
        });

        // Admin-only: everything else
        Route::middleware('role:admin,admin_livehost')->group(function () {
            Route::get('live-now', [\App\Http\Controllers\LiveHost\DashboardController::class, 'liveNowJson'])
                ->name('live-now');

            Route::get('hosts/create', [\App\Http\Controllers\LiveHost\HostController::class, 'create'])
                ->name('hosts.create');
            Route::post('hosts', [\App\Http\Controllers\LiveHost\HostController::class, 'store'])
                ->name('hosts.store');
            Route::get('hosts/{host}/edit', [\App\Http\Controllers\LiveHost\HostController::class, 'edit'])
                ->name('hosts.edit');
            Route::put('hosts/{host}', [\App\Http\Controllers\LiveHost\HostController::class, 'update'])
                ->name('hosts.update');
            Route::delete('hosts/{host}', [\App\Http\Controllers\LiveHost\HostController::class, 'destroy'])
                ->name('hosts.destroy');

            Route::post('hosts/{host}/commission-profile', [\App\Http\Controllers\LiveHost\LiveHostCommissionProfileController::class, 'store'])
                ->name('hosts.commission-profile.store');
            Route::put('hosts/{host}/commission-profile', [\App\Http\Controllers\LiveHost\LiveHostCommissionProfileController::class, 'update'])
                ->name('hosts.commission-profile.update');

            Route::post('hosts/{host}/platform-rates', [\App\Http\Controllers\LiveHost\LiveHostPlatformCommissionRateController::class, 'store'])
                ->name('hosts.platform-rates.store');
            Route::put('hosts/{host}/platform-rates/{rate}', [\App\Http\Controllers\LiveHost\LiveHostPlatformCommissionRateController::class, 'update'])
                ->name('hosts.platform-rates.update');

            Route::post('hosts/{host}/platforms/{platform:id}/tiers', [\App\Http\Controllers\LiveHost\HostController::class, 'storeTierSchedule'])
                ->name('hosts.tiers.store')
                ->withoutScopedBindings();
            Route::patch('hosts/{host}/tiers/{tier}', [\App\Http\Controllers\LiveHost\HostController::class, 'updateTier'])
                ->name('hosts.tiers.update');
            Route::delete('hosts/{host}/tiers/{tier}', [\App\Http\Controllers\LiveHost\HostController::class, 'destroyTier'])
                ->name('hosts.tiers.destroy');

            Route::post('hosts/{host}/platform-accounts/{platformAccount}', [\App\Http\Controllers\LiveHost\HostPlatformAccountController::class, 'attach'])
                ->name('hosts.platform-accounts.attach');
            Route::patch('hosts/{host}/platform-accounts/{platformAccount}', [\App\Http\Controllers\LiveHost\HostPlatformAccountController::class, 'update'])
                ->name('hosts.platform-accounts.update');
            Route::delete('hosts/{host}/platform-accounts/{platformAccount}', [\App\Http\Controllers\LiveHost\HostPlatformAccountController::class, 'detach'])
                ->name('hosts.platform-accounts.detach');

            Route::resource('schedules', \App\Http\Controllers\LiveHost\ScheduleController::class);

            // platform-accounts: write verbs only (GET index+show are in shared group above)
            Route::get('platform-accounts/create', [\App\Http\Controllers\LiveHost\PlatformAccountController::class, 'create'])
                ->name('platform-accounts.create');
            Route::post('platform-accounts', [\App\Http\Controllers\LiveHost\PlatformAccountController::class, 'store'])
                ->name('platform-accounts.store');
            Route::get('platform-accounts/{platformAccount}/edit', [\App\Http\Controllers\LiveHost\PlatformAccountController::class, 'edit'])
                ->name('platform-accounts.edit');
            Route::put('platform-accounts/{platformAccount}', [\App\Http\Controllers\LiveHost\PlatformAccountController::class, 'update'])
                ->name('platform-accounts.update');
            Route::delete('platform-accounts/{platformAccount}', [\App\Http\Controllers\LiveHost\PlatformAccountController::class, 'destroy'])
                ->name('platform-accounts.destroy');

            // creators: write verbs only
            Route::post('creators', [\App\Http\Controllers\LiveHost\CreatorController::class, 'store'])
                ->name('creators.store');
            Route::put('creators/{creator}', [\App\Http\Controllers\LiveHost\CreatorController::class, 'update'])
                ->name('creators.update');
            Route::delete('creators/{creator}', [\App\Http\Controllers\LiveHost\CreatorController::class, 'destroy'])
                ->name('creators.destroy');

            Route::get('sessions', [\App\Http\Controllers\LiveHost\SessionController::class, 'index'])
                ->name('sessions.index');
            Route::get('sessions/{session}', [\App\Http\Controllers\LiveHost\SessionController::class, 'show'])
                ->name('sessions.show');
            Route::put('sessions/{session}', [\App\Http\Controllers\LiveHost\SessionController::class, 'update'])
                ->name('sessions.update');
            Route::post('sessions/{session}/verify', [\App\Http\Controllers\LiveHost\SessionController::class, 'verify'])
                ->name('sessions.verify');
            Route::post('sessions/{session}/verify-link', [\App\Http\Controllers\LiveHost\SessionController::class, 'verifyLink'])
                ->name('sessions.verify-link');
            Route::post('sessions/{session}/attachments', [\App\Http\Controllers\LiveHost\SessionController::class, 'storeAttachment'])
                ->name('sessions.attachments.store');
            Route::delete('sessions/{session}/attachments/{attachment}', [\App\Http\Controllers\LiveHost\SessionController::class, 'destroyAttachment'])
                ->name('sessions.attachments.destroy');
            Route::post('sessions/{session}/adjustments', [\App\Http\Controllers\LiveHost\LiveSessionGmvAdjustmentController::class, 'store'])
                ->name('sessions.adjustments.store');
            Route::delete('sessions/{session}/adjustments/{adjustment}', [\App\Http\Controllers\LiveHost\LiveSessionGmvAdjustmentController::class, 'destroy'])
                ->name('sessions.adjustments.destroy');
            Route::post('sessions/{session}/adjustments/{adjustment}/approve', [\App\Http\Controllers\LiveHost\LiveSessionGmvAdjustmentController::class, 'approve'])
                ->name('sessions.adjustments.approve');
            Route::post('sessions/{session}/adjustments/{adjustment}/reject', [\App\Http\Controllers\LiveHost\LiveSessionGmvAdjustmentController::class, 'reject'])
                ->name('sessions.adjustments.reject');

            Route::get('commission/export', [\App\Http\Controllers\LiveHost\CommissionOverviewController::class, 'export'])
                ->name('commission.export');
            Route::get('commission', [\App\Http\Controllers\LiveHost\CommissionOverviewController::class, 'index'])
                ->name('commission.index');

            // payroll, tiktok-imports, recruitment — preserve all existing definitions as-is.
        });
    });
```

**IMPORTANT:** The agent must copy over the payroll, tiktok-imports, and recruitment route definitions verbatim from the current file into the admin-only group. The snippet above shortens them to a comment; do NOT leave a comment in the final code. Read the current `routes/web.php` to grab the full block before editing.

**Step 4: Run tests to verify they pass**

```bash
php artisan test --compact --filter=LiveHostAssistantRoleTest
```

Expected: all dataset rows pass. 8 shared routes return 200, all admin-only routes return 403.

**Step 5: Run the broader LiveHost suite to confirm no regressions**

```bash
php artisan test --compact tests/Feature/LiveHost
```

Expected: all pass. If anything breaks, it's likely a test that was asserting the old middleware string `role:admin_livehost,admin` on routes that moved to the shared group. Update those assertions to the new reality — the permission outcome (admin still 200s) is unchanged.

**Step 6: Commit**

```bash
git add routes/web.php tests/Feature/LiveHost/LiveHostAssistantRoleTest.php
git commit -m "$(cat <<'EOF'
feat(livehost): split /livehost routes into shared + admin-only groups

Shared group (admin, admin_livehost, livehost_assistant): dashboard,
time-slots CRUD, session-slots CRUD, and read-only hosts/
platform-accounts/creators. Admin-only group retains everything else
(sessions, commission, payroll, tiktok-imports, recruitment, and all
write verbs on hosts/accounts/creators).

Route::resource calls on hosts/platform-accounts/creators are expanded
into explicit Route::get/post/put/delete so GET verbs can live in the
shared group without exposing write verbs.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

## Task 3 — Controller abort_if guards (belt-and-braces)

**Files:**
- Modify: `app/Http/Controllers/LiveHost/DashboardController.php` (only the `liveNowJson` method)
- Modify: `app/Http/Controllers/LiveHost/HostController.php` (create/store/edit/update/destroy + tier methods)
- Modify: `app/Http/Controllers/LiveHost/LiveHostCommissionProfileController.php` (all methods)
- Modify: `app/Http/Controllers/LiveHost/LiveHostPlatformCommissionRateController.php` (all methods)
- Modify: `app/Http/Controllers/LiveHost/HostPlatformAccountController.php` (attach/update/detach)
- Modify: `app/Http/Controllers/LiveHost/PlatformAccountController.php` (create/store/edit/update/destroy)
- Modify: `app/Http/Controllers/LiveHost/CreatorController.php` (store/update/destroy)
- Modify: `app/Http/Controllers/LiveHost/SessionController.php` (all methods)
- Modify: `app/Http/Controllers/LiveHost/LiveSessionGmvAdjustmentController.php` (all methods)
- Modify: `app/Http/Controllers/LiveHost/CommissionOverviewController.php` (index, export)
- Modify: `app/Http/Controllers/LiveHost/LiveHostPayrollRunController.php` (all methods)
- Modify: `app/Http/Controllers/LiveHost/TiktokReportImportController.php` (all methods)
- Modify: `app/Http/Controllers/LiveHost/RecruitmentCampaignController.php` (all methods)
- Modify: `app/Http/Controllers/LiveHost/RecruitmentApplicantController.php` (all methods)
- Modify: `app/Http/Controllers/LiveHost/RecruitmentStageController.php` (all methods)
- Modify: `app/Http/Controllers/LiveHost/ScheduleController.php` (all methods)
- Test: `tests/Feature/LiveHost/LiveHostAssistantRoleTest.php`

**Step 1: Write a failing test that simulates middleware-bypass**

This test temporarily attaches a fake route with only the `auth` middleware, pointing at an admin-only controller action, and asserts the assistant still gets 403. This proves the controller-side guard works independently of the middleware.

Append to `LiveHostAssistantRoleTest.php`:

```php
use Illuminate\Support\Facades\Route as RouteFacade;

it('controller guards 403 the assistant even without role middleware', function () {
    RouteFacade::middleware('auth')->get('__test__/live-now', [
        \App\Http\Controllers\LiveHost\DashboardController::class,
        'liveNowJson',
    ])->name('__test__.live-now');

    $assistant = User::factory()->liveHostAssistant()->create();

    $response = $this->actingAs($assistant)->get('/__test__/live-now');

    expect($response->status())->toBe(403);
});
```

**Step 2: Run it to confirm it fails**

```bash
php artisan test --compact --filter="controller guards 403"
```

Expected: FAIL — controller currently returns 200 because it has no internal guard.

**Step 3: Add the guard**

At the top of every admin-only controller action, add:

```php
abort_if($request->user()?->isLiveHostAssistant() === true, 403);
```

Each action's signature already takes `Request $request` in most cases. For actions that only take model-bound params (e.g. `update(Host $host)`), change the signature to `update(Request $request, Host $host)` — this is a safe widening.

For controllers whose every method is admin-only (e.g. `LiveHostPayrollRunController`, `TiktokReportImportController`, `RecruitmentCampaignController`, etc.), an alternative is a constructor middleware call:

```php
public function __construct()
{
    $this->middleware(function ($request, $next) {
        abort_if($request->user()?->isLiveHostAssistant() === true, 403);
        return $next($request);
    });
}
```

Laravel 12 controllers no longer extend a base with `middleware()` by default — the project's `app/Http/Controllers/Controller.php` should be checked; if it has a `middleware()` helper, use it, otherwise use per-method `abort_if`. Either pattern is fine; pick whichever keeps the diff smaller per controller.

Methods to guard (reference the design doc Section 3 list for the full enumeration).

**Step 4: Run the test to confirm it passes**

```bash
php artisan test --compact --filter="controller guards 403"
```

Expected: PASS.

**Step 5: Run the full LiveHost suite**

```bash
php artisan test --compact tests/Feature/LiveHost
```

Expected: all pass. Guards should not affect admin or admin_livehost users because the condition is `=== true` on the assistant check only.

**Step 6: Commit**

```bash
git add app/Http/Controllers/LiveHost tests/Feature/LiveHost/LiveHostAssistantRoleTest.php
git commit -m "$(cat <<'EOF'
feat(livehost): add controller-level guards for admin-only actions

Each admin-only LiveHost controller action now explicitly refuses the
livehost_assistant role via abort_if, independent of the route
middleware. Catches any future middleware slip.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

## Task 4 — Inertia shared `permissions` prop + filtered `navCounts`

**Files:**
- Modify: `app/Http/Middleware/HandleInertiaRequests.php`
- Test: `tests/Feature/LiveHost/LiveHostAssistantRoleTest.php`

**Step 1: Write the failing test**

Append to `LiveHostAssistantRoleTest.php`:

```php
it('shares a permissions prop on Inertia responses', function () {
    $assistant = User::factory()->liveHostAssistant()->create();

    $response = $this->actingAs($assistant)->get(route('livehost.session-slots.index'));

    $response->assertInertia(fn ($page) => $page
        ->where('auth.user.role', 'livehost_assistant')
        ->where('auth.permissions.canManageHosts', false)
        ->where('auth.permissions.canSeeFinancials', false)
        ->where('auth.permissions.canSeePayroll', false)
        ->where('auth.permissions.canRecruit', false)
        ->where('auth.permissions.canSeeSessions', false)
        ->where('auth.permissions.canSeeTiktokImports', false)
    );
});

it('grants admin_livehost full permissions in shared prop', function () {
    $pic = User::factory()->create(['role' => 'admin_livehost']);

    $response = $this->actingAs($pic)->get(route('livehost.session-slots.index'));

    $response->assertInertia(fn ($page) => $page
        ->where('auth.permissions.canManageHosts', true)
        ->where('auth.permissions.canSeeFinancials', true)
    );
});

it('excludes sensitive nav count keys from assistant payload', function () {
    $assistant = User::factory()->liveHostAssistant()->create();

    $response = $this->actingAs($assistant)->get(route('livehost.session-slots.index'));

    $response->assertInertia(fn ($page) => $page
        ->has('navCounts.hosts')
        ->has('navCounts.platformAccounts')
        ->has('navCounts.creators')
        ->missing('navCounts.sessions')
        ->missing('navCounts.schedules')
    );
});
```

**Step 2: Run to confirm failure**

```bash
php artisan test --compact --filter="shares a permissions prop|grants admin_livehost|excludes sensitive nav count"
```

Expected: FAIL — `auth.permissions` doesn't exist yet, and `navCounts` returns `null` for the assistant (early-return on role check).

**Step 3: Update `HandleInertiaRequests::share()`**

In `app/Http/Middleware/HandleInertiaRequests.php`:

Inside `share()`, extend the `auth` array:

```php
'auth' => [
    'user' => fn () => $request->user()
        ? [
            'id' => $request->user()->id,
            'name' => $request->user()->name,
            'email' => $request->user()->email,
            'role' => $request->user()->role,
            'avatarUrl' => $request->user()->avatar_url,
            'commission' => $this->hostCommission($request->user()),
        ]
        : null,
    'permissions' => fn () => $this->permissions($request->user()),
],
```

Add the helper:

```php
/**
 * Role-derived capability flags consumed by the sidebar and action buttons.
 *
 * @return array<string, bool>
 */
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

Update `navCounts()` to include the assistant branch:

```php
private function navCounts(Request $request): ?array
{
    $user = $request->user();

    if (! $user) {
        return null;
    }

    if ($user->role === 'livehost_assistant') {
        return Cache::remember('livehost.navCounts.assistant', 60, fn () => [
            'hosts' => User::query()->where('role', 'live_host')->count(),
            'platformAccounts' => PlatformAccount::query()->count(),
            'creators' => \App\Models\LiveHostPlatformAccount::query()->count(),
        ]);
    }

    if (! in_array($user->role, ['admin', 'admin_livehost'], true)) {
        return null;
    }

    return Cache::remember('livehost.navCounts', 60, fn () => [
        'hosts' => User::query()->where('role', 'live_host')->count(),
        'schedules' => LiveSchedule::query()->where('is_active', true)->count(),
        'sessions' => LiveSession::query()->count(),
        'platformAccounts' => PlatformAccount::query()->count(),
        'creators' => \App\Models\LiveHostPlatformAccount::query()->count(),
    ]);
}
```

**Step 4: Run tests to confirm they pass**

```bash
php artisan test --compact --filter="shares a permissions prop|grants admin_livehost|excludes sensitive nav count"
```

Expected: all PASS.

**Step 5: Commit**

```bash
git add app/Http/Middleware/HandleInertiaRequests.php tests/Feature/LiveHost/LiveHostAssistantRoleTest.php
git commit -m "$(cat <<'EOF'
feat(livehost): share auth.permissions + role-filtered nav counts

Adds a role-derived permissions object on every Inertia response so the
frontend sidebar and per-page action buttons can gate visibility
without pattern-matching on role strings. Assistant nav-count payload
omits sensitive keys (sessions, schedules).

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

## Task 5 — Sidebar role-aware filtering

**Files:**
- Modify: `resources/js/livehost/layouts/LiveHostLayout.jsx`

This task has no automated test in the Pest suite (it's UI logic). Manual verification via the browser.

**Step 1: Add `key` to each NAV_GROUPS item**

Locate `NAV_GROUPS` in `resources/js/livehost/layouts/LiveHostLayout.jsx` (around line 19–45). Add a `key` field to every item:

```jsx
const NAV_GROUPS = [
  {
    label: 'Operations',
    items: [
      { key: 'dashboard', label: 'Dashboard', href: '/livehost', icon: LayoutDashboard },
      { key: 'hosts', label: 'Live Hosts', href: '/livehost/hosts', icon: Users, countKey: 'hosts' },
      { key: 'recruitment', label: 'Recruitment', href: '/livehost/recruitment/campaigns', icon: Megaphone },
    ],
  },
  {
    label: 'Allocation',
    items: [
      { key: 'time-slots', label: 'Time Slots', href: '/livehost/time-slots', icon: Clock },
      { key: 'session-slots', label: 'Session Slots', href: '/livehost/session-slots', icon: LayoutGrid },
      { key: 'platform-accounts', label: 'Platform Accounts', href: '/livehost/platform-accounts', icon: Store, countKey: 'platformAccounts' },
      { key: 'creators', label: 'Creators', href: '/livehost/creators', icon: UserCircle2, countKey: 'creators' },
    ],
  },
  {
    label: 'Records',
    items: [
      { key: 'sessions', label: 'Live Sessions', href: '/livehost/sessions', icon: Play, countKey: 'sessions' },
      { key: 'commission', label: 'Commission', href: '/livehost/commission', icon: DollarSign },
      { key: 'payroll', label: 'Payroll', href: '/livehost/payroll', icon: Banknote },
      { key: 'tiktok-imports', label: 'TikTok Imports', href: '/livehost/tiktok-imports', icon: FileSpreadsheet },
    ],
  },
];
```

**Step 2: Add the visibility helper**

Above the component function body:

```jsx
const NAV_ITEM_PERMISSION = {
  dashboard: null,               // always visible
  hosts: null,                   // always visible (read-only for assistant)
  recruitment: 'canRecruit',
  'time-slots': null,
  'session-slots': null,
  'platform-accounts': null,     // always visible (read-only for assistant)
  creators: null,                // always visible (read-only for assistant)
  sessions: 'canSeeSessions',
  commission: 'canSeeFinancials',
  payroll: 'canSeePayroll',
  'tiktok-imports': 'canSeeTiktokImports',
};

function canSee(itemKey, permissions) {
  const flag = NAV_ITEM_PERMISSION[itemKey];
  if (flag === null || flag === undefined) return true;
  return Boolean(permissions?.[flag]);
}
```

**Step 3: Filter groups at render time**

Inside the component, where `NAV_GROUPS.map(...)` renders the sidebar, replace with:

```jsx
const { auth } = usePage().props;
const permissions = auth?.permissions ?? {};

const visibleGroups = NAV_GROUPS
  .map((group) => ({
    ...group,
    items: group.items.filter((item) => canSee(item.key, permissions)),
  }))
  .filter((group) => group.items.length > 0);

// … then in JSX:
{visibleGroups.map((group) => ( /* existing group render */ ))}
```

`usePage` comes from `@inertiajs/react`. If it's not imported at the top of the file already, add the import.

**Step 4: Build and manually verify**

```bash
npm run build
```

Expected: build succeeds.

**Step 5: Manual smoke test**

Start the dev environment:

```bash
composer run dev
```

Open the site at the Herd URL (`https://mudeerbedaie.test/livehost`). Log in as the operator (`admin@example.com` / `password`), which is role `admin` — sidebar should show the full NAV_GROUPS tree (three groups, all items).

Create a test assistant user via Tinker:

```bash
php artisan tinker --no-interaction --execute="App\Models\User::factory()->liveHostAssistant()->create(['email' => 'assistant@example.com', 'password' => bcrypt('password')])"
```

Log out, log in as `assistant@example.com` / `password`. Sidebar should show:
- Operations: Dashboard, Live Hosts (no Recruitment)
- Allocation: Time Slots, Session Slots, Platform Accounts, Creators
- Records group entirely absent

If anything deviates, inspect `auth.permissions` in browser devtools (React DevTools → Inertia page props).

**Step 6: Commit**

```bash
git add resources/js/livehost/layouts/LiveHostLayout.jsx
git commit -m "$(cat <<'EOF'
feat(livehost): filter sidebar nav by auth.permissions

Each NAV_GROUPS item maps to a permission flag. Groups whose items are
fully filtered out are hidden. livehost_assistant users see only
Operations (Dashboard + Hosts) and Allocation (Time Slots, Session
Slots, Platform Accounts, Creators). No Records group.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

## Task 6 — Per-page action button hiding

**Files:**
- Modify: `resources/js/livehost/pages/hosts/Index.jsx`
- Modify: `resources/js/livehost/pages/hosts/Show.jsx`
- Modify: `resources/js/livehost/pages/platform-accounts/Index.jsx`
- Modify: `resources/js/livehost/pages/platform-accounts/Show.jsx`
- Modify: `resources/js/livehost/pages/creators/Index.jsx`

**Step 1: Pattern — read the flag at the top of each page**

```jsx
import { usePage } from '@inertiajs/react';
// …
const { auth } = usePage().props;
const canManageHosts = Boolean(auth?.permissions?.canManageHosts);
```

Swap `canManageHosts` for the relevant flag per page (`canManagePlatformAccounts`, `canManageCreators`).

**Step 2: Wrap action buttons and links in conditionals**

Examples:

```jsx
{canManageHosts && (
  <Link href="/livehost/hosts/create" className="...">
    New host
  </Link>
)}

{canManageHosts && (
  <button onClick={() => handleDelete(host.id)}>Delete</button>
)}
```

For entire editing sections (commission profile editor, platform rates, tier editor on `hosts/Show.jsx`), wrap the section wrapper:

```jsx
{canManageHosts && (
  <section>
    {/* commission profile UI */}
  </section>
)}
```

Buttons / links to audit per page:

- `hosts/Index.jsx`: "New host" / "Create host" primary button; row-level Edit and Delete; any inline commission-edit.
- `hosts/Show.jsx`: "Edit host" link; commission-profile create/update UI; platform-rates add/edit/delete; tiers add/edit/delete; attach/update/detach platform accounts.
- `platform-accounts/Index.jsx`: "New account" button; row Edit, Delete.
- `platform-accounts/Show.jsx`: Edit link, Delete button.
- `creators/Index.jsx`: Add creator form/button, row Edit, Delete.

**Step 3: Build and smoke-test as the assistant**

```bash
npm run build
```

Then log in as `assistant@example.com` and visit each page. Confirm no edit/create/delete UI is visible.

**Step 4: Confirm no console/network errors**

In browser devtools:
- No red console errors.
- No 403 or 500 responses on page load.

If the page crashes because some code path assumes the user can edit (e.g. a form defaulting to an edit mode), wrap that logic too.

**Step 5: Commit**

```bash
git add resources/js/livehost/pages
git commit -m "$(cat <<'EOF'
feat(livehost): hide write action UI from livehost_assistant on shared pages

Hosts, platform-accounts, and creators pages gate their Create / Edit /
Delete controls and PIC-only editor sections behind auth.permissions
flags. Assistants see the content as read-only.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

## Task 7 — SchedulerDashboard page + controller branching

**Files:**
- Modify: `app/Http/Controllers/LiveHost/DashboardController.php`
- Create: `resources/js/livehost/pages/SchedulerDashboard.jsx`
- Test: `tests/Feature/LiveHost/LiveHostAssistantRoleTest.php`

**Step 1: Write the failing test**

Append to `LiveHostAssistantRoleTest.php`:

```php
it('renders SchedulerDashboard for livehost_assistant', function () {
    $assistant = User::factory()->liveHostAssistant()->create();

    $response = $this->actingAs($assistant)->get(route('livehost.dashboard'));

    $response->assertInertia(fn ($page) => $page
        ->component('SchedulerDashboard')
        ->has('stats.coveragePercent')
        ->has('stats.unassignedCount')
        ->has('stats.activeHosts')
        ->has('stats.platformAccounts')
        ->has('unassignedThisWeek')
        ->has('todaySlots')
    );
});

it('still renders Dashboard for admin_livehost', function () {
    $pic = User::factory()->create(['role' => 'admin_livehost']);

    $response = $this->actingAs($pic)->get(route('livehost.dashboard'));

    $response->assertInertia(fn ($page) => $page->component('Dashboard'));
});
```

**Step 2: Run to confirm failure**

```bash
php artisan test --compact --filter="SchedulerDashboard|still renders Dashboard"
```

Expected: FAIL — `DashboardController@index` always renders `Dashboard` today.

**Step 3: Update `DashboardController@index`**

In `app/Http/Controllers/LiveHost/DashboardController.php`, modify `index()`:

```php
public function index(Request $request): Response
{
    $user = $request->user();

    if ($user?->isLiveHostAssistant()) {
        return Inertia::render('SchedulerDashboard', $this->schedulerStats());
    }

    // existing admin PIC dashboard render
    return Inertia::render('Dashboard', /* existing props */);
}
```

Add the helper in the same controller:

```php
/**
 * Stats for the scheduler-focused dashboard. No GMV, commission, or
 * financial fields — the assistant role must not see revenue.
 *
 * @return array<string, mixed>
 */
private function schedulerStats(): array
{
    $weekStart = now()->startOfWeek()->toDateString();
    $weekEnd = now()->endOfWeek()->toDateString();
    $today = today()->toDateString();

    $weekSlots = \App\Models\LiveScheduleAssignment::query()
        ->where('is_template', false)
        ->whereBetween('schedule_date', [$weekStart, $weekEnd])
        ->get(['id', 'schedule_date', 'live_host_id', 'platform_account_id', 'status']);

    $assignedCount = $weekSlots->whereNotNull('live_host_id')->count();
    $totalCount = $weekSlots->count();

    $unassignedThisWeek = $weekSlots
        ->whereNull('live_host_id')
        ->take(20)
        ->values();

    $todaySlots = \App\Models\LiveScheduleAssignment::query()
        ->where('is_template', false)
        ->whereDate('schedule_date', $today)
        ->with(['liveHost:id,name', 'platformAccount:id,label'])
        ->orderBy('schedule_date')
        ->orderBy('id')
        ->get(['id', 'schedule_date', 'live_host_id', 'platform_account_id', 'status']);

    return [
        'stats' => [
            'coveragePercent' => $totalCount === 0 ? 0 : (int) round($assignedCount / $totalCount * 100),
            'unassignedCount' => $totalCount - $assignedCount,
            'activeHosts' => \App\Models\User::query()->where('role', 'live_host')->count(),
            'platformAccounts' => \App\Models\PlatformAccount::query()->count(),
        ],
        'unassignedThisWeek' => $unassignedThisWeek->map(fn ($s) => [
            'id' => $s->id,
            'schedule_date' => $s->schedule_date,
            'platform_account_id' => $s->platform_account_id,
            'status' => $s->status,
        ]),
        'todaySlots' => $todaySlots->map(fn ($s) => [
            'id' => $s->id,
            'schedule_date' => $s->schedule_date,
            'status' => $s->status,
            'host_name' => $s->liveHost?->name,
            'platform_account_label' => $s->platformAccount?->label,
        ]),
    ];
}
```

**IMPORTANT:** If `LiveScheduleAssignment` has different column names than assumed (e.g. `scheduled_at` datetime instead of `schedule_date` + `day_of_week`), inspect the model / migration first and adapt. The `SessionSlotController::index` method is the authoritative reference for the column layout.

Also verify relationship names on `LiveScheduleAssignment` — `liveHost()` and `platformAccount()` are likely but not guaranteed. Check the model.

**Step 4: Create the React page**

Create `resources/js/livehost/pages/SchedulerDashboard.jsx`. Use the existing `LiveHostLayout`, Tailwind utilities, and Lucide icons for consistency with the rest of the SPA. A minimal starting point (the frontend-design skill will polish it after the flow works):

```jsx
import { usePage, Link } from '@inertiajs/react';
import LiveHostLayout from '../layouts/LiveHostLayout';
import { Calendar, Users, Store, AlertCircle } from 'lucide-react';

export default function SchedulerDashboard({ stats, unassignedThisWeek, todaySlots }) {
  return (
    <LiveHostLayout title="Scheduler Dashboard">
      <div className="mx-auto max-w-7xl p-6 space-y-6">
        <header>
          <h1 className="text-2xl font-semibold">Scheduler Dashboard</h1>
          <p className="text-sm text-muted-foreground">
            This week's coverage and today's slots.
          </p>
        </header>

        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <StatCard label="This week coverage" value={`${stats.coveragePercent}%`} icon={Calendar} />
          <StatCard label="Unassigned slots" value={stats.unassignedCount} icon={AlertCircle} />
          <StatCard label="Active hosts" value={stats.activeHosts} icon={Users} />
          <StatCard label="Platform accounts" value={stats.platformAccounts} icon={Store} />
        </div>

        <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
          <section className="rounded-lg border p-4">
            <h2 className="text-sm font-semibold mb-3">Unassigned — this week</h2>
            {unassignedThisWeek.length === 0 ? (
              <p className="text-sm text-muted-foreground">All slots are assigned. 🎯</p>
            ) : (
              <ul className="space-y-2">
                {unassignedThisWeek.map((s) => (
                  <li key={s.id} className="flex items-center justify-between text-sm">
                    <span>{s.schedule_date}</span>
                    <Link href={`/livehost/session-slots/${s.id}/edit`} className="text-blue-600 hover:underline">
                      Assign
                    </Link>
                  </li>
                ))}
              </ul>
            )}
          </section>

          <section className="rounded-lg border p-4">
            <h2 className="text-sm font-semibold mb-3">Today</h2>
            {todaySlots.length === 0 ? (
              <p className="text-sm text-muted-foreground">Nothing on today.</p>
            ) : (
              <ul className="space-y-2">
                {todaySlots.map((s) => (
                  <li key={s.id} className="flex items-center justify-between text-sm">
                    <span>{s.host_name ?? 'Unassigned'}</span>
                    <span className="text-muted-foreground">{s.platform_account_label}</span>
                    <span className="uppercase text-[10px] tracking-wider">{s.status}</span>
                  </li>
                ))}
              </ul>
            )}
          </section>
        </div>

        <div className="flex flex-wrap gap-2">
          <Link href="/livehost/session-slots/create" className="rounded-md border px-3 py-1.5 text-sm">
            + New session slot
          </Link>
          <Link href="/livehost/time-slots" className="rounded-md border px-3 py-1.5 text-sm">
            Manage time slots
          </Link>
          <Link href="/livehost/session-slots" className="rounded-md border px-3 py-1.5 text-sm">
            Open calendar
          </Link>
        </div>
      </div>
    </LiveHostLayout>
  );
}

function StatCard({ label, value, icon: Icon }) {
  return (
    <div className="rounded-lg border p-4 flex items-center gap-3">
      <Icon className="h-5 w-5 text-muted-foreground" />
      <div>
        <div className="text-xs uppercase tracking-wider text-muted-foreground">{label}</div>
        <div className="text-xl font-semibold">{value}</div>
      </div>
    </div>
  );
}
```

The styling matches the minimalist, borderless card language already used in the livehost SPA. If Tailwind classes / design tokens differ in this repo, align with whatever the existing `Dashboard.jsx` uses.

**Step 5: Build and run tests**

```bash
npm run build
php artisan test --compact --filter="SchedulerDashboard|still renders Dashboard"
```

Expected: 2 PASS.

**Step 6: Manual smoke test**

Log in as `assistant@example.com`, land on `/livehost`. Should see the SchedulerDashboard with the 4 stat cards, unassigned list, today list, and quick-action buttons. No GMV, no commission, no payroll cards.

Log in as `admin@example.com` (role `admin`), land on `/livehost`. Should see the full PIC Dashboard unchanged.

**Step 7: Commit**

```bash
git add app/Http/Controllers/LiveHost/DashboardController.php resources/js/livehost/pages/SchedulerDashboard.jsx tests/Feature/LiveHost/LiveHostAssistantRoleTest.php
git commit -m "$(cat <<'EOF'
feat(livehost): add SchedulerDashboard for livehost_assistant

Dashboard controller branches on role: assistants get a new scheduling-
focused dashboard with coverage %, unassigned slot count, active
hosts/platform accounts, this-week unassigned list, and today's slots.
No GMV, commission, payroll, or live-now data exposed.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

## Task 8 — Route-walker safety test

Catches any future admin-only route added without role middleware.

**Files:**
- Modify: `tests/Feature/LiveHost/LiveHostAssistantRoleTest.php`

**Step 1: Write the test**

```php
it('every non-shared /livehost route returns 403 for the assistant', function () {
    $assistant = User::factory()->liveHostAssistant()->create();

    $shared = [
        'livehost.dashboard',
        'livehost.hosts.index',
        'livehost.hosts.show',
        'livehost.platform-accounts.index',
        'livehost.platform-accounts.show',
        'livehost.creators.index',
        'livehost.time-slots.index',
        'livehost.time-slots.create',
        'livehost.time-slots.store',
        'livehost.time-slots.edit',
        'livehost.time-slots.update',
        'livehost.time-slots.destroy',
        'livehost.session-slots.index',
        'livehost.session-slots.calendar',
        'livehost.session-slots.table',
        'livehost.session-slots.preview',
        'livehost.session-slots.create',
        'livehost.session-slots.store',
        'livehost.session-slots.show',
        'livehost.session-slots.edit',
        'livehost.session-slots.update',
        'livehost.session-slots.destroy',
    ];

    $livehostRoutes = collect(\Route::getRoutes())
        ->filter(fn ($r) => str_starts_with($r->getName() ?? '', 'livehost.'))
        ->filter(fn ($r) => ! in_array($r->getName(), $shared, true));

    $failures = [];

    foreach ($livehostRoutes as $route) {
        // Skip routes that require model binding params we can't satisfy in a smoke test.
        if (str_contains($route->uri(), '{')) continue;

        $methods = array_diff($route->methods(), ['HEAD']);
        foreach ($methods as $method) {
            $response = $this->actingAs($assistant)->call($method, $route->uri());
            if ($response->status() !== 403) {
                $failures[] = "{$method} {$route->uri()} returned {$response->status()}";
            }
        }
    }

    expect($failures)->toBe([]);
});
```

**Step 2: Run it**

```bash
php artisan test --compact --filter="every non-shared /livehost route"
```

Expected: PASS. If it fails, the failure list pinpoints the route that slipped the middleware net.

**Step 3: Commit**

```bash
git add tests/Feature/LiveHost/LiveHostAssistantRoleTest.php
git commit -m "$(cat <<'EOF'
test(livehost): route-walker guard — assert every non-shared /livehost
route 403s the assistant

Catches any future admin-only route added without role middleware.
Parameterized routes are skipped (binding setup out of scope); all
no-param routes must return 403 for livehost_assistant.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

## Task 9 — Pint formatting + full suite pass

**Step 1: Run Pint**

```bash
vendor/bin/pint --dirty
```

**Step 2: Run the LiveHost suite**

```bash
php artisan test --compact tests/Feature/LiveHost
```

Expected: all green.

**Step 3: Ask the operator whether to run the full test suite**

Per project CLAUDE.md convention: after the targeted suite passes, ask the user before running `php artisan test --compact` (whole suite).

**Step 4: Commit (if Pint made changes)**

```bash
git add -A
git commit -m "$(cat <<'EOF'
chore: pint formatting

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

## Task 10 — Operator walkthrough

**Steps:**

1. Tell the operator the role is live on `main`.
2. Walk them through creating an assistant: `/admin/users` → new user → role `Livehost Assistant` (role picker label derives automatically from `ucwords(str_replace('_', ' ', role))` per `User::getRoleName()`).
3. Ask them to log in as the new assistant and confirm the sidebar / dashboard look right.
4. Note the follow-ups from the design doc (per-assistant host scoping, audit log) as future work — not starting them now.

---

## Rollback plan

If something goes wrong after deploy:

```bash
php artisan migrate:rollback --step=1
```

This reverts the enum back to not including `livehost_assistant`. Then `git revert` the feature commits in reverse order. Any users that had role `livehost_assistant` get demoted to `student` by the migration's `down()` method.

---

## Summary of files touched

**Created:**
- `database/migrations/2026_04_24_*_add_livehost_assistant_role_to_users_table.php`
- `tests/Feature/LiveHost/LiveHostAssistantRoleTest.php`
- `resources/js/livehost/pages/SchedulerDashboard.jsx`

**Modified:**
- `app/Models/User.php`
- `database/factories/UserFactory.php`
- `routes/web.php`
- `app/Http/Middleware/HandleInertiaRequests.php`
- `app/Http/Controllers/LiveHost/DashboardController.php`
- 14 other LiveHost controllers (adding `abort_if` guards)
- `resources/js/livehost/layouts/LiveHostLayout.jsx`
- `resources/js/livehost/pages/hosts/Index.jsx`
- `resources/js/livehost/pages/hosts/Show.jsx`
- `resources/js/livehost/pages/platform-accounts/Index.jsx`
- `resources/js/livehost/pages/platform-accounts/Show.jsx`
- `resources/js/livehost/pages/creators/Index.jsx`

**Commits (in order):**
1. `feat(livehost): add livehost_assistant role to user enum`
2. `feat(livehost): split /livehost routes into shared + admin-only groups`
3. `feat(livehost): add controller-level guards for admin-only actions`
4. `feat(livehost): share auth.permissions + role-filtered nav counts`
5. `feat(livehost): filter sidebar nav by auth.permissions`
6. `feat(livehost): hide write action UI from livehost_assistant on shared pages`
7. `feat(livehost): add SchedulerDashboard for livehost_assistant`
8. `test(livehost): route-walker guard — assert every non-shared /livehost route 403s the assistant`
9. `chore: pint formatting` (if any)
