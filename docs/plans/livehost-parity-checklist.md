# Live Host PIC Dashboard — Parity Checklist

Date: 2026-04-19
Branch: feature/livehost-pic-dashboard

This document verifies that the new Inertia PIC dashboard at `/livehost/hosts/*` fully replaces the legacy Volt admin views at `/admin/live-hosts/*` before those Volt views are retired.

Reference for Volt semantics: `docs/plans/livehost-volt-semantics.md` (Task 0.1 output).

## Scope of this retirement

**Retiring ONLY** the 4 Volt views under `/admin/live-hosts/*`:

- `resources/views/livewire/admin/live-hosts-list.blade.php`
- `resources/views/livewire/admin/live-hosts-create.blade.php`
- `resources/views/livewire/admin/live-hosts-show.blade.php`
- `resources/views/livewire/admin/live-hosts-edit.blade.php`

**Intentionally NOT retiring** (parity coverage incomplete, keep both surfaces alongside):

- `admin/live-schedules*` — Volt has the legacy schedule management; the new `/livehost/schedules` is the replacement but some flows were not fully verified.
- `admin/live-time-slots` — has Seed Default Slots, inline toggle, duplicate-for-platform, overlap check we did not port.
- `admin/live-schedule-calendar` — has spreadsheet grid, CSV import/export, conflict detection, notifications we did not port.
- `admin/live-sessions*` — remains the canonical admin sessions UI. Our `/livehost/sessions` is the PIC-scoped read-only counterpart.

This retirement is **conservative**: only retire what we can prove full parity for.

---

## 1. `live-hosts-list.blade.php` → `/livehost/hosts` (HostController@index)

Volt source: `resources/views/livewire/admin/live-hosts-list.blade.php`
Inertia source: `app/Http/Controllers/LiveHost/HostController.php::index`, `resources/js/livehost/pages/hosts/Index.jsx`

### Data & behavior

- [x] **Paginated list (15 per page)** — `HostController@index` line 42 uses `paginate(15)`. Verified by `HostControllerTest::it('lists live hosts with pagination')`.
- [x] **Filter to `role = 'live_host'` only** — line 25. Test: `it('excludes non-live_host users from the list')`.
- [x] **Excludes soft-deleted hosts** — `User` model uses `SoftDeletes`, default query scope handles it. Test: `it('excludes soft-deleted hosts from the list')`.
- [x] **Search by name / email / phone (LIKE %q%)** — lines 26-32. Tests: `it('filters hosts by name search')`, `it('filters hosts by email search')`.
- [x] **Filter by status** — line 33. Test: `it('filters hosts by status')`.
- [x] **`platformAccounts` count** — `withCount(['platformAccounts'])` line 34, rendered in `Accounts` column (Index.jsx line 161).
- [x] **Sessions count** — via `selectSub` against `live_sessions.live_host_id` (HostController lines 35-40). NOTE: this is a semantic improvement over Volt, which used `liveSessions()` (HasManyThrough PlatformAccount). Inertia counts sessions the host is directly assigned to via `live_host_id`, matching the PIC model's intent.
- [x] **Search debounce 300ms** — Index.jsx line 44 (`setTimeout(..., 300)`).
- [x] **Clear Filters** — Index.jsx `clearFilters()` handler, shown when search/status set (lines 114-122).
- [x] **Row actions: View, Edit, Delete (with confirm)** — Index.jsx lines 170-199. Delete uses `window.confirm` (Volt used `wire:confirm` — functional parity).
- [x] **Empty state** — "No hosts found" with Clear filters hint when filters applied (lines 111-123).

### Known gaps

- [⚠️] **4 stats tiles (Total, Active, Platform Accounts, Sessions Today)** — NOT ported to `/livehost/hosts`. These stats instead live on the `/livehost` dashboard (DashboardController) as "Live now" / "Scheduled today" tiles. Arguably better UX separation (dashboard for stats, index for data), but technically a UI gap relative to Volt. **Follow-up (non-blocking):** if users ask for at-a-glance stats on the Hosts list, add a tile row to `hosts/Index.jsx`.

---

## 2. `live-hosts-create.blade.php` → `/livehost/hosts/create` (HostController@create + store)

