# Live Host Volt Admin — Existing Semantics (Parity Reference)

Snapshot of behavior in the existing Volt admin pages at `/admin/live-hosts/*` so the Inertia
rewrite preserves it exactly. Source files:

- `resources/views/livewire/admin/live-hosts-list.blade.php`
- `resources/views/livewire/admin/live-hosts-create.blade.php`
- `resources/views/livewire/admin/live-hosts-show.blade.php`
- `resources/views/livewire/admin/live-hosts-edit.blade.php`
- `app/Models/User.php`
- `database/migrations/2025_09_01_064422_add_user_management_fields_to_users_table.php`

Routes (from `routes/web.php` lines 462-465, all under the admin prefix):

- `GET  /admin/live-hosts`           → `admin.live-hosts-list`   — name `live-hosts`
- `GET  /admin/live-hosts/create`    → `admin.live-hosts-create` — name `live-hosts.create`
- `GET  /admin/live-hosts/{host}`    → `admin.live-hosts-show`   — name `live-hosts.show`
- `GET  /admin/live-hosts/{host}/edit` → `admin.live-hosts-edit` — name `live-hosts.edit`

Route model binding resolves `{host}` as a `User` (no `scopeLiveHosts` — any user ID works; the
views just render its live-host context).

---

## Users table — status column (CRITICAL)

- [ ] Column name: **`status`** on `users` table.
- [ ] Defined as `enum('active','inactive','suspended')` in migration
      `2025_09_01_064422_add_user_management_fields_to_users_table.php` line 15.
      SQLite reports it as `varchar` (enum is stored as text on SQLite); MySQL enforces enum.
- [ ] Default: `'active'`.
- [ ] Allowed values: **`active`**, **`inactive`**, **`suspended`** — nothing else.
- [ ] Helpers on `User`: `isActive()`, `isInactive()`, `isSuspended()`,
      `activate()` → sets `active`, `deactivate()` → sets `inactive`, `suspend()` → sets `suspended`.
- [ ] `User::getStatusColor()` returns: `active`→green, `inactive`→gray, `suspended`→red, default gray.

## Users table — role column

- [ ] Column name: **`role`** (varchar).
- [ ] Live host marker: `role = 'live_host'` (NOT a pivot / role table; plain string column).
- [ ] No `scopeLiveHosts` exists on the model — every query filters inline with
      `where('role', 'live_host')`.
- [ ] `User::isLiveHost()` returns `$this->role === 'live_host'`.
- [ ] Other related roles present in model: `admin_livehost` (see `isAdminLivehost()`) — NOT used
      by the Volt admin views; only `live_host` is filtered/assigned.

## Soft deletes

- [ ] `User` uses `Illuminate\Database\Eloquent\SoftDeletes` (trait imported line 10, used line 19).
- [ ] Users table has `deleted_at` column (confirmed via schema dump).
- [ ] Therefore `$host->delete()` in both list and show views is a **soft delete**, NOT a hard delete.
- [ ] Role column is **NOT** stripped on delete; the row is simply soft-deleted with `role='live_host'` intact.

## Key relationships used by the Volt pages

- [ ] `platformAccounts(): HasMany` → `PlatformAccount` on `user_id`.
- [ ] `assignedPlatformAccounts(): BelongsToMany` → via `live_host_platform_account` pivot (NOT used
      by any of the 4 Volt pages — only the owned `platformAccounts()` is used).
- [ ] `liveSessions()` → `HasManyThrough(LiveSession, PlatformAccount, 'user_id', 'platform_account_id')`.
- [ ] `scheduleAssignments(): HasMany` → `LiveScheduleAssignment` on `live_host_id` (not used by these 4 views).
- [ ] PlatformAccount sub-relations touched: `platform`, `liveSchedules`, `liveSessions`.

---

## 1. `live-hosts-list.blade.php` (index)

### Public properties
- [ ] `$search = ''` (string)
- [ ] `$statusFilter = ''` (string)
- [ ] `$perPage = 15` (int)
- [ ] Uses `WithPagination` trait.

