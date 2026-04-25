# Live Host — Session Replacement Module

**Date:** 2026-04-25
**Status:** Design approved, ready for implementation planning
**Owner:** —

---

## 1. Problem

A live host on the Mudeer Bedaie platform may face an emergency (illness, family, personal) and be unable to deliver a scheduled live session. Today there is no in-app workflow for this — the host has to message the PIC outside the system, the PIC scrambles for a replacement, and there is no audit trail of how often hosts request replacements or who covered the slot.

We need:
- A self-serve "Request Replacement" flow for the host on the existing schedule screen.
- A PIC-side queue where pending requests can be reviewed and a replacement host assigned.
- Correct commission impact: the host who actually delivers the live earns the commission; the original requester does not.
- A durable record so admins can see how many replacements each host has requested.
- Malay-language email + in-app notifications for everyone involved.

## 2. Paradigm note

The screen at `/live-host/schedule` is **Inertia React** (the "Live Host Pocket" app at `resources/js/livehost-pocket/`), not Volt. CLAUDE.md is out of date on this. Host-side UI in this design extends the existing Inertia React pocket. Admin-side UI extends the existing PIC Inertia React app at `resources/js/livehost/`. No new paradigm is introduced.

## 3. Decisions

| # | Decision | Rationale |
|---|---|---|
| 1 | Host can request **one-date** OR **permanent** replacement (host chooses) | Covers both emergency (one-off) and giving-up-a-slot (permanent) flows from a single entry point. |
| 2 | **PIC manually assigns** the replacement — no open broadcast | Cleanest control, no race conditions, matches the existing PIC-led ops model. |
| 3 | **Original host loses commission, replacement earns normally** | Fair to whoever does the work. Naturally satisfied because `LiveSession.live_host_id` is the host who actually goes live. |
| 4 | Replacement is **auto-on-the-hook** when PIC assigns (no accept/reject step) | Faster, no double-handling. Replacement just sees the slot on their own schedule. |
| 5 | Original host **can withdraw** while status is `pending` | Emergencies resolve. After assignment, no withdrawal. |
| 6 | If no replacement assigned by slot start time, **original host is back on the hook** | Forces accountability. Request auto-expires; standard `missed` flow if they don't show. |
| 7 | Request form: **reason category dropdown + optional note** | Reportable categories with bilingual labels (Sakit / Sick, etc.), free text for context. |
| 8 | **Malay-only emails** for the new notifications | Matches the team. Existing English emails are not touched. |
| 9 | Admin UI is a **new dedicated page** at `/livehost/replacements` (Inertia) | Doesn't bloat the existing sessions index. Surfaces a count badge on the PIC dashboard so it's noticed without polling. |
| 10 | **New `session_replacement_requests` table** (not bolted onto existing models) | Full lifecycle tracking, reportable, doesn't disturb assignments/sessions. |

## 4. Architecture

### 4.1 New components

- **Model:** `App\Models\SessionReplacementRequest`
- **Migration:** `session_replacement_requests` (MySQL+SQLite compatible per CLAUDE.md guidance)
- **Controllers:**
  - `App\Http\Controllers\LiveHostPocket\ReplacementRequestController` — host: `store`, `withdraw`
  - `App\Http\Controllers\LiveHost\ReplacementRequestController` — PIC: `index`, `show`, `assign`, `reject`
- **Form requests:** `StoreReplacementRequest`, `AssignReplacementRequest`, `RejectReplacementRequest`
- **Notifications (queued, mail+database, Malay):**
  - `ReplacementRequestedNotification` → all `admin` + `admin_livehost` users
  - `ReplacementAssignedToYouNotification` → assigned replacement host
  - `ReplacementResolvedNotification` → original host (covers `assigned`, `rejected`, `expired`)
- **Console command:** `App\Console\Commands\ExpireReplacementRequestsCommand`, scheduled `everyFiveMinutes`
- **Inertia React pages:**
  - Host modal added to existing `resources/js/livehost-pocket/pages/Schedule.jsx` (no new page)
  - PIC: `resources/js/livehost/pages/Replacements/Index.jsx`, `Show.jsx`
