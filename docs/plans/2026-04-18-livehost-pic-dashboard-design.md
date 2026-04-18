# Live Host PIC Dashboard — Design

**Date:** 2026-04-18
**Owner:** Ahmad Amin (admin_livehost PIC)
**Status:** Approved for implementation planning

## Summary

A dedicated dashboard for the Live Host PIC — the `admin_livehost` role — built as an Inertia.js + React module mounted at `/livehost`. It replaces the current Volt-based `/admin/live-hosts/*` pages with a focused, modern workspace where a PIC can manage live streaming hosts, schedules, time/session slots, and live sessions without the distractions of the full admin sidebar.

**v1 scope:** PIC-side only. The existing `/live-host/*` host-facing Volt pages stay untouched in v1 and may be migrated later.

## Goals

- **Focus** — a distinct surface for PICs managing 15+ live hosts and 17+ platform accounts, separated from the general admin UI.
- **Modern UX** — fresh bento-grid dashboard, real-time live session visibility, smooth navigation.
- **Consistent with project direction** — reuses the role system, Laravel auth, existing Eloquent models; introduces Inertia as a deliberate third UI paradigm.
- **Incremental rollout** — ship alongside the existing Volt admin, retire in Phase 3 once parity is reached.

## Non-goals (v1)

- Host-side (`live_host` role) migration — deferred to v2.
- Server-side rendering (SSR) — internal tool, no SEO need.
- Mobile-first design — responsive but optimized for desktop.
- A separate API layer — Inertia props replace it; narrow JSON endpoints added only where needed (e.g., live polling).

## Decisions locked during brainstorming

| Decision | Choice | Rationale |
|---|---|---|
| Target user | Both PIC and Live Host (dual-mode eventually); **v1 = PIC only** | User wants PIC-first; dual-mode is the long-term shape. |
| Stack | **Inertia.js + React** | User's explicit preference; avoids full API layer. |
| UI library | **shadcn/ui + Radix + Tailwind v4** | Modern, flexible, Flux UI is Livewire-only and can't be used. |
| Role access | **`admin_livehost` + `admin`** | PIC + super-admin fallback. |
| Migration strategy | **PIC-first, admin Volt retires after parity** | Existing host-side Volt pages untouched in v1. |
| Mount path | `/livehost` | Avoids collision with existing `/live-host/*` and `/admin/live-hosts/*`. |
| Aesthetic direction | **"Pulse" — modern bento dashboard**, Geist type, emerald accent, soft shadows, rounded cards | User rejected the editorial direction; approved the fresh/modern version. |

## Architecture

### Stack

- **Backend:** Laravel 12 + `inertiajs/inertia-laravel` (new dep)
- **Frontend:** React 19 (existing) + `@inertiajs/react` (new dep)
- **UI:** shadcn/ui (copy-paste, per-component) + Radix primitives + Tailwind v4 (existing) + lucide-react icons
- **Type system:** **Geist** (sans + mono) via Google Fonts
- **Data fetching:** Inertia props for page loads; TanStack Query (already in HR) reserved for live-polling endpoints only
- **Testing:** Pest v4 (existing) — feature tests + browser smoke tests

### Routes (`routes/web.php`)

```php
Route::middleware(['auth', 'role:admin_livehost,admin'])
    ->prefix('livehost')
    ->name('livehost.')
    ->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
        Route::get('/live-now', [DashboardController::class, 'liveNow'])->name('live-now');

        Route::get('hosts', [HostController::class, 'index'])->name('hosts.index');
        Route::get('hosts/create', [HostController::class, 'create'])->name('hosts.create');
        Route::post('hosts', [HostController::class, 'store'])->name('hosts.store');
        Route::get('hosts/{user}', [HostController::class, 'show'])->name('hosts.show');
        Route::get('hosts/{user}/edit', [HostController::class, 'edit'])->name('hosts.edit');
        Route::put('hosts/{user}', [HostController::class, 'update'])->name('hosts.update');
        Route::delete('hosts/{user}', [HostController::class, 'destroy'])->name('hosts.destroy');

        Route::resource('schedules', ScheduleController::class);
        Route::resource('time-slots', TimeSlotController::class);
        Route::resource('session-slots', SessionSlotController::class);

        Route::get('sessions', [SessionController::class, 'index'])->name('sessions.index');
        Route::get('sessions/{session}', [SessionController::class, 'show'])->name('sessions.show');
    });
```

