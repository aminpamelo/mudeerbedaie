# Recruitment Stage Assignment & Due Dates — Design

Date: 2026-04-25
Status: Approved
Surface: Live Host Desk → Recruitment → Applicants kanban
Files: Inertia React (`resources/js/livehost/...`), controllers under `app/Http/Controllers/LiveHost/...`

## Problem

On the Applicants kanban each card shows just identity + applied-at. The PIC has no
way to record:
- who is owning this applicant at the current stage,
- when the stage needs to be completed,
- per-stage notes scoped to the work happening right now.

Today, clicking a card navigates to a full detail page. The PIC wants a faster
in-place modal that captures these three things and shows overdue state.

## Outcome

Clicking an applicant card on the kanban opens a modal where the PIC can:

1. Assign an admin / admin_livehost user as the stage owner.
2. Set a due-at datetime.
3. Write stage-scoped notes.
4. See an overdue indicator if `due_at` has passed.
5. Move the applicant to the next stage or reject — same actions as the detail page.
6. Open the full profile if they need more context.

When an applicant moves stage, assignee + due_at + stage_notes reset for the new stage;
the previous values are preserved as a closed row of the new join table for history.

## Data model

New table `live_host_applicant_stages`:

| column | type | notes |
|---|---|---|
| id | bigint | PK |
| applicant_id | FK live_host_applicants | cascadeOnDelete |
| stage_id | FK live_host_recruitment_stages | nullOnDelete |
| assignee_id | FK users | nullable, nullOnDelete |
| due_at | datetime | nullable |
| stage_notes | text | nullable |
| entered_at | datetime | required |
| exited_at | datetime | nullable; null = current row |
| timestamps | | |

Indexes:
- `(applicant_id, exited_at)` — find the open row fast.
- `(assignee_id, due_at)` — future overdue queries / dashboards.

The audit log `live_host_applicant_stage_history` is unchanged. The new table holds
mutable per-stage state; history remains an append-only log of transitions.

### Lifecycle

- **Applicant created** → one row inserted with `stage_id = current_stage_id`,
  `entered_at = applied_at`, `exited_at = null`, others null.
- **Stage move** (drag/drop or move-to action) — inside the same DB transaction:
  1. Set `exited_at = now()` on the open row for that applicant.
  2. Insert a new open row for the destination stage with `entered_at = now()`,
     other mutable fields null.
  3. Append the transition row to `live_host_applicant_stage_history` (existing).
- **Reject / Hire / Withdraw** — close the open row (`exited_at = now()`); no new row.
- **Edit current stage** — the new endpoint mutates only the open row.

### Backfill (must work on MySQL + SQLite)

For every existing applicant with a non-null `current_stage_id`, insert one open
row with `entered_at = applied_at`. No driver branching needed (insert-only).

## Backend

### Model

`app/Models/LiveHostApplicantStage.php`

- `belongsTo` applicant, stage, assignee (User).
- Casts: `due_at`, `entered_at`, `exited_at` to `datetime`.
- Scope `open()` filters `exited_at IS NULL`.
- Accessor `is_overdue` = `due_at && due_at->isPast() && exited_at === null`.

### Service

Extract stage-move logic into `app/Services/Recruitment/ApplicantStageTransition.php`
(if not already extracted). Methods:

- `enterFirstStage(LiveHostApplicant $a)` — used at create time.
- `transition(LiveHostApplicant $a, LiveHostRecruitmentStage $to, User $by, ?string $notes)`
  — closes open row, opens new row, writes history entry. Wrapped in `DB::transaction`.

### Controller endpoints

`app/Http/Controllers/LiveHost/RecruitmentApplicantController.php`:

- `PATCH /livehost/recruitment/applicants/{applicant}/current-stage`
  - Body: `assignee_id?`, `due_at?`, `stage_notes?`.
  - Validates via new `UpdateApplicantCurrentStageRequest`:
    - `assignee_id` — exists in users, role IN (admin, admin_livehost).
    - `due_at` — date_format ISO8601, nullable, **may be in the past** (for backdating).
    - `stage_notes` — string, max 5000.
  - Updates only the open row for the applicant. Returns 409 if no open row exists.