### Methods
- [ ] `updatingSearch()` → `resetPage()` (resets paginator on search change).
- [ ] `updatingStatusFilter()` → `resetPage()`.
- [ ] `clearFilters()` → clears `$search`, `$statusFilter`, calls `resetPage()`.
- [ ] `deleteHost($hostId)`:
  - Loads `User::findOrFail($hostId)` (no role check — uses whatever ID passed).
  - If `$host->platformAccounts()->count() > 0`, dispatches browser event `notify` with
    `type: error, message: 'Cannot delete live host with connected platform accounts.'` and
    **returns without deleting**.
  - Otherwise calls `$host->delete()` (soft delete — SoftDeletes trait) and dispatches `notify`
    with `type: success, message: 'Live host deleted successfully.'`.
- [ ] Computed `getLiveHostsProperty()` — query:
  - `User::query()`
  - `->where('role', 'live_host')`
  - `->when($search, name|email|phone LIKE %...%)`
  - `->when($statusFilter, ->where('status', ...))`
  - `->withCount(['platformAccounts', 'liveSessions'])`
  - `->latest()->paginate($perPage)`

### Filters shown
- [ ] Search input (debounce 300ms) — searches name, email, phone.
- [ ] Status select: All / Active / Inactive / Suspended (values: `''`, `active`, `inactive`, `suspended`).
- [ ] "Clear Filters" button shown only when `$search || $statusFilter`.

### Stats cards (4 tiles, inline queries)
- [ ] Total Live Hosts: `User::where('role','live_host')->count()`.
- [ ] Active Hosts: `User::where('role','live_host')->where('status','active')->count()`.
- [ ] Total Platform Accounts: `PlatformAccount::whereHas('user', fn($q) => $q->where('role','live_host'))->count()`.
- [ ] Live Sessions Today: `LiveSession::whereDate('scheduled_start_at', today())->count()`.

### Table columns
- [ ] Live Host — avatar (`$host->initials()`), name, `ID: #{id}`.
- [ ] Contact — email, phone (if present).
- [ ] Platform Accounts — count badge from `platform_accounts_count`, pluralized.
- [ ] Total Sessions — `live_sessions_count`.
- [ ] Status — `<flux:badge :color="$host->getStatusColor()">{{ ucfirst($host->status) }}</flux:badge>`.
- [ ] Actions — View, Edit, Delete (with `wire:confirm` dialog: "Are you sure you want to delete this live host?").

### Empty state
- [ ] Filtered: "No live hosts found matching your filters."
- [ ] Unfiltered: "No live hosts yet. Add a user with the \"Live Host\" role to get started."

---

## 2. `live-hosts-create.blade.php` (create form)

### Public properties
- [ ] `$name = ''`
- [ ] `$email = ''`
- [ ] `$phone = ''`
- [ ] `$password = ''`
- [ ] `$password_confirmation = ''`
- [ ] `$status = 'active'`

### Validation (`rules()` method)
- [ ] `name`: required, string, max:255
- [ ] `email`: required, string, email, max:255, **unique:users,email** (global unique, no soft-deleted-aware ignore — matches Laravel default which does NOT see soft-deleted rows because the unique check hits the DB unique index. **?** Possible edge case: if a live host was soft-deleted, their email row still exists in the `users` table; creating a new host with the same email will FAIL the DB unique index even though the validator query-based rule could theoretically succeed. Flag for follow-up if Inertia rewrite must handle soft-deleted email reuse.)
- [ ] `phone`: nullable, string, max:20 (**?** — `users.phone` has a unique index (`users_phone_unique`), but the validation rule does NOT declare `unique`. Duplicate phone will throw a DB integrity error rather than a field validation error. Flag: Inertia rewrite should probably add `unique:users,phone` for a cleaner error.)
- [ ] `password`: required, confirmed, `Password::defaults()`
- [ ] `status`: required, in:active,inactive,suspended

