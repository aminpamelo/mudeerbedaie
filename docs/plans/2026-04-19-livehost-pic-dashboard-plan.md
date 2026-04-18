# Live Host PIC Dashboard — Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Build a dedicated Inertia.js + React dashboard at `/livehost` for the `admin_livehost` PIC role, replacing the existing `/admin/live-hosts/*` Volt views in phased rollout.

**Architecture:** Inertia.js bridges Laravel controllers to React page components — no separate API layer. `admin_livehost` and `admin` roles gated via existing role middleware. Reuses all existing Eloquent models (`User`, `LiveSession`, `LiveSchedule`, `LiveTimeSlot`, `PlatformAccount`, etc.). shadcn/ui components themed to the approved "Pulse" visual palette.

**Tech Stack:** Laravel 12 + `inertiajs/inertia-laravel` (new), React 19 + `@inertiajs/react` (new), Tailwind v4 (existing), shadcn/ui + Radix (new), Geist fonts, lucide-react (existing via HR), Pest v4 (existing).

**Design doc:** `docs/plans/2026-04-18-livehost-pic-dashboard-design.md`
**Visual mockup:** `docs/design-mockups/livehost-dashboard.html`

**Rollout phases:**
- **Phase 0** — Discovery + scaffolding (15 tasks)
- **Phase 1** — Dashboard + Live Hosts CRUD (22 tasks)
- **Phase 2** — Schedules + Time Slots + Session Slots + Live Sessions (4 resource groups, pattern-based)
- **Phase 3** — Parity verification + Volt retirement

---

## Cross-cutting conventions

**Test runner:** `php artisan test --compact --filter=<filter>` for scoped runs. Full suite only before each phase's final commit.

**Pint formatting:** Run `vendor/bin/pint --dirty` before every commit.

**DB compatibility:** Any new migrations must handle both MySQL and SQLite per CLAUDE.md. v1 adds no new migrations — reuses existing tables.

**Commit frequency:** Commit after each task completes (test green + pint clean). Each commit message starts with a scope prefix: `feat(livehost):`, `test(livehost):`, `chore(livehost):`, etc.

**Role used in tests:** `admin_livehost` is the primary test actor. Use `User::factory()->create(['role' => 'admin_livehost'])`.

---

## Phase 0 — Discovery + Scaffolding

**Outcome:** Inertia installed, empty `/livehost` dashboard renders for `admin_livehost`/`admin` roles, existing Volt admin untouched. Engineer has read and documented existing Volt behavior so Phase 1 preserves semantics.

---

### Task 0.1: Read existing Volt admin views and document semantics

**Why:** The design doc flagged deletion semantics and form fields as open questions. Resolve before writing any code.

**Files to read:**
- `resources/views/livewire/admin/live-hosts-list.blade.php`
- `resources/views/livewire/admin/live-hosts-create.blade.php`
- `resources/views/livewire/admin/live-hosts-show.blade.php`
- `resources/views/livewire/admin/live-hosts-edit.blade.php`
- `app/Models/User.php` (specifically `scopeLiveHosts`, `isLiveHost`, status column if any)

**Step 1: Skim each file and capture:**
- Form fields (name, email, phone, status, notes, platform accounts, ...)
- Validation rules
- Delete behavior (soft-delete user? strip `live_host` role? cascade `LiveSession`?)
- Status column name + possible values
- How "active/inactive" is computed
- Any status toggle actions (activate/deactivate?)
- Relationships shown in the show view (platform accounts, recent sessions, stats)

**Step 2: Write findings to** `docs/plans/livehost-volt-semantics.md` as a checklist the implementer will use while building Phase 1.

**Step 3: Commit**

```bash
git add docs/plans/livehost-volt-semantics.md
git commit -m "docs(livehost): capture existing Volt admin semantics for parity"
```

---

### Task 0.2: Install Inertia backend package

**Step 1:** Run:
```bash
composer require inertiajs/inertia-laravel
```

**Step 2:** Verify `composer.json` now includes it under `require`.

**Step 3: Commit**
```bash
git add composer.json composer.lock
git commit -m "chore(livehost): install inertiajs/inertia-laravel"
```

---

### Task 0.3: Install Inertia React client + shadcn runtime deps

**Step 1:** Run:
```bash
npm install @inertiajs/react class-variance-authority clsx tailwind-merge
```

**Step 2:** Verify `package.json` dependencies include all four.

**Step 3: Commit**
```bash
git add package.json package-lock.json
git commit -m "chore(livehost): install @inertiajs/react + shadcn runtime deps"
```

---

### Task 0.4: Publish the `HandleInertiaRequests` middleware

**Step 1:** Run:
```bash
php artisan inertia:middleware
```

This creates `app/Http/Middleware/HandleInertiaRequests.php`.

**Step 2:** Register the middleware in `bootstrap/app.php`:

Find the `withMiddleware(function (Middleware $middleware) {...})` section and append to the `web` group:

```php
$middleware->web(append: [
    \App\Http\Middleware\HandleInertiaRequests::class,
]);
```

**Step 3:** Edit `app/Http/Middleware/HandleInertiaRequests.php` `share()` method:

```php
public function share(Request $request): array
{
    return [
        ...parent::share($request),
        'auth' => [
            'user' => fn () => $request->user()
                ? [
                    'id' => $request->user()->id,
                    'name' => $request->user()->name,
                    'email' => $request->user()->email,
                    'role' => $request->user()->role,
                ]
                : null,
        ],
        'flash' => [
            'success' => fn () => $request->session()->get('success'),
            'error' => fn () => $request->session()->get('error'),
        ],
    ];
}
```

**Step 4: Commit**
```bash
vendor/bin/pint --dirty
git add bootstrap/app.php app/Http/Middleware/HandleInertiaRequests.php
git commit -m "feat(livehost): register Inertia middleware + share auth/flash"
```

---

### Task 0.5: Create root Blade template for Inertia

**File:** `resources/views/livehost/app.blade.php`

**Content:**
```blade
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="bg-[#FAFAFA]">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title inertia>{{ config('app.name', 'Live Host Desk') }}</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Geist:wght@100..900&family=Geist+Mono:wght@100..900&display=swap" rel="stylesheet">

    @routes
    @viteReactRefresh
    @vite(['resources/js/livehost/app.jsx', 'resources/js/livehost/styles/livehost.css'])
    @inertiaHead
</head>
<body class="font-sans antialiased">
    @inertia
</body>
</html>
```

Note: `@routes` is Ziggy (used by HR). If Ziggy isn't installed project-wide, confirm via `composer show tightenco/ziggy`. If missing, drop `@routes` for now and use plain URLs — we'll add Ziggy in Task 0.6 if needed.

**Step 1:** Check Ziggy:
```bash
composer show tightenco/ziggy 2>/dev/null && echo "installed" || echo "missing"
```

**Step 2:** If missing, install:
```bash
composer require tightenco/ziggy
```

**Step 3:** Create the Blade file.

**Step 4: Commit**
```bash
git add resources/views/livehost/app.blade.php composer.json composer.lock
git commit -m "feat(livehost): add root Blade template for Inertia mount"
```

---

### Task 0.6: Create the Pulse Tailwind + shadcn CSS

**File:** `resources/js/livehost/styles/livehost.css`

**Content:**
```css
@import "tailwindcss";

@theme {
  --font-sans: "Geist", system-ui, sans-serif;
  --font-mono: "Geist Mono", monospace;

  --color-canvas: #FAFAFA;
  --color-surface: #FFFFFF;
  --color-surface-2: #F5F5F5;
  --color-border: #EAEAEA;
  --color-border-2: #F0F0F0;
  --color-ink: #0A0A0A;
  --color-ink-2: #404040;
  --color-muted: #737373;
  --color-muted-2: #A3A3A3;

  --color-emerald: #10B981;
  --color-emerald-ink: #059669;
  --color-emerald-soft: #ECFDF5;
  --color-amber: #F59E0B;
  --color-amber-soft: #FFFBEB;
  --color-rose: #F43F5E;
  --color-rose-soft: #FFF1F2;
  --color-sky: #0EA5E9;
  --color-violet: #8B5CF6;

  --radius-card: 16px;
}

/* shadcn/ui CSS vars mapped to Pulse palette */
:root {
  --background: 0 0% 98%;
  --foreground: 0 0% 4%;
  --card: 0 0% 100%;
  --card-foreground: 0 0% 4%;
  --popover: 0 0% 100%;
  --popover-foreground: 0 0% 4%;
  --primary: 0 0% 4%;
  --primary-foreground: 0 0% 100%;
  --secondary: 0 0% 96%;
  --secondary-foreground: 0 0% 4%;
  --muted: 0 0% 96%;
  --muted-foreground: 0 0% 45%;
  --accent: 0 0% 96%;
  --accent-foreground: 0 0% 4%;
  --destructive: 347 77% 60%;
  --destructive-foreground: 0 0% 100%;
  --border: 0 0% 92%;
  --input: 0 0% 92%;
  --ring: 160 84% 39%;
  --radius: 0.75rem;
}

html, body { background: var(--color-canvas); color: var(--color-ink); }

body {
  background-image:
    radial-gradient(ellipse 900px 500px at 0% 0%, rgba(16,185,129,0.08), transparent 70%),
    radial-gradient(ellipse 700px 400px at 100% 0%, rgba(245,158,11,0.06), transparent 70%),
    radial-gradient(ellipse 600px 400px at 50% 100%, rgba(139,92,246,0.04), transparent 70%);
  background-attachment: fixed;
  font-feature-settings: "ss01", "cv11";
  letter-spacing: -0.01em;
}

@keyframes pulse-dot {
  70%  { box-shadow: 0 0 0 6px rgba(16,185,129,0); }
  100% { box-shadow: 0 0 0 0 rgba(16,185,129,0); }
}
.pulse-dot {
  background: var(--color-emerald);
  box-shadow: 0 0 0 0 rgba(16,185,129,0.6);
  animation: pulse-dot 1.6s ease-out infinite;
  border-radius: 9999px;
}
```

**Step 1:** Create the file.

**Step 2: Commit**
```bash
git add resources/js/livehost/styles/livehost.css
git commit -m "feat(livehost): add Pulse Tailwind theme + shadcn CSS vars"
```

---

### Task 0.7: Create the Inertia client entry

**File:** `resources/js/livehost/app.jsx`