- `RecruitmentApplicantController::index` — eager-loads `currentStageRow.assignee`
  and exposes `assignee`, `due_at`, `is_overdue`, `stage_notes` on each applicant
  payload. Also passes a small `assignableUsers` list (admin + admin_livehost,
  ordered by name, fields: id, name, email, avatar initials).

### Authorization

Reuses existing `RecruitmentApplicantPolicy` — same gate as the existing
update/move-stage actions.

## Frontend

### New component

`resources/js/livehost/components/recruitment/StageAssignmentModal.jsx`:

- Triggered by clicking an applicant card on the kanban (`Index.jsx`).
- Header: name, applicant_number, current stage badge, overdue pill when applicable.
- Body:
  - Assignee combobox (filterable list of `assignableUsers`). Avatar + name.
  - Due-at datetime input (native `<input type="datetime-local">` for v1).
  - Stage notes textarea.
- Footer:
  - "Open full profile" link → existing detail page.
  - "Move to next stage" → reuses existing `PATCH /stage` endpoint.
  - "Reject" → reuses existing `PATCH /reject` endpoint.
- Auto-save assignee/due_at/stage_notes on blur (mirrors the existing notes
  auto-save in `Show.jsx`). Debounce 500 ms for the textarea.
- Optimistic UI with rollback on error (mirrors the kanban drag-and-drop pattern).
- Closes on save, on Escape, on backdrop click.

### Index.jsx changes

- Card click no longer navigates; instead opens the modal with that applicant id.
  The drag handle still suppresses the click during drag (`snapshot.isDragging`).
- Card footer adds:
  - Assignee initials avatar (12 px circle) — tooltip with full name. `null` → no avatar.
  - Due-date pill (e.g. "Due Mon 4 May") — red text+ring when overdue, neutral
    otherwise. `null` → no pill.
- After modal save, Inertia partial-reload `applicants` only.

### Show.jsx

- Add a small "Stage assignment" panel showing the open row's assignee, due_at,
  stage_notes (read-only echo + "Edit" button that opens the same modal).
  v1 may keep this panel minimal — modal is the primary edit surface.

## Tests

Pest feature tests under `tests/Feature/LiveHost/Recruitment/`:

1. `StageAssignmentTest::test_create_applicant_opens_first_stage_row`
2. `StageAssignmentTest::test_stage_move_closes_old_and_opens_new_row`
3. `StageAssignmentTest::test_update_current_stage_persists_assignee_due_notes`
4. `StageAssignmentTest::test_update_current_stage_validates_assignee_role`
5. `StageAssignmentTest::test_update_current_stage_returns_409_when_no_open_row`
6. `StageAssignmentTest::test_reject_closes_open_row`
7. `StageAssignmentTest::test_backfill_migration_inserts_open_row_per_applicant`

Browser test (Pest 4) — `tests/Browser/LiveHost/Recruitment/StageAssignmentModalTest.php`:
- Open kanban → click card → fill modal → save → assert card shows assignee
  avatar + due-date pill, no full page navigation occurred.

## Out of scope (v1)

- Email / db notifications on assign or overdue.
- Daily overdue digest job.
- Per-stage column defaults (per-applicant only for now).
- Calendar view of due dates.
- Mobile pocket app surfacing assignment.

## Risks

- **Backfill correctness on production** — guard the migration with an existence
  check before inserting (`whereNotExists` against the new table) so it's idempotent.
- **Concurrent drag + modal save** — both touch the open row. Mitigation: the
  `current-stage` PATCH targets `WHERE applicant_id = ? AND exited_at IS NULL`. If
  affected rows = 0, return 409 and surface a "moved by someone else, refresh" toast.
- **Click vs drag conflict** — covered by the existing `snapshot.isDragging`
  guard in `Index.jsx`.