Volt source: `resources/views/livewire/admin/live-hosts-create.blade.php`
Inertia source: `HostController@create/store`, `resources/js/livehost/pages/hosts/Create.jsx`, `app/Http/Requests/LiveHost/StoreHostRequest.php`

### Data & behavior

- [x] **Fields: name, email, phone, status** — StoreHostRequest rules covers all four.
- [x] **Role hard-coded to `live_host`** — HostController@store line 134.
- [x] **Validation: name required string max:255** — StoreHostRequest.
- [x] **Validation: email required unique** — StoreHostRequest. Test: `it('rejects host create with duplicate email')`.
- [x] **Validation: status in active|inactive|suspended** — StoreHostRequest. Test: `it('rejects host create with invalid status')`.
- [x] **Success redirects + flash** — `redirect()->route('livehost.hosts.index')->with('success', …)`. Test: `it('creates a new live host')`.

### Intentional behavior changes from Volt

- [🔄] **Password flow changed** — Volt required admin to set an initial password (+ confirmation). Inertia generates a random 40-char password (HostController line 135). This matches the PIC-first model: the admin creates the account and the host receives credentials via a separate flow (password reset / invite email) rather than requiring the admin to generate & communicate passwords manually. **This is a deliberate product change, not a gap.**
- [🔄] **Phone now required + uniquely validated** — Volt allowed nullable phone with no uniqueness rule at the validator layer (relied on the DB unique index to throw). Inertia requires phone and adds `unique:users,phone` for a clean validation message. This matches one of the Volt semantics doc's flagged follow-ups (point 2 in "Follow-up / Ambiguities"). Test: `it('rejects host create with duplicate phone')`.
- [🔄] **No `email_verified_at` set on create** — Same as Volt (null on create).
- [🔄] **No welcome email sent** — Same as Volt. Semantics doc deferred this to future.

### Known gaps

- [⚠️] **Soft-deleted email/phone reuse** — When a host is soft-deleted, their `email` and `phone` still occupy the unique index. Creating a new host with the same email/phone fails with a validator-level error (now clean) and the admin can't "restore" the soft-deleted host. This is the same limitation the Volt form had. **Follow-up ticket:** add `restore`/`reclaim` action when validation detects a soft-deleted match (documented in livehost-volt-semantics.md line 228).

---

## 3. `live-hosts-edit.blade.php` → `/livehost/hosts/{host}/edit` (HostController@edit + update)

Volt source: `resources/views/livewire/admin/live-hosts-edit.blade.php`
Inertia source: `HostController@edit/update`, `resources/js/livehost/pages/hosts/Edit.jsx`, `app/Http/Requests/LiveHost/UpdateHostRequest.php`

### Data & behavior

- [x] **Pre-fills name, email, phone, status from host** — HostController@edit lines 148-154. Test: `it('renders the edit form with pre-filled host data')`.
- [x] **Validation: name, email (unique-ignore-self), phone (unique-ignore-self), status** — UpdateHostRequest with `Rule::unique('users', 'email|phone')->ignore($hostId)`. Test: `it('allows updating a host to keep its own email and phone')`, `it('prevents updating a host to another users email')`.
- [x] **Role NOT updated on save** — `$host->update($request->validated())` consumes only validated fields. `role` is not in rules, so mass-assign skips it. Test: `it('ignores role field in update mass-assign')`.
- [x] **404 when editing a non-live-host** — `abort_unless($host->role === 'live_host', 404)`. Test: `it('returns 404 when editing a non-live-host user')`.
- [x] **Success redirect to show page + flash** — `redirect()->route('livehost.hosts.show', $host)->with('success', …)`. Test: `it('updates a live host')`.

### Intentional behavior changes from Volt

- [🔄] **Password edit removed from Edit form** — Volt allowed an admin to reset a host's password inline. Inertia omits this to align with the new "password reset via separate flow" model. **This is a deliberate product change.**

---

## 4. `live-hosts-show.blade.php` → `/livehost/hosts/{host}` (HostController@show)

Volt source: `resources/views/livewire/admin/live-hosts-show.blade.php`
Inertia source: `HostController@show`, `resources/js/livehost/pages/hosts/Show.jsx`

### Data & behavior

