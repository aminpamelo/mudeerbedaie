# Pocket Session Recap — Proof of Live & Missed-Session Flow

**Date:** 2026-04-19
**Status:** Design approved, ready for planning
**Surface:** Live Host Pocket (React SPA at `/live-host/*`)

## Problem

Today the Pocket Sessions list only exposes a "recap & upload" link for sessions whose status is already `live` or `ended`. A host whose session has `status = scheduled` and has passed its scheduled start time has no way to:

1. Record that they actually went live (and upload proof).
2. Record that they did *not* go live and give a reason.

Admins have no reliable signal to distinguish "host went live successfully" from "host forgot to close out" from "host genuinely didn't stream". The current recap form accepts any submission — even one with zero attachments — so "recapped" is not a trustworthy proxy for "went live".

## Goal

Let hosts self-report session outcomes from the Pocket app immediately after their scheduled window, with a proof artifact (image or video) required for the "went live" path and a structured reason required for the "did not go live" path. Give admins the same capability so they can flag missed sessions retroactively.

## Decisions (captured from brainstorming)

| Decision | Chosen option |
|---|---|
| Proof level | **Hybrid** — require ≥1 image/video attachment + whatever analytics they captured. Remarks and extra files optional. |
| Recap entry point | **When scheduled time has passed** — once `now >= scheduledStartAt`, the card surfaces a "Submit recap" CTA even while `status` is still `scheduled`. |
| Who flags no-show | **Either host or admin** — both paths update the same row, last write wins. |
| Missed reason format | **Preset reasons + optional note** — 5-item enum plus a free-text note field. |
| Missed status value | **New `missed` status** — distinct from `cancelled` (admin-before-the-fact) so reporting stays clean. |

## Data model

### New `live_sessions` status values

Existing: `scheduled`, `live`, `ended`, `cancelled`.
Added: `missed` — host post-fact no-show.

`cancelled` remains admin-only, pre-event ("stream called off because of platform outage"). `missed` is post-fact and reason-tracked.

### New columns on `live_sessions`

| Column | Type | Nullable | Notes |
|---|---|---|---|
| `missed_reason_code` | `VARCHAR(32)` | yes | Enum: `tech_issue`, `sick`, `account_issue`, `schedule_conflict`, `other`. |
| `missed_reason_note` | `TEXT` | yes | Free-form, ≤500 chars. |

Both only populated when `status = missed`. Preserved across status flips (see "Data preservation" below).

### Proof-of-live rule

Enforced in the request-validation layer, not via a database constraint:

> Before a session transitions to `ended` via the recap endpoint, at least one `LiveSessionAttachment` whose `file_type` matches `image/*` or `video/*` must exist for that session.

Other attachment types (PDF, documents) are still allowed; they just don't satisfy the proof requirement on their own.

### Data preservation on flip

If a host accidentally marks "missed" then flips back to "went live" (or vice versa), previously entered analytics and attachments are **kept**, not wiped. Reason fields are cleared only when going *from* `missed` *to* `ended`. Analytics rows are preserved when going `ended → missed` (they just stop being shown). This keeps self-correction cheap.

## Sessions list — card state matrix

[Sessions.jsx](../../resources/js/livehost-pocket/pages/Sessions.jsx)

| Derived state | Condition | Primary CTA | Secondary CTA | Badge |
|---|---|---|---|---|
| Live | `status === 'live'` | **Manage session →** | — | `LIVE` (pulsing) |
| Awaiting recap (new) | `status === 'scheduled' && now >= scheduledStartAt` | **Submit recap →** (accent) | **Didn't go live** (muted text button) | `RECAP PENDING` (warn color) |
| Ended | `status === 'ended'` | **Open recap & upload →** | — | `ENDED` |
| Missed (new) | `status === 'missed'` | Read-only footer: `MISSED · {reason label}` | — | `MISSED` (muted red) |
| Cancelled | `status === 'cancelled'` | `Session cancelled` footer | — | `CANCELLED` |
| Scheduled (future) | `status === 'scheduled' && now < scheduledStartAt` | `Awaiting start` footer | — | `SCHED` |

### Upcoming filter behavior