### `save()` behavior
- [ ] Validates → `User::create([...])` with:
  - `name`, `email`, `phone`, hashed `password`,
  - **`role` hard-coded to `'live_host'`**,
  - `status` from form.
- [ ] Flashes `success` session message: "Live host created successfully."
- [ ] Redirects to `route('admin.live-hosts.show', $user)` with `navigate: true` (wire:navigate).
- [ ] **No email / notification is sent.** No welcome/invite mail is triggered anywhere in the component.
- [ ] **No `email_verified_at` is set** — created users have null email_verified_at.

### `cancel()` behavior
- [ ] Redirects to `route('admin.live-hosts')` (the index), `navigate: true`.

### UI sections
- [ ] Basic Information card: Name, Email, Phone, Status.
- [ ] Security card: Password, Confirm Password.

---

## 3. `live-hosts-edit.blade.php` (edit form)

### Public properties
- [ ] `public User $host;`
- [ ] `$name`, `$email`, `$phone`, `$password`, `$password_confirmation`, `$status` — all strings.

### `mount(User $host)` seeds properties from `$host->{name,email,phone ?? '',status}`.
- [ ] Note: password fields start empty.

### Validation (`rules()` — differs from create)
- [ ] `name`: required, string, max:255
- [ ] `email`: required, string, email, max:255, **`Rule::unique('users','email')->ignore($this->host->id)`** (ignores self) — **does NOT include soft-deleted rows in consideration either direction**; same caveat as create.
- [ ] `phone`: nullable, string, max:20 (same phone-unique caveat as create; no explicit rule).
- [ ] `password`: **nullable**, confirmed, `Password::defaults()`.
- [ ] `status`: required, in:active,inactive,suspended.

### `save()` behavior
- [ ] Builds `$updateData` with `name, email, phone, status` (always).
- [ ] If `$validated['password']` is non-empty, adds `password => Hash::make(...)`.
- [ ] **`role` is NOT updated** — it remains whatever it already was (should be `live_host`).
- [ ] Calls `$this->host->update($updateData)`.
- [ ] Flashes `success`: "Live host updated successfully."
- [ ] Redirects to `route('admin.live-hosts.show', $this->host)` with `navigate: true`.

### `cancel()` redirects to show page (not index — different from create).

---

## 4. `live-hosts-show.blade.php` (detail view)

### Public properties
- [ ] `public User $host;`
- [ ] `public string $activeTab = 'overview';`
- [ ] Uses `WithPagination` trait (no paginator actually rendered in the template, but trait is imported).

### `mount(User $host)` eager-loads:
- [ ] `platformAccounts.platform`
- [ ] `platformAccounts.liveSchedules`
- [ ] `platformAccounts.liveSessions` (only latest 10 via `latest()->limit(10)`).

### Methods
- [ ] `setActiveTab(string $tab)` — just assigns.
- [ ] `deleteHost()`:
  - Identical guard to list view: blocks delete if `$host->platformAccounts()->count() > 0`,
    flashes session `error`: "Cannot delete live host with connected platform accounts."
  - Else: `$host->delete()` (soft delete), flash `success`, redirect to `route('admin.live-hosts')`.
- [ ] `getSchedulesByDayProperty()` — flattens all platform accounts' `liveSchedules`, groups by
      `day_of_week` 0–6 (Sun–Sat).
- [ ] `getSessionsByDayProperty()` — queries `$host->liveSessions()` for current week
      `whereBetween('scheduled_start_at', [now()->startOfWeek(), now()->endOfWeek()])`, eager
      `platformAccount.platform`, groups by weekday.

### `with()` returns `$stats` array
- [ ] `platform_accounts` — `platformAccounts()->count()`
- [ ] `active_accounts` — `platformAccounts()->where('is_active', true)->count()`
- [ ] `schedules` — sum of `live_schedules_count` across all platform accounts
- [ ] `total_sessions` — `liveSessions()->count()`
- [ ] `upcoming_sessions` — `liveSessions()->where('status','scheduled')->where('scheduled_start_at','>', now())->count()`
- [ ] `live_now` — `liveSessions()->where('status','live')->count()`