**Content:**
```jsx
import './styles/livehost.css';
import { createInertiaApp } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';

createInertiaApp({
  title: (title) => title ? `${title} · Live Host Desk` : 'Live Host Desk',
  resolve: (name) => {
    const pages = import.meta.glob('./pages/**/*.jsx', { eager: true });
    const page = pages[`./pages/${name}.jsx`];
    if (!page) {
      throw new Error(`[livehost] page not found: ${name}`);
    }
    return page;
  },
  setup({ el, App, props }) {
    createRoot(el).render(<App {...props} />);
  },
  progress: { color: '#10B981' },
});
```

**Step 1:** Create the file.

**Step 2: Commit**
```bash
git add resources/js/livehost/app.jsx
git commit -m "feat(livehost): add Inertia client entry with Pulse progress color"
```

---

### Task 0.8: Wire Vite entries

**File:** `vite.config.js`

**Modify:** Find the `laravel({ input: [...] })` block and append:

```js
'resources/js/livehost/app.jsx',
'resources/js/livehost/styles/livehost.css',
```

**Step 1:** Apply the edit.

**Step 2:** Verify build:
```bash
npm run build
```
Expected: build completes without errors; manifest contains `resources/js/livehost/app.jsx`.

**Step 3: Commit**
```bash
git add vite.config.js
git commit -m "chore(livehost): register livehost Vite entries"
```

---

### Task 0.9: Scaffold shadcn components

shadcn is copy-paste, not a library. We'll seed a minimal set; more can be added on demand in Phase 1.

**Step 1:** Create `components.json` at project root (if not already existing):

```json
{
  "$schema": "https://ui.shadcn.com/schema.json",
  "style": "new-york",
  "rsc": false,
  "tsx": false,
  "tailwind": {
    "config": "",
    "css": "resources/js/livehost/styles/livehost.css",
    "baseColor": "neutral",
    "cssVariables": true,
    "prefix": ""
  },
  "aliases": {
    "components": "resources/js/livehost/components",
    "utils": "resources/js/livehost/lib/utils",
    "ui": "resources/js/livehost/components/ui",
    "lib": "resources/js/livehost/lib",
    "hooks": "resources/js/livehost/hooks"
  }
}
```

**Step 2:** Create `resources/js/livehost/lib/utils.js`:

```js
import { clsx } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs) {
  return twMerge(clsx(inputs));
}
```

**Step 3:** Add components via CLI:
```bash
npx shadcn@latest add button input label card table badge dialog dropdown-menu form sonner
```

When prompted, accept the defaults matching `components.json`.

**Step 4:** Verify files exist in `resources/js/livehost/components/ui/`.

**Step 5: Commit**
```bash
vendor/bin/pint --dirty
git add components.json resources/js/livehost/lib resources/js/livehost/components package.json package-lock.json
git commit -m "feat(livehost): seed shadcn components (button, input, card, table, ...)"
```

---

### Task 0.10: Create the LiveHostLayout component

**File:** `resources/js/livehost/layouts/LiveHostLayout.jsx`

**Content:** Implement the sidebar + glass-effect top bar from the approved Pulse mockup (`docs/design-mockups/livehost-dashboard.html`, sidebar + `.top` section). Key pieces:

```jsx
import { Link, usePage } from '@inertiajs/react';
import { Home, Users, Calendar, Clock, LayoutGrid, Play, BarChart3 } from 'lucide-react';
import { cn } from '@/lib/utils';

const NAV = {
  operations: [
    { label: 'Dashboard', href: '/livehost', icon: Home },
    { label: 'Live Hosts', href: '/livehost/hosts', icon: Users, count: 'hosts' },
    { label: 'Schedules', href: '/livehost/schedules', icon: Calendar, count: 'schedules' },
  ],
  allocation: [
    { label: 'Time Slots', href: '/livehost/time-slots', icon: Clock },
    { label: 'Session Slots', href: '/livehost/session-slots', icon: LayoutGrid },
  ],
  records: [
    { label: 'Live Sessions', href: '/livehost/sessions', icon: Play, count: 'sessions' },
  ],
};

export default function LiveHostLayout({ children }) {
  const { auth, navCounts = {}, url } = usePage().props;
  const isActive = (href) => href === '/livehost' ? url === '/livehost' : url.startsWith(href);

  return (
    <div className="grid grid-cols-[240px_1fr] min-h-screen">
      <Sidebar auth={auth} navCounts={navCounts} isActive={isActive} />
      <main className="flex flex-col">
        {children}
      </main>
    </div>
  );
}

function Sidebar({ auth, navCounts, isActive }) { /* ... per mockup ... */ }
```

Implement the full sidebar (brand logo, search box with ⌘K, three nav groups with counts, user menu footer) and export `<TopBar breadcrumb={...} actions={...} />` as a named export for pages to compose.

**Step 1:** Create the file with the full layout.

**Step 2:** Run:
```bash
npm run build
```
Expected: builds without errors.

**Step 3: Commit**
```bash
git add resources/js/livehost/layouts
git commit -m "feat(livehost): add LiveHostLayout with Pulse sidebar + glass top bar"
```

---

### Task 0.11: Create placeholder Dashboard page

**File:** `resources/js/livehost/pages/Dashboard.jsx`