The `Upcoming` tab currently hides everything except `scheduled`. "Awaiting recap" cards (scheduled + past start) are *more* urgent than scheduled-future, so they stay in the Upcoming tab and sort to the very top.

Once the host submits (either path), the card naturally leaves Upcoming and appears in Ended (for `went_live`) or in the All/Missed filter (for `missed`).

### Backend flag

The Inertia DTO gains a single computed `canRecap` boolean so the React component doesn't have to recompute time comparisons:

```php
$canRecap = in_array($session->status, ['ended', 'missed'])
    || ($session->status === 'scheduled' && $session->scheduled_start_at <= now());
```

## SessionDetail recap page

[SessionDetail.jsx](../../resources/js/livehost-pocket/pages/SessionDetail.jsx)

### Top-level Yes/No switch

Two mutually exclusive segmented buttons at the top of the form:

- **Yes, I went live** — shows the existing recap form (cover image, timing, analytics, remarks, attachments).
- **No, I missed it** — collapses those sections and shows the missed-reason form.

Default pre-selection based on current session status:
- `ended` → Yes pre-selected.
- `missed` → No pre-selected.
- `scheduled` (past start) → neither pre-selected; user must choose before the form reveals.

### "Yes" path — proof validation

- A persistent hint lives above the Save button: `PROOF · image or video attachment required`.
- The Save button is disabled client-side while no image/video is attached; server validates again (422 on failure) as defense in depth.
- The "Add file" button copy switches to `Add proof (image/video)` when no proof is attached yet, then reverts to `Add file` once proof is present.
- Analytics, timing, remarks stay optional.

### "No" path — missed reason form

- Radio group of the 5 preset reasons:
  - `tech_issue` — Tech / connection issue
  - `sick` — Sick
  - `account_issue` — Platform account issue
  - `schedule_conflict` — Schedule conflict
  - `other` — Other
- Optional free-text note (textarea, 500-char max).
- Submit button labeled `Mark as missed`, muted red instead of accent.
- All other sections hidden while this path is active.

### Switching paths after save

Allowed — but guarded by a confirm dialog:

> Switch from "Did not go live" to "Went live"? Your reason will be cleared.

Analytics/attachments previously captured remain when flipping the other direction.

### Admin parity

Admins hitting the same endpoint from the desk (not covered by this design, but structurally shared) can flip either status. Attribution (host vs admin action) is implicit via `updated_by` — no new column planned for v1. If later reporting demands distinction, add `missed_by_user_id` then.

## Backend contract

### Routes (existing URLs, new behavior)

| Method | Path | Handler | Change |
|---|---|---|---|
| `POST` | `/live-host/sessions/{id}/recap` | `SessionDetailController::saveRecap` | Accepts new `went_live`, `missed_reason_code`, `missed_reason_note`. Branches on `went_live`. |
| `POST` | `/live-host/sessions/{id}/attachments` | existing | Unchanged |
| `DELETE` | `/live-host/sessions/{id}/attachments/{a}` | existing | Unchanged |

### Validation — new `SaveSessionRecapRequest`

```php
'went_live' => ['required', 'boolean'],

// went_live === true branch
'actual_start_at'    => ['required_if:went_live,true', 'nullable', 'date'],
'actual_end_at'      => ['required_if:went_live,true', 'nullable', 'date', 'after:actual_start_at'],
'cover_image'        => ['nullable', 'image', 'max:5120'],
'viewers_peak'       => ['nullable', 'integer', 'min:0'],
'viewers_avg'        => ['nullable', 'integer', 'min:0'],
'total_likes'        => ['nullable', 'integer', 'min:0'],
'total_comments'     => ['nullable', 'integer', 'min:0'],
'total_shares'       => ['nullable', 'integer', 'min:0'],
'gifts_value'        => ['nullable', 'numeric', 'min:0'],
'remarks'            => ['nullable', 'string', 'max:2000'],

// went_live === false branch
'missed_reason_code' => ['required_if:went_live,false', 'in:tech_issue,sick,account_issue,schedule_conflict,other'],
'missed_reason_note' => ['nullable', 'string', 'max:500'],
```

Plus a custom `after` hook on the request that runs only when `went_live === true`:

```php
$hasProof = LiveSessionAttachment::query()
    ->where('live_session_id', $this->route('session')->id)
    ->where(function ($q) {
        $q->where('file_type', 'like', 'image/%')
          ->orWhere('file_type', 'like', 'video/%');
    })
    ->exists();

if (! $hasProof) {
    $validator->errors()->add(
        'proof',
        'Upload at least one image or video as proof you went live.'
    );
}
```

### Controller branching

```php
// Inside SessionDetailController::saveRecap
if ($request->boolean('went_live')) {
    $session->fill($request->validated())
        ->fill(['status' => 'ended',
                'missed_reason_code' => null,
                'missed_reason_note' => null])
        ->save();

    // persist analytics, cover, etc. — existing helpers
} else {
    $session->fill([
        'status' => 'missed',
        'missed_reason_code' => $request->input('missed_reason_code'),
        'missed_reason_note' => $request->input('missed_reason_note'),
    ])->save();

    // analytics & attachments kept as-is
}
```

### Migration (MySQL + SQLite compatible per CLAUDE.md)

```
1. ALTER TABLE live_sessions ADD COLUMN missed_reason_code VARCHAR(32) NULL
2. ALTER TABLE live_sessions ADD COLUMN missed_reason_note TEXT NULL
3. Update status constraint/enum to include 'missed' — use DB::getDriverName() branch
```

### DTO updates

`LiveSessionController::buildSessionDto()` exposes two new fields and one flag:
- `missedReasonCode`
- `missedReasonNote`
- `canRecap`

## Edge cases

- **Submitted without proof** → 422, Inertia errors surface inline in an amber banner above the attachments section.
- **Flip went_live → missed with analytics present** → analytics kept; hidden from the UI; recoverable on flip back.
- **> 72h past start, no recap submitted** → stays in "Awaiting recap" state forever for v1. No auto-lapse. Admin dashboards can surface these later via a separate filter.
- **Concurrent writes** (host submits recap while admin marks missed) → last write wins. Not worth optimistic locking for v1.

## Tests (Pest)

Feature tests under `tests/Feature/LiveHostPocket/SessionRecapTest.php`:

1. `POST /recap` with `went_live=true` and no image/video attachment → 422 with `proof` error key.
2. `POST /recap` with `went_live=true` and one image attachment → 200, `status` flips to `ended`.
3. `POST /recap` with `went_live=false` and valid reason code → 200, `status` → `missed`, reason persisted.
4. `POST /recap` with `went_live=false` and missing reason → 422.
5. Flip `went_live=false` → `went_live=true` — analytics preserved, reason fields cleared.
6. `canRecap` flag — scheduled session past start exposes `true`; future session `false`.
7. Unit: `LiveSession::requiresRecap()` (if extracted) returns correct bool per status.
8. Browser (Pest 4): Sessions list Upcoming tab surfaces a past-scheduled card with "Submit recap" CTA; tapping "Didn't go live" opens the missed-reason form, saving flips card into Missed state.

## Files touched

**New**
- `database/migrations/YYYY_MM_DD_add_missed_recap_to_live_sessions.php`
- `app/Http/Requests/LiveHostPocket/SaveSessionRecapRequest.php`
- `tests/Feature/LiveHostPocket/SessionRecapTest.php`

**Edited**
- `app/Http/Controllers/LiveHostPocket/SessionDetailController.php` — branched `saveRecap`
- `app/Http/Controllers/LiveHostPocket/SessionsController.php` — expose `canRecap`, sort awaiting-recap cards to top
- `app/Models/LiveSession.php` — casts, `requiresRecap` helper, new fillable fields
- `resources/js/livehost-pocket/pages/Sessions.jsx` — awaiting-recap card state, Missed chip, dual CTAs
- `resources/js/livehost-pocket/pages/SessionDetail.jsx` — top-level switch, proof validation UX, missed-reason form
- `resources/js/livehost-pocket/lib/format.js` — helper for reason code → label if not already present

## Out of scope

- Admin-side views of the missed-reason analytics (Live Host Desk dashboards).
- Auto-nudging via email/WhatsApp for past-scheduled sessions still in `scheduled`.
- Fraud detection (same image reused across sessions).
- Multi-language reason codes — English only for v1, matching the rest of the app.