### UI structure
- [ ] Header: Back button → index; right side: Edit button, Delete button (danger, with wire:confirm).
- [ ] 6 quick-stats tiles (Platform Accounts, Active Accounts, Schedules, Total Sessions, Upcoming, Live Now).
- [ ] 4 tabs (buttons, NOT anchors): **Overview**, **Platform Accounts ({count})**, **Schedules ({count})**, **Sessions ({total_sessions})**.

### Tab contents
- [ ] **Overview** — two-column grid: Host Information (name, email, phone?, status badge, role label "Live Host", "Member Since" = created_at formatted `F j, Y`); Recent Sessions card shows up to 5 from `$host->platformAccounts->flatMap->liveSessions->take(5)` with title, scheduled_start_at, status badge.
- [ ] **Platform Accounts** — for each `platformAccounts`: name, active/inactive badge, platform display_name, email (if present), schedules count, sessions count.
- [ ] **Schedules** — 7 day columns (Sun–Sat), each shows that day's `liveSchedules` across all platform accounts; each card shows start time, duration in minutes, recurring icon if `is_recurring`, active/inactive dot, platform account name, platform display_name. Today column highlighted.
- [ ] **Sessions** — 7 day columns for **current week only**; each card shows scheduled_start_at time, duration (if present), status dot, title (fallback "Live Session"), platform account name, platform display_name. Status color palette: scheduled=blue, live=green, ended=gray, cancelled=red.

---

## Follow-up / Ambiguities (marked with ?)

- [ ] **?** Soft-deleted email/phone reuse: if a live host is soft-deleted, the `users.email` row still exists and the `users_phone_unique` index still enforces. Current Volt validation doesn't account for this. Inertia rewrite should decide whether to (a) surface the duplicate as a validation error, (b) allow "restoring" the soft-deleted user, or (c) filter validation to non-trashed only.
- [ ] **?** Phone uniqueness: DB has a unique index, but Volt form does NOT include `unique:users,phone` in validation. Inertia rewrite should probably add the rule for proper UX.
- [ ] **?** The `deleteHost($hostId)` method in the list view calls `User::findOrFail` without any role guard — a crafted request could delete a non-live-host user (e.g., a teacher or admin). Inertia rewrite should ensure `role = 'live_host'` is enforced in the delete controller.
- [ ] **?** No email verification / welcome email is sent on create. If Inertia rewrite wants to add notification, confirm with user.
- [ ] **?** Edit form does NOT re-assert `role = 'live_host'` on update. A live host admin-edit won't change role, but if the Inertia rewrite uses mass-assign from request, it must explicitly exclude `role` or hard-set it.
- [ ] **?** The show page imports `WithPagination` trait but renders no paginator — harmless, but the Inertia rewrite doesn't need to port it.
- [ ] **?** Status toggle as a first-class action (e.g., Activate/Suspend button on the show page) does NOT exist — status is only changed via the edit form. `User` model exposes `activate()/deactivate()/suspend()` helpers but no Volt view calls them. Inertia rewrite should decide whether to add explicit toggle actions or keep edit-only.

## Decisions for the Inertia rewrite (parity-first)

- **Delete behavior:** Soft-delete only, role stays `live_host` (exact current behavior). Email/phone-reuse footgun is a known limitation, fix later.
- **Phone validation:** Add `unique:users,phone` rule on store + update (ignoring self on update). Backs the existing DB unique index with a clean validation message.
- **Delete guard:** Enforce `role = 'live_host'` in the controller + policy so crafted requests can't delete non-host users. Return 404 for non-host targets.
- **Status toggle:** Not added in v1. Model helpers exist but no view uses them; defer to future.
- **Welcome email:** Not sent in v1 (current Volt doesn't send). Deferred.
- **Edit form role protection:** Explicitly exclude `role` from mass-assign in UpdateHostRequest::validated() consumers.
