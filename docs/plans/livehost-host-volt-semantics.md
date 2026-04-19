# Host-side Volt semantics (parity reference)

Captured for the host-side mobile migration (`feature/livehost-host-mobile`). The
Pocket Inertia app at `/live-host/*` must preserve these semantics as Batches 2-4
land. All pages currently render the custom `<x-live-host-nav />` blade component
at the bottom — the new PocketLayout replaces that with a React tab bar.

All host pages live in `resources/views/livewire/live-host/` and are gated by
`middleware(['auth', 'role:live_host'])` in `routes/web.php` (the old Volt group
at lines 152-159, removed by this branch).

## dashboard.blade.php — `/live-host/dashboard`

Read-only overview with six stat cards, today's schedule, platform accounts,
upcoming sessions, and recent performance.

- **Public properties:** none (stateless; all properties are computed via
  `getFooProperty` magic accessors).
- **Actions:** none.
- **Validation:** none.
- **Data / relationships (all scoped to `auth()->user()`):**
  - `totalSessions` / `upcomingSessions` / `liveNow` / `completedThisWeek` —
    counts from `User::liveSessions()` with scopes `upcoming`, `live`, `ended`
    (plus `actual_end_at >= startOfWeek()` for this-week).
  - `activePlatformAccounts` — `User::platformAccounts()->active()->count()`.
  - `totalViewers` — joins `live_sessions` to `live_analytics` and sums
    `viewers_peak` across ended sessions.
  - `todaySessions` — `liveSessions()` with `whereDate('scheduled_start_at',
    today())` plus `platformAccount.platform` + `analytics` eager loads.
  - `upcomingSessionsList` — upcoming-scope, limit 5, same eager loads.
  - `platformAccounts` — `User::platformAccounts()` with `platform`,
    `liveSchedules`, `liveSessions` loaded and `withCount([...])`.
  - `recentAnalytics` — ended sessions that have analytics, latest 5.
- **Guard logic:** role middleware only; everything is auth()->user() scoped so
  a host cannot see another host's data.
- **Mobile parity notes for Batch 2 (Today tab):**
  - Recent Performance and Upcoming Sessions tables are desktop-ish — they can
    collapse into cards on Pocket.
  - Status-colour source of truth: `LiveSession::status_color` accessor → blue
    (scheduled) / green (live) / gray (ended) / red (cancelled).
  - Time formatting used: `format('h:i A')`, `format('M d, Y')`.

## schedule.blade.php — `/live-host/schedule`

Weekly self-assignment grid. Two tabs: "My Schedule" (list of the host's own
slots grouped by day) and "Claim Slots" (grid of all platforms × days × time
slots where the host can self-assign empty slots).

- **Public properties:**
  - `weekOffset` (int, default 0) — which week's view is being rendered.
  - `activeTab` (string, default `'my-schedule'`) — `'my-schedule'` or
    `'claim-slots'`.
  - Assignment modal state: `showAssignmentModal` (bool),
    `selectedScheduleId` / `selectedPlatformId` / `selectedDayOfWeek` /
    `selectedTimeSlotId` (nullable ints).
- **Actions:**
  - `previousWeek()` / `nextWeek()` / `jumpToToday()` — mutate `weekOffset`.
  - `setActiveTab(string $tab)`.
  - `openAssignmentModal($platformId, $dayOfWeek, $timeSlotId, $scheduleId = null)`.
  - `assignToSelf()` — either updates an existing `LiveSchedule` to set
    `live_host_id = auth()->id()` (only if the slot is unassigned) or creates
    a new `LiveSchedule` row with `is_recurring = true`, `is_active = true`,
    `live_host_id = auth()->id()`. Flashes success/error to session.
  - `unassignSelf(int $scheduleId)` — nulls `live_host_id` iff the schedule
    currently belongs to `auth()->id()` (enforced in PHP, not a policy).
  - `closeModal()` — resets modal state.
- **Validation:** none explicit — controller asserts ownership in PHP before
  mutating.
- **Data / relationships:**
  - `timeSlots` — `LiveTimeSlot::where('is_active', true)->orderBy('start_time')`.
  - `platformAccounts` — all active platform accounts (not only this host's;
    the grid is global so a host can see which slots are open across all
    platforms). Eager loads `platform`.
  - `schedulesMap` — `LiveSchedule` rows for those platforms, `is_active = true`,
    keyed by `platform_account_id-day_of_week-start_time` for O(1) cell lookup.
  - `mySchedules` — `LiveSchedule::where('live_host_id', auth()->id())`,
    active, with `platformAccount.platform`, grouped by `day_of_week`.
  - `schedulesByDay` — same as `mySchedules` but ensures 0-6 are all present.
  - `totalSchedules` / `activeSchedules` / `recurringSchedules` /
    `sessionsThisWeek` / `availableSlotsCount` — stats cards.