- **Routes:**
  - `POST   /live-host/replacement-requests` (host)
  - `DELETE /live-host/replacement-requests/{id}` (host withdraw)
  - `GET    /livehost/replacements` (PIC index)
  - `GET    /livehost/replacements/{id}` (PIC show)
  - `POST   /livehost/replacements/{id}/assign` (PIC assign)
  - `POST   /livehost/replacements/{id}/reject` (PIC reject)
- **Authorization:**
  - Host routes: `role:live_host` middleware + Gate on the model (only the assignment owner can request/withdraw)
  - PIC routes: `role:admin,admin_livehost` middleware

### 4.2 Data model

```text
session_replacement_requests
  id                           bigInt PK
  live_schedule_assignment_id  FK → live_schedule_assignments (slot being given up)
  original_host_id             FK → users (the requesting live_host)
  replacement_host_id          FK → users (nullable; set when PIC assigns)
  scope                        string CHECK IN ('one_date','permanent')
  target_date                  date NULL (required when scope='one_date')
  reason_category              string CHECK IN ('sick','family','personal','other')
  reason_note                  text NULL
  status                       string CHECK IN ('pending','assigned','withdrawn','expired','rejected')
  requested_at                 datetime
  assigned_at                  datetime NULL
  assigned_by_id               FK → users NULL
  rejection_reason             text NULL
  expires_at                   datetime
  live_session_id              FK → live_sessions NULL (set when replacement actually goes live)
  created_at, updated_at, deleted_at
```

Indexes: `(status, expires_at)` for the cron, `(original_host_id, requested_at)` for reporting, `(live_schedule_assignment_id, target_date, status)` for duplicate-pending guard.

### 4.3 Status state machine

```
pending  ──▶ assigned   (PIC assigns replacement_host_id)
pending  ──▶ withdrawn  (original host clicks Withdraw)
pending  ──▶ expired    (cron passes expires_at)
pending  ──▶ rejected   (PIC declines)
```

All non-pending states are terminal.

### 4.4 Permanent-scope semantics

When `scope='permanent'` and PIC assigns:
1. The request row is updated as for `one_date` (status, replacement_host_id, etc.).
2. **Within the same DB transaction**, `LiveScheduleAssignment.live_host_id` is updated to the replacement host. The recurring slot now belongs to the new host from this point forward.
3. The replacement request itself serves as the audit record. No separate audit table for v1.

## 5. Host-side UX

### 5.1 Schedule card states

| Slot state | Card shows |
|---|---|
| Normal | "Mohon ganti" link at the bottom |
| Pending request exists | Pill "Menunggu PIC" + "Tarik balik" link |
| Replaced (one_date, status=assigned) | Pill "Telah diganti" + replacement name |
| Permanent + assigned | Card removed (assignment now owned by replacement) |

### 5.2 Request modal

- Scope: radio (`Tarikh ini sahaja` / `Secara kekal`)
- Date picker (only for `one_date`): default = next upcoming occurrence of `assignment.day_of_week`; min = today; max = +28 days; days not matching the slot's day_of_week are disabled
- Reason category: dropdown (`Sakit / Kecemasan keluarga / Urusan peribadi / Lain-lain`)
- Note: optional textarea, max 500 chars
- Inline warning: *"Komisen untuk slot ini akan diberikan kepada pengganti, bukan anda."*
- Buttons: `Batal` / `Hantar permohonan`

### 5.3 Withdrawal

Confirm dialog on the schedule card. Hits `DELETE /live-host/replacement-requests/{id}`. Backend rejects unless `status='pending'` and `original_host_id = auth()->id()`.

### 5.4 Validation (`StoreReplacementRequest`)

- `scope` required, in enum
- `target_date` required-with `scope=one_date`, after_or_equal today, must match `assignment.day_of_week`
- `reason_category` required, in enum
- `reason_note` nullable, max:500
- Custom rule: no existing pending request for the same `(assignment_id, target_date)` (or `(assignment_id, scope=permanent)`)
- Custom rule: `assignment.live_host_id === auth()->id()`
- Error messages in Malay