Middleware `role:admin_livehost,admin` reuses the existing role middleware pattern used by `/live-host/*` and `/admin/*`.

### Backend layout

```
app/Http/Controllers/LiveHost/
├── DashboardController.php
├── HostController.php
├── ScheduleController.php
├── TimeSlotController.php
├── SessionSlotController.php
└── SessionController.php

app/Http/Requests/LiveHost/
├── StoreHostRequest.php
├── UpdateHostRequest.php
├── StoreScheduleRequest.php
├── UpdateScheduleRequest.php
├── StoreTimeSlotRequest.php
├── UpdateTimeSlotRequest.php
├── StoreSessionSlotRequest.php
└── UpdateSessionSlotRequest.php

app/Http/Middleware/HandleInertiaRequests.php   # published, shares auth.user + flash
```

Eloquent models, migrations, relationships — all reused unchanged: `User` (with `live_host` role), `LiveSession`, `LiveSchedule`, `LiveScheduleAssignment`, `LiveTimeSlot`, `PlatformAccount`, `LiveAnalytics`, `LiveSessionAttachment`.

### Frontend layout

```
resources/js/livehost/
├── app.jsx                              # createInertiaApp entry
├── layouts/
│   └── LiveHostLayout.jsx               # sidebar + top bar (per mockup)
├── pages/
│   ├── Dashboard.jsx
│   ├── hosts/
│   │   ├── Index.jsx
│   │   ├── Create.jsx
│   │   ├── Show.jsx
│   │   └── Edit.jsx
│   ├── schedules/{Index,Create,Show,Edit}.jsx
│   ├── time-slots/{Index,Create,Show,Edit}.jsx
│   ├── session-slots/{Index,Create,Show,Edit}.jsx
│   └── sessions/{Index,Show}.jsx
├── components/
│   ├── ui/                              # shadcn primitives (button, input, dialog, table, ...)
│   ├── PageHeader.jsx                   # page title + actions
│   ├── StatCard.jsx                     # bento KPI card
│   ├── LiveBadge.jsx                    # pulsing emerald dot + label
│   ├── LiveSessionRow.jsx               # the on-air row
│   ├── DataTable.jsx                    # reusable table wrapper
│   ├── StatusChip.jsx                   # live/prep/scheduled/done chips
│   └── Ticker.jsx                       # (optional, per page)
├── lib/
│   ├── utils.js                         # cn() for Tailwind
│   └── format.js                        # date/duration/number formatters
└── styles/
    └── livehost.css                     # Tailwind + shadcn CSS vars (Pulse palette)
```

Root Blade template: `resources/views/livehost/app.blade.php` — single `@inertia` directive, Vite entry `resources/js/livehost/app.jsx`.

Vite config additions (`vite.config.js` input array):
```js
'resources/js/livehost/app.jsx',
'resources/js/livehost/styles/livehost.css',
```

## Visual design — "Pulse"

Mockup: `docs/design-mockups/livehost-dashboard.html` (Dashboard page, approved).

### Palette

```css
--canvas:  #FAFAFA;   /* background */
--surface: #FFFFFF;   /* cards */
--border:  #EAEAEA;
--ink:     #0A0A0A;   /* primary text + primary buttons */
--muted:   #737373;

--emerald: #10B981;   /* LIVE / active accent */
--emerald-soft: #ECFDF5;
--amber:   #F59E0B;   /* prep / warning */
--rose:    #F43F5E;   /* errors / alerts */
--violet:  #8B5CF6;
--sky:     #0EA5E9;
```

Soft radial-gradient mesh (emerald + amber + violet) on the canvas for atmosphere, not flat.

### Type

- **Geist** for all UI (400/500/600/700 weights)
- **Geist Mono** for durations, IDs, tabular numbers
- Tight letter-spacing (`-0.01em` to `-0.04em` for headings)
- KPI numerals at 48–64px, weight 600, tabular lining numerals

### Layout primitives