**Content:**
```jsx
import { Head } from '@inertiajs/react';
import LiveHostLayout, { TopBar } from '@/livehost/layouts/LiveHostLayout';

export default function Dashboard({ auth }) {
  return (
    <>
      <Head title="Dashboard" />
      <TopBar breadcrumb={['Live Host Desk', 'Dashboard']} />
      <div className="p-8">
        <h1 className="text-3xl font-semibold tracking-tight">
          Good afternoon, {auth.user.name.split(' ')[0]}
        </h1>
        <p className="text-muted mt-2">Dashboard coming online. KPIs in Phase 1.</p>
      </div>
    </>
  );
}

Dashboard.layout = (page) => <LiveHostLayout>{page}</LiveHostLayout>;
```

**Step 1:** Create the file.

**Step 2: Commit**
```bash
git add resources/js/livehost/pages/Dashboard.jsx
git commit -m "feat(livehost): add placeholder Dashboard page"
```

---

### Task 0.12: Create the DashboardController stub

**File:** `app/Http/Controllers/LiveHost/DashboardController.php`

**Step 1:** Create with artisan:
```bash
php artisan make:controller LiveHost/DashboardController --no-interaction
```

**Step 2:** Implement:

```php
<?php

namespace App\Http\Controllers\LiveHost;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Dashboard', [
            'navCounts' => [
                'hosts' => 0,
                'schedules' => 0,
                'sessions' => 0,
            ],
        ]);
    }
}
```

**Step 3: Commit**
```bash
vendor/bin/pint --dirty
git add app/Http/Controllers/LiveHost/DashboardController.php
git commit -m "feat(livehost): add DashboardController stub"
```

---

### Task 0.13: Wire the `/livehost` routes

**File:** `routes/web.php`

**Step 1:** Find the existing `/live-host` group (around line 153) and add a **new** block after it:

```php
Route::middleware(['auth', 'role:admin_livehost,admin'])
    ->prefix('livehost')
    ->name('livehost.')
    ->group(function () {
        Route::get('/', [\App\Http\Controllers\LiveHost\DashboardController::class, 'index'])
            ->name('dashboard');
    });
```

**Step 2:** Verify:
```bash
php artisan route:list --path=livehost
```
Expected: Shows `GET /livehost` → `livehost.dashboard`.

**Step 3: Commit**
```bash
git add routes/web.php
git commit -m "feat(livehost): add /livehost dashboard route"
```

---

### Task 0.14: Write access tests (TDD — write first, implementation already exists)

**File:** `tests/Feature/LiveHost/AccessTest.php`

**Step 1:** Create via artisan:
```bash
php artisan make:test LiveHost/AccessTest --pest --no-interaction
```

**Step 2:** Replace contents with:

```php
<?php

use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

it('allows admin_livehost to access the dashboard', function () {
    $pic = User::factory()->create(['role' => 'admin_livehost']);
    actingAs($pic)->get('/livehost')->assertSuccessful();
});

it('allows admin to access the dashboard', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    actingAs($admin)->get('/livehost')->assertSuccessful();
});

it('forbids live_host from accessing the PIC dashboard', function () {
    $host = User::factory()->create(['role' => 'live_host']);
    actingAs($host)->get('/livehost')->assertForbidden();
});

it('forbids regular users from accessing the PIC dashboard', function () {
    $user = User::factory()->create(['role' => 'student']);
    actingAs($user)->get('/livehost')->assertForbidden();
});

it('redirects guests to login', function () {
    get('/livehost')->assertRedirect('/login');
});
```

**Step 3:** Run:
```bash
php artisan test --compact --filter=AccessTest
```
Expected: all 5 pass (route + middleware + controller already exist from prior tasks).

If any test fails, investigate whether the existing `role` middleware recognizes `admin_livehost,admin` as a comma-separated list. Check `app/Http/Middleware/` for the role middleware class and confirm its signature — fix test setup if the middleware uses a different arg form.

**Step 4: Commit**
```bash
git add tests/Feature/LiveHost/AccessTest.php
git commit -m "test(livehost): access control for dashboard (admin_livehost/admin allow; others deny)"
```

---

### Task 0.15: Browser smoke test for empty Dashboard

**File:** `tests/Browser/LiveHost/DashboardSmokeTest.php`

**Step 1:** Create:
```bash
php artisan make:test Browser/LiveHost/DashboardSmokeTest --pest --no-interaction
```

**Step 2:** Replace contents:

```php
<?php

use App\Models\User;

it('renders the dashboard shell without JS errors', function () {
    $pic = User::factory()->create(['role' => 'admin_livehost', 'name' => 'Ahmad Amin']);
    $this->actingAs($pic);

    visit('/livehost')
        ->assertSee('Good afternoon, Ahmad')
        ->assertSee('Dashboard')
        ->assertNoJavascriptErrors();
});
```

**Step 3:** Run:
```bash
php artisan test --compact --filter=DashboardSmokeTest
```

If first run errors with "browser not installed", run:
```bash
npx playwright install chromium
```
and retry.

Expected: test passes.

**Step 4: Commit**
```bash
git add tests/Browser/LiveHost/DashboardSmokeTest.php
git commit -m "test(livehost): browser smoke test for empty Dashboard"
```

**End of Phase 0.** At this point, `/livehost` is reachable by PIC/admin, renders the Pulse layout shell with a placeholder heading, and is covered by access + smoke tests. The existing `/admin/live-hosts/*` is untouched.

---