`expires_at` is computed server-side:
- `one_date` → `target_date` + `assignment.timeSlot.start_time`
- `permanent` → `requested_at + 24 hours`

## 6. PIC-side UX

### 6.1 Index page

Tabs by status (`Pending`, `Assigned`, `Expired`, `Rejected`, `Withdrawn`); filters by host, platform, date range. Each pending row shows host, slot, scope, reason, and a live "Expires in Xh Ym" countdown computed from `expires_at`. Default tab is `Pending` ordered by `expires_at ASC` so urgent ones float to the top.

A small badge ("Pending replacements: N") is added to the existing PIC dashboard home so PIC notices without manually opening the page.

### 6.2 Show / assign page

Displays request details, reason, expiry countdown, original-host repeat-offender stat (count of `assigned + expired + withdrawn` in last 90 days — visibility only, no automated blocking).

**Available-hosts list:**
- All users with role `live_host`, excluding the original host
- Excludes hosts with another `LiveScheduleAssignment` overlapping the same time window on the target day_of_week
- Surfaces (does not hide) hosts with adjacent slots, with a ⚠ note for PIC awareness
- Ordered by ascending count of prior `assigned` replacement requests (favors hosts not yet over-used as replacements)

**Assign action** (`AssignReplacementRequest` form request):
- Validates: status still `pending`, `replacement_host_id` is a live_host, no overlap on target_date
- DB transaction:
  1. Update request: `status='assigned'`, `replacement_host_id`, `assigned_at`, `assigned_by_id`
  2. If `scope='permanent'`: update `LiveScheduleAssignment.live_host_id`
  3. Queue `ReplacementAssignedToYouNotification` → replacement
  4. Queue `ReplacementResolvedNotification` → original

**Reject action:** requires `rejection_reason`, sets `status='rejected'`, dispatches `ReplacementResolvedNotification` to original host.

### 6.3 Commission impact

- **Replacement host's earnings:** No code change. The `LiveSession` row created when they go live has `live_host_id = replacement_host_id`, so existing payroll picks them up.
- **Original host's earnings:**
  - `one_date`: they don't go live → no `LiveSession` row → naturally zero.
  - `permanent`: assignment ownership is transferred → future weeks aren't theirs.
- **Guardrail:** in the controller that creates a `LiveSession` on "go live", reject if there is a `status=assigned` replacement request for `(assignment_id, today)` and `replacement_host_id !== current_user`. Returns 422 with Malay message *"Slot ini telah diganti. Sila hubungi PIC."*

### 6.4 Reporting

A small "Replacement requests (90d): N" line on the existing host detail admin page, plus a sortable column on the new replacements index.

```php
SessionReplacementRequest::query()
    ->selectRaw('original_host_id, status, count(*) as total')
    ->whereBetween('requested_at', [$from, $to])
    ->groupBy('original_host_id', 'status')
    ->get();
```

## 7. Notifications (Malay)

All three follow the existing `ScheduleAssignmentNotification` pattern: `implements ShouldQueue`, channels `['mail','database']`, uses `MailMessage` (no separate Blade templates).

### 7.1 `ReplacementRequestedNotification` → admins

```
Subjek: Permohonan Ganti Slot — {host} ({day} {time})

Salam,

{host} telah memohon penggantian untuk slot berikut:
  Platform : {platform_name}
  Slot     : {day}, {start} – {end}
  Tarikh   : {target_date} (sekali sahaja) | Mulai segera (penggantian kekal)
  Sebab    : {reason_label_malay}
  Catatan  : {note or "—"}

Permohonan ini akan tamat tempoh secara automatik
pada {expires_at} ({countdown})
jika tiada pengganti dipilih.

Sila tetapkan pengganti di pautan di bawah:
  → {url to /livehost/replacements/{id}}

Terima kasih,
Mudeer Bedaie
```