- **Guard logic:** PHP checks `$schedule->live_host_id === auth()->id()` before
  unassign; `assignToSelf` refuses to overwrite an already-assigned slot.
- **Display quirks:** Days of week ordered `Sat, Sun, Mon, Tue, Wed, Thu, Fri`
  (Malaysian business week). Day labels in Malay (`SABTU`, `AHAD`, etc.).
  Platform colour helper returns gray/blue/orange/purple.

## sessions-index.blade.php — `/live-host/sessions`

Paginated session list with filters. Stats cards reuse the dashboard metrics.

- **Public properties:** `search`, `statusFilter`, `platformFilter`,
  `dateFilter` (all strings, all default `''`); `perPage = 15`.
- **Actions:**
  - `updatingSearch` / `updatingStatusFilter` / `updatingPlatformFilter` /
    `updatingDateFilter` — reset pagination on change.
  - `clearFilters()` — reset all filters + page.
- **Validation:** none.
- **Data / relationships:**
  - `sessions` — paginated (`paginate(15)`) over `auth()->user()->liveSessions()`
    with `platformAccount.platform` + `analytics` eager loads. Filters applied:
    search (title or description `LIKE %q%`), status (exact match), platform
    (via `whereHas('platformAccount', fn ($q) => $q->where('platform_id', …))`),
    dateFilter (today / this_week / next_week / this_month / past).
    Order `scheduled_start_at desc`.
  - `totalSessions` / `upcomingSessions` / `liveNow` / `completedSessions` —
    stat counts.
  - `platforms` — distinct platforms from the host's platform accounts, used to
    populate the platform filter dropdown.
- **Guard logic:** Only auth()->user() sessions — safe by construction.
- **Uses `WithPagination`.**

## sessions-show.blade.php — `/live-host/sessions/{session}`

Detail view with session info, timeline, analytics, and attachments.

- **Public properties:** `LiveSession $session` (route-bound, `#[Locked]`).
- **Actions:** none — read-only.
- **Validation:** none.
- **Data / relationships:** loads `platformAccount.platform`, `liveSchedule`,
  `analytics`, `attachments.uploader` in `mount()`.
- **Guard logic (important):** In `mount()`, aborts 403 unless
  `$session->platformAccount->user_id === auth()->id()`. Note: this uses the
  legacy single-owner column `platform_accounts.user_id`, NOT
  `live_sessions.live_host_id`. Batches 2-4 should verify whether this guard
  is still correct after the `live_host_platform_account` pivot migration
  (2025_11_17), since a platform account can now have multiple hosts. Suggest
  updating the guard to `$session->live_host_id === auth()->id()` for the
  Inertia port (session-upload.blade.php already uses that column).
- **Helper:** `getFormattedFileSize($bytes)` for attachment sizes.
- **Timeline states:** scheduled → live (when `actual_start_at` set or status
  is `live`/`ended`) → ended (when `actual_end_at` set or status is `ended`).
  Separate branch for `cancelled`.
- **Analytics cards (6):** peak viewers, avg viewers, total likes, total
  comments, total shares, gifts value (RM). Plus engagement rate %, total
  engagement, duration minutes.

## session-upload.blade.php — `/live-host/session-slots`

Pending-vs-uploaded session tabs with an upload modal. This is the parity
reference for Batch 4 (Upload screen).

- **Public properties:**
  - `activeTab` (default `'pending'`, also `'uploaded'`).
  - Upload modal: `showUploadModal` (bool), `editingSessionId` (nullable int),
    `actualStartTime` (string `H:i`), `actualEndTime` (string `H:i`),
    `sessionImage` (Livewire file upload), `remarks` (string, ≤1000 chars).
  - `filterDate` (string) — scoped to the "uploaded" tab.
- **Actions:**
  - `setActiveTab(string $tab)`.
  - `openUploadModal(int $sessionId)` — loads the session, refuses if
    `live_host_id !== auth()->id()` or already uploaded. Pre-fills start/end
    times from `actual_start_at` (or falls back to `scheduled_start_at` for
    start).
  - `uploadSession()` — validates + stores file on `public` disk under
    `live-sessions/`, then calls `LiveSession::uploadDetails([...])`. That
    method sets `actual_start_at`, `actual_end_at`,
    `duration_minutes = diffInMinutes`, `image_path`, `remarks`,
    `uploaded_at = now()`, `uploaded_by = auth()->id()`.
  - `closeModal()` — resets modal state + validation.
  - `viewSession(int $sessionId)` — `redirect(..., navigate: true)` to the
    sessions.show route.