## Phase 1 — Dashboard + Live Hosts CRUD

**Outcome:** Full Dashboard per Pulse mockup (bento KPIs, On Air panel with polling, agenda, activity, top hosts table) + complete Live Hosts CRUD (list, create, show, edit, delete) with tests.

---

### Task 1.1: Dashboard stats query

**File:** `app/Http/Controllers/LiveHost/DashboardController.php`

**Goal:** Compute the four hero KPIs + data for on-air, upcoming, activity, top hosts.

**Step 1: Write a failing test first**

`tests/Feature/LiveHost/DashboardTest.php`:

```php
<?php

use App\Models\User;
use App\Models\LiveSession;
use App\Models\PlatformAccount;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->pic = User::factory()->create(['role' => 'admin_livehost']);
});

it('exposes dashboard stats via Inertia props', function () {
    // Seed: 3 active hosts, 2 platform accounts, 1 ongoing session
    $hosts = User::factory()->count(3)->create(['role' => 'live_host']);
    PlatformAccount::factory()->count(2)->create();
    LiveSession::factory()->create(['status' => 'ongoing']);

    actingAs($this->pic)
        ->get('/livehost')
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->has('stats', fn (Assert $s) => $s
                ->where('totalHosts', 3)
                ->where('activeHosts', 3)
                ->where('platformAccounts', 2)
                ->where('liveNow', 1)
                ->etc())
            ->has('liveNow', 1)
            ->has('upcoming')
            ->has('recentActivity')
            ->has('topHosts'));
});
```

**Step 2: Run — expect fail**

```bash
php artisan test --compact --filter=DashboardTest
```
Expected: fail — props missing.

**Step 3: Implement DashboardController@index**

```php
public function index(Request $request): Response
{
    $stats = [
        'totalHosts' => User::liveHosts()->count(),
        'activeHosts' => User::liveHosts()->where('status', 'active')->count(),
        'platformAccounts' => PlatformAccount::count(),
        'liveNow' => LiveSession::where('status', 'ongoing')->count(),
        'sessionsToday' => LiveSession::whereDate('scheduled_start_at', today())->count(),
        'watchHoursToday' => $this->watchHoursToday(),
    ];

    return Inertia::render('Dashboard', [
        'stats' => $stats,
        'liveNow' => $this->liveNow(),
        'upcoming' => $this->upcoming(),
        'recentActivity' => $this->recentActivity(),
        'topHosts' => $this->topHosts(),
        'navCounts' => [
            'hosts' => $stats['totalHosts'],
            'schedules' => LiveSchedule::where('scheduled_start_at', '>=', now())->count(),
            'sessions' => LiveSession::count(),
        ],
    ]);
}

private function liveNow(): Collection
{
    return LiveSession::query()
        ->with(['platformAccount', 'liveHost'])
        ->where('status', 'ongoing')
        ->orderByDesc('actual_start_at')
        ->take(5)
        ->get()
        ->map(fn ($s) => [
            'id' => $s->id,
            'hostName' => $s->liveHost?->name,
            'platformAccount' => $s->platformAccount?->handle,
            'platformType' => $s->platformAccount?->platform,
            'startedAt' => $s->actual_start_at,
            'viewers' => $s->current_viewers ?? 0,
        ]);
}

// ...similar private methods for upcoming(), recentActivity(), topHosts()
```

Reference `User::liveHosts()` scope — if the existing `User` model doesn't have this scope, add it now in a separate sub-step.

Important: the exact field names (`status`, `current_viewers`, `actual_start_at`) must match the Volt semantics doc from Task 0.1. If any field doesn't exist, STOP and surface it to the user — don't invent schema.

**Step 4: Run — expect pass**

```bash
php artisan test --compact --filter=DashboardTest
```

**Step 5: Commit**
```bash
vendor/bin/pint --dirty
git add app/Http/Controllers/LiveHost/DashboardController.php app/Models/User.php tests/Feature/LiveHost/DashboardTest.php
git commit -m "feat(livehost): wire Dashboard stats, liveNow, upcoming, activity, topHosts"
```

---

### Task 1.2: `/livehost/live-now` JSON endpoint for polling

**Files:**
- Modify: `routes/web.php` (add route inside existing group)
- Modify: `app/Http/Controllers/LiveHost/DashboardController.php` (add `liveNow` public method)

**Step 1: Write failing test** — `tests/Feature/LiveHost/DashboardTest.php` add:

```php
it('exposes liveNow as JSON for polling', function () {
    LiveSession::factory()->count(2)->create(['status' => 'ongoing']);

    actingAs($this->pic)
        ->getJson('/livehost/live-now')
        ->assertOk()
        ->assertJsonCount(2, 'liveNow')
        ->assertJsonStructure(['liveNow' => [['id', 'hostName', 'viewers']], 'stats']);
});
```

**Step 2: Run — expect 404.**

**Step 3: Add route:**
```php
Route::get('live-now', [\App\Http\Controllers\LiveHost\DashboardController::class, 'liveNowJson'])
    ->name('live-now');
```

**Step 4: Implement `liveNowJson` method** — returns `response()->json([...])`.

**Step 5: Run — expect pass.**

**Step 6: Commit.**

---

### Task 1.3: Build StatCard component

**File:** `resources/js/livehost/components/StatCard.jsx`

