# Class Syllabus + Inline Assignment from Upsell Tab

Date: 2026-04-27

## Problem

The class show page lets admins manage sessions, students, payments, certificates, PICs, and (recently) per-session upsell config — but there is no way to record *what the teacher should teach* in each session. Admins currently track curriculum coverage outside the system. They want to assign syllabus topics to sessions directly from the existing Upsell tab table (which is the highest-density per-session management surface) without leaving the page.

## Goal

Give each class an ordered library of syllabus topics, and let admins attach one or more topics to any session inline from the Upsell tab.

## Non-goals

- Tracking per-student progress through the syllabus (separate concern).
- Showing syllabus in the Master Timetable session modal, the teacher's session detail page, or the student-facing pages — follow-up iterations.
- Drag-and-drop reordering — v1 ships with up/down arrow buttons; D&D can come later.
- Bulk-assigning a topic to a date range of sessions.

## Data model

**New table `class_syllabi`** (Laravel will use `class_syllabi` automatically since `Syllabus` → `Syllabi` per English plural rules; we set `$table` explicitly to make this stable across environments).

| Column        | Type           | Notes                                |
|---------------|----------------|--------------------------------------|
| `id`          | bigint PK      |                                      |
| `class_id`    | foreignId      | FK → `classes.id`, cascade on delete |
| `title`       | string(255)    | required                             |
| `description` | text nullable  | optional longer notes                |
| `sort_order`  | unsigned int   | default 0, used for ordering         |
| `created_at`  | timestamp      |                                      |
| `updated_at`  | timestamp      |                                      |

Index on `(class_id, sort_order)` for ordered lookups.

**New column on `class_sessions`**: `syllabus_ids` — JSON nullable. Mirrors the existing `upsell_funnel_ids` / `upsell_pic_user_ids` / `upsell_teacher_ids` columns so a session can carry multiple topics.

**Migration compatibility**: standard `Schema::create()` and `$table->json()` work on both MySQL and SQLite without driver branching, so no `DB::getDriverName()` switch is needed for the create. Both `down()` paths are also straight `dropColumn` / `drop` and need no branching.

## Models

**New `App\Models\ClassSyllabus`**
- `$fillable` = `['class_id', 'title', 'description', 'sort_order']`.
- `$table = 'class_syllabi'` to be explicit.
- `class(): BelongsTo` → `ClassModel`.
- `HasFactory` + `ClassSyllabusFactory` for tests.

**`ClassModel` additions**
- `syllabi(): HasMany` returning `ClassSyllabus::class` ordered by `sort_order` ASC.

**`ClassSession` additions**
- `'syllabus_ids'` added to `$fillable`.
- `'syllabus_ids' => 'array'` cast.
- `syllabusItems(): \Illuminate\Database\Eloquent\Collection` — returns `ClassSyllabus::whereIn('id', $ids)->orderBy('sort_order')->get()` (same shape as `upsellFunnels()`). Empty collection when null/empty.

## UI

### New "Syllabus" tab on the class page

Inserted between **Timetable** and **Certificates** in [resources/views/livewire/admin/class-show.blade.php](resources/views/livewire/admin/class-show.blade.php). The component already routes via `public string $activeTab` — we add `'syllabus'` as a valid value and a tab nav entry.

**Layout:**
- Header row: heading "Syllabus" + subtext "Topics this class will cover" + primary `Add Topic` button (opens modal).
- Empty state when `$this->class->syllabi->isEmpty()`: centered icon + "No syllabus topics yet — add the first one to get started."
- Topic list: ordered cards iterating `$this->class->syllabi`, each showing:
  - Sequence badge `#1`, `#2`, ...
  - Title (medium weight)
  - Description (muted, line-clamped to 3 lines)
  - Up / Down icon buttons (disabled at boundaries)
  - Edit (pencil) icon → opens edit modal pre-filled
  - Delete (trash) icon → inline confirm flow

**Modal** (single `flux:modal`, reused for add + edit) — fields:
- Title (`flux:input`, required)
- Description (`flux:textarea`, optional, 4 rows)

**Volt methods:**
- `openAddTopicModal()`, `openEditTopicModal(int $id)`, `closeTopicModal()`
- `saveTopic()` — validates `['topicTitle' => 'required|string|max:255', 'topicDescription' => 'nullable|string']`, inserts (with `sort_order = max+1`) or updates.
- `deleteTopic(int $id)` — also `null`-out any `class_sessions.syllabus_ids` entries that reference it (loop affected sessions; we expect this to be cheap because the operation is rare).
- `moveTopicUp(int $id)` / `moveTopicDown(int $id)` — swap `sort_order` with the neighbour, then re-densify all topics for that class so the sequence stays `0, 1, 2, ...`.

### Upsell tab integration

In the Session Upsell Management table (line ~7606 of `class-show.blade.php`), add a new **Syllabus** column inserted between **Status** and **Funnel**.

**Cell editor** mirrors the existing Funnel / PIC / Teacher Alpine multi-select chip pattern:
- Selected items render as small indigo chips (`bg-indigo-100 dark:bg-indigo-500/20 text-indigo-700`) showing `#{order} Title` truncated.
- `+` button opens a searchable popover listing `$this->class->syllabi` not yet selected on this session, displayed as `#{order} {title}`.
- Add → calls `$wire.addSessionSyllabus(sessionId, syllabusId)`.
- Remove (× on chip) → calls `$wire.removeSessionSyllabus(sessionId, syllabusId)`.

**Volt methods:**
- `addSessionSyllabus(int $sessionId, int $syllabusId)` — append to JSON array, dedupe.
- `removeSessionSyllabus(int $sessionId, int $syllabusId)` — drop from array, write `null` if empty.
- Computed `getAvailableSyllabiProperty()` returning `$this->class->syllabi` (lightweight; no N+1 because it's loaded once per request and used by all rows).

### Tab visibility

The Syllabus tab is admin-only (entire `class-show.blade.php` is admin-scoped) — no extra authorization needed.

## Testing

**Feature tests** (`tests/Feature/Livewire/Admin/ClassSyllabusTest.php`, Pest):
- "admin can add a topic" — Volt::test the class-show component, set fields, call `saveTopic`, assert DB row exists, `sort_order` is 0.
- "admin can reorder topics" — seed 3 topics, call `moveTopicUp` on the middle one, assert new `sort_order` sequence.
- "admin can delete a topic and it is removed from sessions" — seed topic, attach to a session via `addSessionSyllabus`, call `deleteTopic`, assert `class_sessions.syllabus_ids` no longer contains that ID.
- "session syllabusItems() returns topics in sort order" — basic accessor test.

**Visual verification**: Playwright on `/admin/classes/31?tab=syllabus` (CRUD + reorder) then `?tab=upsell` (chip add/remove flows).

## Implementation order

1. Migration + models + factory (no UI yet).
2. Pest test scaffolding (red).
3. Syllabus tab UI + Volt methods (turns reorder/CRUD tests green).
4. Upsell-tab Syllabus column + add/remove methods (turns the attach/delete-cleanup tests green).
5. Pint + Playwright pass.