- **Validation rules (Livewire `$this->validate()`):**
  - `actualStartTime` — required.
  - `actualEndTime` — `required|after:actualStartTime`.
  - `sessionImage` — `required|image|max:5120` (5 MB).
  - `remarks` — `nullable|string|max:1000`.
  - Custom messages for `actualEndTime.after`, `sessionImage.required`,
    `sessionImage.max`.
- **Data / relationships:**
  - `pendingSessions` — host's sessions where `status = 'ended'` AND
    `uploaded_at IS NULL`. Paginated (10 per page, cursor name `pendingPage`).
  - `uploadedSessions` — host's sessions with non-null `uploaded_at`,
    optionally filtered by date. Paginated (10, cursor name `uploadedPage`).
  - `stats` — pending / uploaded / thisWeek (uploads in
    startOfWeek..endOfWeek) / totalMinutes (sum of `duration_minutes` for
    uploaded).
- **Guard logic:** all queries `where('live_host_id', auth()->id())`. Modal
  actions re-check ownership before writes.
- **Storage:** uses `store('live-sessions', 'public')`, URL via
  `Storage::url($attachment->file_path)` on the show page.

## Allowance for live_host role

**Exists? NO.**

Grep across `app/`, `database/migrations/`, and the live-host Volt views
returned zero matches for allowance-adjacent terms tied to the live host
domain. Specifically:

- `live_sessions` columns (see
  `database/migrations/2025_11_03_143203_create_live_sessions_table.php` +
  `2025_11_04_200001_add_upload_fields_to_live_sessions_table.php`): no
  allowance/rate/amount/compensation field.
- `App\Models\LiveSession`: `$fillable` does not contain any pay-related key;
  no `calculateAllowance` / `earning_rate` / similar method.
- `App\Models\PlatformAccount` / `LiveSchedule` / `LiveScheduleAssignment` /
  `LiveAnalytics`: no allowance columns or accessors.
- `App\Models\User`: no host-specific allowance or rate.
- No `LiveHostAllowanceService` class anywhere.

The existing allowance infrastructure (`ClassSession::allowance_amount`,
`BackfillSessionAllowances` command, `CourseClassSettings::allowance_rate`,
`PayslipGenerationService`) is for the **teacher** role (class/course
attendance), not live hosts. It is not reused, extended, or referenced from
any live-host code.

Conclusion: **the host-side Pocket cannot display allowance totals in Batch 4
without new product decisions.** Flag for user before Batch 4.

### Suggestions if the user wants allowance in Batch 4

Minimum viable additions (for user to decide):

1. **Per-platform-account rate.** Add `per_session_allowance` (decimal 10,2)
   or `per_minute_allowance` to `platform_accounts`. A session's allowance is
   computed from `platformAccount.per_session_allowance` (flat) or
   `duration_minutes * platformAccount.per_minute_allowance`.
2. **Per-host override.** Add a nullable `live_session_rate` column on
   `users`, falling back to the platform rate.
3. **Snapshot at upload time.** Add `allowance_amount` (decimal 10,2) on
   `live_sessions` populated by `LiveSession::uploadDetails()` so historical
   totals never shift if rates change.
4. **Service class.** `App\Services\LiveHostAllowanceService` with
   `calculate(LiveSession $session): float` — mirrors the teacher pattern.

The mockup currently fakes an RM figure on the Today screen. Batch 4 should
either (a) wait for these decisions and render real numbers, (b) feature-flag
the allowance strip so it only shows once populated, or (c) ship without it.
Recommend (b) to unblock the UI migration.

## Decisions / open questions

1. **Guard on sessions-show.** Current code uses `platformAccount->user_id`
   but the schema moved to a pivot + `live_sessions.live_host_id` in November.
   Batch 3 should port this as `live_host_id === auth()->id()` instead.
2. **Allowance.** Blocker for Batch 4's "earnings" affordance. User must
   approve schema + rate source before the Today screen shows RM values.
3. **Self-assignment concurrency.** `assignToSelf()` has a TOCTOU window — a
   slot could be claimed between the modal opening and the write. The current
   code checks `!$schedule->live_host_id` before writing, which is best-effort.
   Batch 3 should keep the same semantics (accept rare race, flash error on
   loss).
4. **Legacy `platform_accounts.user_id`.** Still present for backward compat
   but the pivot table is now authoritative. Any new Pocket queries should
   use `User::platformAccounts()` (pivot-aware) rather than
   `PlatformAccount::where('user_id', auth()->id())`.