Implement per mockup: variants `hero` (large number, emerald gradient), `dark` (gradient dark live card), `progress` (with bar), `ring` (with circular ring). Support `label`, `value`, `icon`, `trend`, `subtitle`, `variant`.

**Step 1:** Create file per mockup HTML.
**Step 2:** `npm run build` — verify no errors.
**Step 3:** Commit.

---

### Task 1.4: Build LiveSessionRow component

**File:** `resources/js/livehost/components/LiveSessionRow.jsx`

Per mockup: gradient thumbnail with pulse dot, host name, platform meta, viewer count, duration pill. Props: `{ hostName, initials, platformAccount, platformType, sessionId, viewers, duration, thumbColor }`.

**Step 1–3:** Same pattern.

---

### Task 1.5: Build AgendaRow component

**File:** `resources/js/livehost/components/AgendaRow.jsx` — time pill + host/meta + status chip.

---

### Task 1.6: Build ActivityFeedItem component

**File:** `resources/js/livehost/components/ActivityFeedItem.jsx` — icon bubble + body + relative time.

---

### Task 1.7: Build StatusChip component

**File:** `resources/js/livehost/components/StatusChip.jsx` — variants `live`, `prep`, `scheduled`, `done` (per mockup `.chip--*` classes).

---

### Task 1.8: Assemble the full Dashboard page

**File:** `resources/js/livehost/pages/Dashboard.jsx`

**Step 1:** Replace placeholder with full bento layout consuming the Inertia props from Task 1.1. Use the components from Tasks 1.3–1.7. Reference the mockup's `<section class="bento">` blocks.

**Step 2:** Add polling hook for `/livehost/live-now` using `fetch` every 10s (skip TanStack Query for this simple case):

```jsx
import { useEffect, useState } from 'react';

function useLiveNow(initial) {
  const [data, setData] = useState(initial);
  useEffect(() => {
    const id = setInterval(() => {
      fetch('/livehost/live-now').then(r => r.json()).then(setData);
    }, 10_000);
    return () => clearInterval(id);
  }, []);
  return data;
}
```

**Step 3:** Browser smoke — extend the existing `DashboardSmokeTest`:

```php
it('renders dashboard with live KPIs', function () {
    $pic = User::factory()->create(['role' => 'admin_livehost', 'name' => 'Ahmad Amin']);
    User::factory()->count(3)->create(['role' => 'live_host']);
    LiveSession::factory()->create(['status' => 'ongoing']);

    $this->actingAs($pic);

    visit('/livehost')
        ->assertSee('Active hosts')
        ->assertSee('Live now')
        ->assertSee('On Air now')
        ->assertNoJavascriptErrors();
});
```

**Step 4:** Run tests, `npm run build`, commit.

---

### Task 1.9: HostController@index — listing page

**File:** `app/Http/Controllers/LiveHost/HostController.php`

**Step 1: Failing test** in `tests/Feature/LiveHost/HostControllerTest.php`:

```php
it('lists live hosts with pagination and filters', function () {
    User::factory()->count(25)->create(['role' => 'live_host']);

    actingAs($this->pic)
        ->get('/livehost/hosts')
        ->assertInertia(fn (Assert $p) => $p
            ->component('hosts/Index')
            ->has('hosts.data', 15)  // default paginate
            ->has('filters'));
});

it('filters live hosts by search', function () {
    User::factory()->create(['role' => 'live_host', 'name' => 'Wan Amir']);
    User::factory()->create(['role' => 'live_host', 'name' => 'Haliza Tan']);

    actingAs($this->pic)
        ->get('/livehost/hosts?search=Wan')
        ->assertInertia(fn (Assert $p) => $p->has('hosts.data', 1));
});
```

**Step 2: Run — fail (404).**

**Step 3: Add route + implement:**

Route:
```php
Route::get('hosts', [\App\Http\Controllers\LiveHost\HostController::class, 'index'])
    ->name('hosts.index');
```

Controller:
```php
public function index(Request $request): Response
{
    $hosts = User::query()
        ->where('role', 'live_host')
        ->when($request->search, fn ($q, $s) => $q->where(fn ($q) => $q
            ->where('name', 'like', "%{$s}%")
            ->orWhere('email', 'like', "%{$s}%")))
        ->when($request->status, fn ($q, $s) => $q->where('status', $s))
        ->withCount(['platformAccounts', 'liveSessions'])
        ->latest()
        ->paginate(15)
        ->withQueryString();

    return Inertia::render('hosts/Index', [
        'hosts' => $hosts,
        'filters' => $request->only(['search', 'status']),
    ]);
}
```

**Step 4: Run — pass.**
**Step 5: Commit.**

---

### Task 1.10: Hosts Index page

**File:** `resources/js/livehost/pages/hosts/Index.jsx`