- [x] **Eager-loads platform accounts with platform** — `$host->load(['platformAccounts.platform'])` line 74.
- [x] **Returns 404 for non-live-host user** — line 72. Test: `it('returns 404 when showing a non-live-host user')`.
- [x] **Header: host name, back / edit / delete actions** — Show.jsx lines 60-83. Delete shows a confirmation modal rather than `wire:confirm`.
- [x] **Delete guard (connected platform accounts)** — HostController@destroy lines 177-182. Test: `it('refuses to delete a host with connected platform accounts')`.
- [x] **Soft-delete behavior** — `$host->delete()` (SoftDeletes trait). Test: `it('soft-deletes a live host without connected platform accounts')`.
- [x] **Stats: totalSessions, completedSessions, platformAccounts** — HostController lines 93-123.
- [x] **Recent sessions list (latest 10)** — HostController lines 76-91.
- [x] **Platform accounts list** — HostController lines 112-117.

### Intentional behavior changes from Volt

- [🔄] **Sessions now scoped to `live_host_id` (direct assignment)** — Volt computed sessions through `platformAccounts.liveSessions`. Inertia queries `LiveSession::where('live_host_id', $host->id)` directly, matching the PIC assignment model introduced for the dashboard. This is a more correct, PIC-first data model.

### Known gaps (intentional scope reduction for v1)

- [⚠️] **4-tab structure (Overview, Platform Accounts, Schedules, Sessions)** — Volt had four tabs. Inertia Show page is a single scroll with Recent Sessions + Platform Accounts panels. **Follow-up ticket:** consider adding a per-day Schedules view to Show if PIC users ask for it.
- [⚠️] **Per-day Schedules grid (Sun–Sat, 7 columns)** — NOT ported to Show page. The per-day grid is available at `/livehost/schedules` instead, scoped to the whole deck rather than a single host. **Follow-up ticket:** add a host-filtered view of that grid if needed.
- [⚠️] **Per-weekday Sessions grid for current week** — NOT ported. Recent Sessions list (latest 10) replaces it. **Follow-up ticket:** add a week-view mode if PIC users ask for it.
- [⚠️] **6 quick-stats tiles (Platform Accounts, Active Accounts, Schedules, Total Sessions, Upcoming, Live Now)** — Inertia has 3 (totalSessions, completedSessions, platformAccounts). The missing ones (Active Accounts, Schedules count, Upcoming, Live Now) can be added cheaply — none are load-bearing for v1. **Follow-up ticket.**

---

## 5. Cross-cutting: tests & routes

- [x] **Feature tests** — `tests/Feature/LiveHost/HostControllerTest.php` covers index filter/pagination/role-exclusion/soft-delete-exclusion, create (success + 3 validation-failure paths), show (success + 404), edit (pre-fill + 404), update (success + self-keep email + duplicate reject + role-mass-assign protection), destroy (success + platform-account guard + 404 + forbidden).
- [x] **Browser smoke** — `tests/Browser/LiveHost/HostsIndexTest.php`, `HostsCreateTest.php`, `HostsDestroyTest.php`.
- [x] **Access control** — `tests/Feature/LiveHost/AccessTest.php` verifies admin_livehost + admin can access, live_host + student forbidden, guest redirected to login.
- [x] **Authorization via policy** — HostController@destroy checks `$request->user()->can('livehost.delete', $host)`. Test: `it('forbids non-PIC from deleting hosts')`.

---

## Summary: known follow-ups (non-blocking)

1. **Soft-deleted email/phone reuse** — add restore/reclaim flow when creating a host whose email/phone matches a soft-deleted row. (Was already a Volt limitation.)
2. **Per-host Schedules grid on Show page** — optional, only if PIC users miss it.
3. **Per-host Sessions week view on Show page** — optional.
4. **Hosts Index stats tiles** — optional; stats currently live on the dashboard.
5. **More quick-stats on Show page** — optional (Active Accounts, Schedules, Upcoming, Live Now).

None of these block the retirement of `/admin/live-hosts/*` because the core CRUD surface has full behavioral parity (or deliberate product-driven improvements).

## Decision

The 4 `/admin/live-hosts/*` Volt views are safe to retire behind permanent (301) redirects to their `/livehost/hosts/*` equivalents.