### 7.2 `ReplacementAssignedToYouNotification` → replacement host

```
Subjek: Anda Telah Ditugaskan Sebagai Pengganti — {day} {time}

Salam {replacement_name},

PIC telah menugaskan anda untuk menggantikan slot berikut:
  Platform : {platform_name}
  Slot     : {day}, {start} – {end}
  Tarikh   : {target_date or "Mulai segera (kekal)"}
  Asal     : {original_host_name}

Komisen penuh untuk slot ini akan diberikan kepada anda
seperti biasa.

Slot ini kini akan kelihatan dalam jadual anda di:
  → /live-host/schedule

Sekian, terima kasih.
```

### 7.3 `ReplacementResolvedNotification` → original host

Body branches on final status:
- `assigned`: *"Permohonan anda telah diluluskan. Pengganti: {replacement_name}."*
- `rejected`: *"Permohonan anda ditolak oleh PIC. Sebab: {rejection_reason}. Anda masih bertanggungjawab untuk slot ini."*
- `expired`: *"Permohonan anda telah tamat tempoh tanpa pengganti dipilih. Sila pastikan anda hadir untuk slot ini, atau hubungi PIC dengan segera."*

### 7.4 Admin recipient lookup

```php
$admins = User::query()->whereIn('role', ['admin', 'admin_livehost'])->get();
Notification::send($admins, new ReplacementRequestedNotification($request));
```

Matches the existing fan-out pattern in this codebase.

## 8. Auto-expiry

```php
// app/Console/Commands/ExpireReplacementRequestsCommand.php
SessionReplacementRequest::query()
    ->where('status', 'pending')
    ->where('expires_at', '<=', now())
    ->each(function (SessionReplacementRequest $req) {
        $req->update(['status' => 'expired']);
        $req->originalHost->notify(new ReplacementResolvedNotification($req));
    });
```

Scheduled in `routes/console.php` to run `everyFiveMinutes`. The `where('status', 'pending')` guard makes re-runs idempotent.

## 9. Testing

Feature tests under `tests/Feature/SessionReplacement/`:

- `RequestReplacementTest` — submit happy path; rejects past dates / wrong day_of_week / non-owner / duplicate pending; withdrawal flips status; can't withdraw when not pending.
- `AssignReplacementTest` — assign happy path; time-overlap candidate blocked; permanent scope updates `LiveScheduleAssignment.live_host_id`; can't assign twice; original host blocked from creating a `LiveSession` for a replaced slot.
- `RejectReplacementTest` — reject sets status, requires reason, dispatches notification.
- `ExpireReplacementRequestsTest` — cron flips expired, idempotent on re-run, dispatches notification.
- `ReplacementNotificationsTest` — `Notification::fake()`, asserts the three classes fire to the right recipients and assert literal Malay subject lines.
- `ReplacementVisibilityTest` — host sees own requests only; admin/admin_livehost see all.

One Pest 4 browser smoke test under `tests/Browser/`: host requests on `/live-host/schedule` → PIC assigns on `/livehost/replacements` → replacement appears on the assigned host's `/live-host/schedule`. Catches the Inertia wiring across both apps.

## 10. Out of scope (v1)

- Configurable expiry window per platform (hardcoded: slot-start for one_date, 24h for permanent).
- Automated lockout for hosts with too many replacements (we surface the count; we don't act on it).
- SMS/WhatsApp notifications (mail + database channels only).
- Re-using a Pest dataset for the four `reason_category` values (will add only if the test file gets repetitive).
- A separate audit table for permanent-replacement assignment ownership transfers (the request row itself is the audit trail in v1).

## 11. Migration compatibility

Per CLAUDE.md, all migrations must run on both MySQL (production) and SQLite (development). The `session_replacement_requests` table uses standard `string` columns with check constraints (not native enums) and will not be altered after creation, so no `DB::getDriverName()` branching is needed for the create migration. If a future migration needs to alter an enum-style column, it must use the dual-driver pattern documented in CLAUDE.md.