**Requirements from the mockup:**
- Page header "Live Hosts" with "+ New host" primary button
- Search + status filter row (debounced, triggers `router.get` with `preserveState: true`)
- Data table: Host (avatar + name + ID), Accounts, Sessions, Watch time, Avg viewers, Trend sparkline, Status chip, Actions dropdown
- Pagination controls (using Inertia's `hosts.links`)

**Step 1:** Implement the page using shadcn `Table`, `Input`, `Button`, `DropdownMenu`.

**Step 2:** Extract reusable `<DataTable>` and `<Pagination>` to `components/`.

**Step 3:** Browser smoke test — `tests/Browser/LiveHost/HostsIndexTest.php`:

```php
it('renders hosts index with search', function () {
    $pic = User::factory()->create(['role' => 'admin_livehost']);
    User::factory()->create(['role' => 'live_host', 'name' => 'Wan Amir']);
    User::factory()->create(['role' => 'live_host', 'name' => 'Haliza Tan']);

    $this->actingAs($pic);

    visit('/livehost/hosts')
        ->assertSee('Live Hosts')
        ->assertSee('Wan Amir')
        ->fill('search', 'Haliza')
        ->pause(500)
        ->assertDontSee('Wan Amir')
        ->assertSee('Haliza Tan')
        ->assertNoJavascriptErrors();
});
```

**Step 4: Commit.**

---

### Task 1.11: StoreHostRequest + @store

**Files:**
- Create: `app/Http/Requests/LiveHost/StoreHostRequest.php`
- Modify: `HostController.php`
- Modify: `routes/web.php`

**Step 1: Failing test:**

```php
it('creates a new live host', function () {
    actingAs($this->pic)
        ->post('/livehost/hosts', [
            'name' => 'Test Host',
            'email' => 'test@example.com',
            'phone' => '60123456789',
            'status' => 'active',
        ])
        ->assertRedirect('/livehost/hosts')
        ->assertSessionHas('success');

    expect(User::where('email', 'test@example.com')->first())
        ->not->toBeNull()
        ->role->toBe('live_host');
});

it('validates required fields on host create', function () {
    actingAs($this->pic)
        ->post('/livehost/hosts', [])
        ->assertSessionHasErrors(['name', 'email']);
});
```

**Step 2: Generate + implement Form Request:**

```bash
php artisan make:request LiveHost/StoreHostRequest --no-interaction
```

Rules mirror the fields captured in Task 0.1. At minimum:
```php
return [
    'name' => ['required', 'string', 'max:255'],
    'email' => ['required', 'email', 'unique:users,email'],
    'phone' => ['nullable', 'string', 'max:32'],
    'status' => ['required', 'in:active,inactive'],
];
```

**Step 3: Implement `@store`:**
```php
public function store(StoreHostRequest $request): RedirectResponse
{
    $host = User::create([
        ...$request->validated(),
        'role' => 'live_host',
        'password' => bcrypt(str()->random(32)), // temporary; send reset link in a later task
    ]);

    return redirect()->route('livehost.hosts.index')
        ->with('success', "Live host {$host->name} created.");
}
```

**Step 4: Add route** `POST /livehost/hosts → HostController@store`.

**Step 5: Run test — pass. Commit.**

---

### Task 1.12: hosts/Create.jsx

**File:** `resources/js/livehost/pages/hosts/Create.jsx`

Use Inertia's `useForm`. Fields per Task 0.1. Submit via `form.post(route('livehost.hosts.store'))`. Show validation errors under each field.

---

### Task 1.13: HostController@show + hosts/Show.jsx

Controller props: `{ host, platformAccounts, recentSessions, stats: {totalSessions, totalWatchHours, avgViewers} }`.

Test:
```php
it('shows live host details with stats', function () {
    $host = User::factory()->create(['role' => 'live_host']);

    actingAs($this->pic)
        ->get("/livehost/hosts/{$host->id}")
        ->assertInertia(fn (Assert $p) => $p
            ->component('hosts/Show')
            ->where('host.id', $host->id)
            ->has('platformAccounts')
            ->has('recentSessions')
            ->has('stats'));
});
```

Page: mirror the Dashboard's "top hosts" row but for a single host — big KPIs + recent sessions timeline + platform accounts list.

---

### Task 1.14: UpdateHostRequest + @edit + @update + hosts/Edit.jsx

Symmetric to 1.11 + 1.12. `UpdateHostRequest` uses `Rule::unique('users', 'email')->ignore($this->user)`.

---

### Task 1.15: HostController@destroy + policy

**CRITICAL — confirm semantics with Task 0.1 findings.** Options:
- Soft-delete the user (requires `SoftDeletes` trait on User)
- Strip the `live_host` role (set `role = null` or previous role)
- Hard delete (cascades to `LiveSession`?)

**Test:**
```php
it('deletes a live host per the documented semantic', function () {
    $host = User::factory()->create(['role' => 'live_host']);

    actingAs($this->pic)
        ->delete("/livehost/hosts/{$host->id}")
        ->assertRedirect('/livehost/hosts')
        ->assertSessionHas('success');

    // Assert per semantic — example for role-strip:
    expect($host->fresh()->role)->not->toBe('live_host');
});

it('forbids live_host from deleting other hosts', function () {
    $host = User::factory()->create(['role' => 'live_host']);
    $otherHost = User::factory()->create(['role' => 'live_host']);

    $this->actingAs($host)
        ->delete("/livehost/hosts/{$otherHost->id}")
        ->assertForbidden();
});
```

**Implement `LiveHostPolicy`:**

```bash
php artisan make:policy LiveHostPolicy --no-interaction
```

Authorize `delete` for `admin_livehost` + `admin` only.

Register in `AuthServiceProvider`:
```php
protected $policies = [
    // ...existing
];
```

Then use `$this->authorize('delete', $host)` in the controller.

---

### Task 1.16: Phase 1 integration — full suite + manual QA

**Step 1:** Run:
```bash
php artisan test --compact
```
Expected: all green.

**Step 2:** Run:
```bash
vendor/bin/pint --dirty
npm run build
```

**Step 3:** Manual QA checklist in a browser logged in as `admin_livehost`:
- `/livehost` — all 4 KPIs render with real data, "On Air" panel polls every 10s
- `/livehost/hosts` — list paginates, search debounces, filter works
- Create host — form validates, redirects with toast
- View host — stats + recent sessions render
- Edit host — saves, toast
- Delete host — confirm dialog, deletes per semantic
- Sidebar active state works on every page
- Visit each page as `admin` — should also work
- Visit as `live_host` — should 403

**Step 4:** Commit any final fixes, tag:
```bash
git tag -a livehost-phase-1 -m "Phase 1 complete: Dashboard + Hosts CRUD"
```

---

## Phase 2 — Schedules + Time Slots + Session Slots + Live Sessions

**Outcome:** Remaining 4 resource pages, following Phase 1's controller + page + Form Request + tests pattern.

Each resource follows the same **13-task mini-loop** as Hosts:
1. `Store<Resource>Request` + `Update<Resource>Request`
2. `<Resource>Controller` with `index`, `create`, `store`, `show`, `edit`, `update`, `destroy`
3. `<Policy>` if actions differ by role
4. Index page (table with search/filter)
5. Create page (form)
6. Show page (detail)
7. Edit page (form)
8. Route registration (`Route::resource(...)`)
9. Tests: access, Inertia shape, form validation, policy
10. Browser smoke: index renders, create+edit round-trip

### Task 2.1 — Schedules resource

Model: `LiveSchedule` + `LiveScheduleAssignment` pivot.

Complexity: show page must render assignments (host allocations per schedule). Create form needs to assign hosts to the schedule.

### Task 2.2 — Time Slots resource

Model: `LiveTimeSlot`.

Relatively simple — template time windows.

### Task 2.3 — Session Slots resource

Probably bridges schedules and time slots — confirm from Task 0.1 docs which model/table backs "session slots". If it's a view over existing tables, skip separate controller and render under Schedules.

### Task 2.4 — Live Sessions (read-only)

Model: `LiveSession` — only `index` + `show` (sessions are recorded, not manually created here).

Show page includes: session metadata, `LiveAnalytics`, `LiveSessionAttachment` list, playback link if applicable.

### Task 2.5 — Global search (optional, defer if tight)

Wire the sidebar's ⌘K search to a combined index over hosts + schedules + sessions.

### Task 2.6 — Phase 2 integration

Same final QA + tag `livehost-phase-2`.

---

## Phase 3 — Parity Verification + Retirement

### Task 3.1: Parity checklist

**File:** `docs/plans/livehost-parity-checklist.md`

Walk through every action available in `/admin/live-hosts/*` Volt pages and verify the Inertia pages cover each one. Tick each off against the Task 0.1 semantics doc. Any gaps — open a follow-up task; do not retire yet.

### Task 3.2: Add redirects from old Volt routes to new Inertia routes

**File:** `routes/web.php`

```php
Route::permanentRedirect('/admin/live-hosts', '/livehost/hosts');
Route::permanentRedirect('/admin/live-hosts/create', '/livehost/hosts/create');
Route::get('/admin/live-hosts/{host}', fn ($host) => redirect("/livehost/hosts/{$host}"));
Route::get('/admin/live-hosts/{host}/edit', fn ($host) => redirect("/livehost/hosts/{$host}/edit"));
```

Test each redirect returns 301/302 to the new URL.

### Task 3.3: Remove old Volt admin views

Delete after parity is confirmed and redirects pass:
- `resources/views/livewire/admin/live-hosts-list.blade.php`
- `resources/views/livewire/admin/live-hosts-create.blade.php`
- `resources/views/livewire/admin/live-hosts-show.blade.php`
- `resources/views/livewire/admin/live-hosts-edit.blade.php`

Also remove the `Volt::route(...)` registrations for these in `routes/web.php`.

Run the full test suite after deletion — no tests should reference these views.

### Task 3.4: Update `CLAUDE.md` with the new paradigm

Add a section under "Architecture" documenting the three UI paradigms:
- **Livewire Volt** — admin, teacher, student, host-side live-host
- **React SPA (HR)** — `/hr/*`
- **Inertia.js (Live Host Desk)** — `/livehost/*`

Clarify which paradigm new features should use based on their area.

### Task 3.5: Final tag

```bash
git tag -a livehost-phase-3 -m "Phase 3 complete: parity verified, old Volt retired"
```

---

## Estimated effort

- **Phase 0:** 0.5 day
- **Phase 1:** 2–3 days (Dashboard is large; CRUD is standard)
- **Phase 2:** 2–3 days (4 resource groups, pattern-based)
- **Phase 3:** 0.5 day

Total: **5–7 working days** for a focused implementer.

---

## Post-launch ideas (out of scope for v1)

- Host-side `/live-host/*` migration to Inertia (v2)
- WebSocket-based live stats instead of 10s polling
- Mobile-optimized layout for on-call PIC monitoring from phone
- Multi-PIC role (supporting designated PICs per platform / region)