- **Rounded 16px cards** with subtle shadow (`0 1px 2px rgba(0,0,0,0.04), 0 4px 16px rgba(0,0,0,0.03)`)
- **Bento grid** (12-column, mixed spans: 3/3/3/3 for KPIs, 7/5 for Live + Activity, 12 for tables)
- **Glass-effect top bar** (sticky, `backdrop-filter: blur(12px)`)
- **Rounded pill chips** for status (Live / Prep / Scheduled / Done)
- **Gradient avatar thumbnails** (emerald, rose, violet, sky, amber)
- **Pulsing emerald dot** animation for LIVE indicators (pure CSS)
- **Sparkline trends** in data tables (inline SVG)
- **Circular progress ring** for completion KPIs (e.g., Watch hours vs weekly target)

shadcn's CSS-var contract (`--background`, `--foreground`, `--primary`, etc.) is mapped onto the Pulse palette in `livehost.css`, so stock shadcn components pick up the tone automatically.

## Pages & Inertia props contract

| Route | Component | Props |
|---|---|---|
| `GET /livehost` | `pages/Dashboard.jsx` | `{ stats: {totalHosts, activeHosts, platformAccounts, sessionsToday, watchHoursToday}, liveNow: [], upcoming: [], recentActivity: [], topHosts: [] }` |
| `GET /livehost/live-now` | _(JSON)_ | `{ liveNow: [], stats: {...} }` — polled every 10s by the Dashboard "On Air" panel |
| `GET /livehost/hosts` | `pages/hosts/Index.jsx` | `{ hosts: paginated, filters: {status, search} }` |
| `GET /livehost/hosts/create` | `pages/hosts/Create.jsx` | `{}` |
| `POST /livehost/hosts` | redirect | — |
| `GET /livehost/hosts/{user}` | `pages/hosts/Show.jsx` | `{ host, platformAccounts, recentSessions, stats }` |
| `GET /livehost/hosts/{user}/edit` | `pages/hosts/Edit.jsx` | `{ host }` |
| `PUT /livehost/hosts/{user}` | redirect | — |
| `DELETE /livehost/hosts/{user}` | redirect | — |
| `GET /livehost/schedules` | `pages/schedules/Index.jsx` | `{ schedules: paginated, hosts, filters }` |
| `GET /livehost/schedules/{schedule}` | `pages/schedules/Show.jsx` | `{ schedule, assignments }` |
| `GET /livehost/time-slots` | `pages/time-slots/Index.jsx` | `{ timeSlots: paginated }` |
| `GET /livehost/session-slots` | `pages/session-slots/Index.jsx` | `{ sessionSlots: paginated, hosts }` |
| `GET /livehost/sessions` | `pages/sessions/Index.jsx` | `{ sessions: paginated, filters }` |
| `GET /livehost/sessions/{session}` | `pages/sessions/Show.jsx` | `{ session, attachments, analytics, host }` |

All index routes: eager-load related models, `paginate(15)->withQueryString()`, `withCount` for table counts.

## Forms

Inertia's `useForm` hook for all mutations — handles CSRF, validation errors (wired to `form.errors`), and loading state.

```jsx
const form = useForm({ name: '', email: '', phone: '', status: 'active' });
form.post(route('livehost.hosts.store'));
```

Server-side validation via Form Request classes (per CLAUDE.md guidelines) — errors bubble back through Inertia automatically.

## Authorization

Two layers:

1. **Route middleware** — `role:admin_livehost,admin` on the whole `/livehost` group. 403 for anyone else.
2. **Policies** — `LiveHostPolicy` for user-as-host actions; reuse existing model policies where present, add thin policies that delegate to role checks (`$user->isAdminLivehost() || $user->isAdmin()`) otherwise.

Flash messages (success/error) via `HandleInertiaRequests::share(['flash' => [...]])`, rendered by a `<Toaster />` (shadcn `sonner`) mounted in the layout.

## Real-time behavior

**Live polling** — only the Dashboard's "On Air" panel and top KPIs that change frequently:

```jsx
useQuery({
  queryKey: ['livehost-live-now'],
  queryFn: () => fetch('/livehost/live-now').then(r => r.json()),
  refetchInterval: 10_000,
});
```

Narrow JSON route, not a general API layer. No WebSockets in v1.

## Testing (Pest)

1. **Access / middleware** — `tests/Feature/LiveHost/AccessTest.php`
   - `admin_livehost` → 200; `admin` → 200; `live_host` → 403; guest → redirect
2. **Inertia response shape** — one test per controller action using `assertInertia()`:
   ```php
   ->assertInertia(fn (Assert $page) => $page
       ->component('hosts/Index')
       ->has('hosts.data', 15)
       ->has('filters'));
   ```
3. **Form validation** — per Form Request, datasets for rule coverage
4. **Policies** — role-based allow/deny per resource action
5. **Browser smoke** (Pest v4) — one per page, `assertNoJavascriptErrors()`, key text visible

No Vitest / JS unit tests — Pest browser tests cover the client side per project convention.

## Rollout phases

### Phase 0 — Scaffolding (1 PR)

- Install `inertiajs/inertia-laravel` + `@inertiajs/react`; publish middleware; configure `HandleInertiaRequests`.
- Scaffold shadcn (`components.json`, ui primitives: button, input, card, table, dialog, dropdown-menu, badge, sonner, form).
- Vite entry + root Blade template.
- Empty `DashboardController@index` returning a placeholder page.
- Admin sidebar link to `/livehost` for admin_livehost + admin roles.
- **Verify:** admin_livehost can reach `/livehost`; existing `/admin/live-hosts/*` still works.

### Phase 1 — Dashboard + Live Hosts CRUD

- Full Dashboard per the Pulse mockup: bento grid, 4 KPIs (Active hosts, Live now, Sessions today, Watch hours ring), On Air panel with 10s polling, Next-up agenda, Recent activity feed, Top hosts table with sparklines.
- `HostController` complete CRUD with Form Requests.
- `LiveHostPolicy`.
- Tests: access, Inertia shapes, form validation, browser smoke.

### Phase 2 — Schedules + Time Slots + Session Slots + Live Sessions

- Remaining 4 resource sections.
- Tests per strategy above.

### Phase 3 — Retirement

- Verify parity (checklist: same actions available, same data visible, same authorization).
- Add `301` redirects `/admin/live-hosts/*` → `/livehost/*`.
- Delete the 4 `livewire/admin/live-hosts-*.blade.php` Volt views.
- Leave `/live-host/*` host-side Volt pages in place (out of scope per PIC-first decision).

## Dependencies (new)

**Composer:**
- `inertiajs/inertia-laravel`

**npm:**
- `@inertiajs/react`
- `class-variance-authority`, `clsx`, `tailwind-merge` (shadcn runtime)
- Radix primitives per component added (as shadcn adds them)
- `lucide-react` — **already installed** for HR, reuse

No changes to React, Tailwind, Vite, Pest.

## Risks & flagged decisions for implementation

1. **Deletion semantics for live hosts** — must read existing `livewire/admin/live-hosts-edit.blade.php` + `live-hosts-list.blade.php` before Phase 1 to preserve exact behavior (soft-delete vs. role-strip vs. cascade). Flagged for the implementation plan.
2. **`admin` role discoverability** — admins have many modules; ensure `/livehost` surfaces in their admin sidebar, not hidden.
3. **Flash message wiring in Inertia** — easy to forget to share `flash.success` / `flash.error` from `HandleInertiaRequests::share()`.
4. **Third UI paradigm** — adds Inertia alongside Volt + HR's React SPA. Each new LiveHost contributor now has to learn which paradigm covers which area. Document the boundaries clearly in `CLAUDE.md` once Phase 0 lands.
5. **shadcn CSS-var mapping** — stock shadcn expects a specific contract; care needed to map the Pulse palette without breaking component defaults.

## Open questions (resolve during implementation planning)

- Which fields does the current "Create Live Host" Volt form have? (Read the Volt file before building the Inertia form.)
- Is there a `status` column on `users` for live hosts, or is status derived from something else?
- How is "Live now" currently computed? (Probably `LiveSession` with `status = 'ongoing'`.) Confirm during Phase 1.
- Are platform accounts assigned via a pivot (`live_host_platform_account`) or directly? (Map already shows pivot; confirm fields.)

## Approval

This design was developed and approved interactively with the project owner on 2026-04-18. The next step is a detailed implementation plan produced via the `writing-plans` skill, broken into Phase 0 / Phase 1 / Phase 2 / Phase 3 deliverables.
