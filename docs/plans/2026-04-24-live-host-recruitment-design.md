# Live Host Recruitment — Design

**Date:** 2026-04-24
**Owner:** Live Host Desk (admin_livehost / admin)
**Status:** Approved for implementation

## Goal

Build a recruitment module inside the Live Host Desk that lets admins publish recruitment campaigns, collect applications through a public form, review applicants through a configurable multi-stage pipeline, and hire selected candidates by creating a `User` with the `live_host` role.

## Context

The HR module (`/hr/*`) already has a full recruitment system with `JobPosting`, `Applicant`, `ApplicantStage`, `Interview`, `OfferLetter`, public careers endpoints, and a hire flow that creates an internal employee. Live host recruitment has the same shape but ends in a different type of record (LiveHost / commission-based contractor, not an employee with department/position/salary).

**Decision:** Build parallel tables and UI scoped to the Live Host Desk rather than reusing HR schema. This keeps the two lifecycles independent and avoids coupling two product areas that will evolve separately.

## Architecture

**Placement:** Extends `/livehost/*` Inertia + React (matches [HostController](../../app/Http/Controllers/LiveHost/HostController.php) and the rest of the Live Host Desk PIC UI).

**Access control**
- Admin: `auth` + `role:admin_livehost,admin` (same middleware group as existing livehost routes)
- Public application form: unauthenticated

**New left-nav item** in the Live Host Desk sidebar (under OPERATIONS):

```
Dashboard
Live Hosts (4)
Recruitment     ← NEW
  ├─ Campaigns
  └─ Applicants
...
```

**New routes**

Admin (Inertia):
- `GET  /livehost/recruitment/campaigns`
- `GET  /livehost/recruitment/campaigns/{campaign}`
- `POST /livehost/recruitment/campaigns`
- `PUT  /livehost/recruitment/campaigns/{campaign}`
- `PATCH /livehost/recruitment/campaigns/{campaign}/publish`
- `PATCH /livehost/recruitment/campaigns/{campaign}/close`
- `DELETE /livehost/recruitment/campaigns/{campaign}` (guarded: only if no applicants)
- `GET  /livehost/recruitment/applicants`
- `GET  /livehost/recruitment/applicants/{applicant}`
- `PATCH /livehost/recruitment/applicants/{applicant}/stage` (advance / move)
- `PATCH /livehost/recruitment/applicants/{applicant}/reject`
- `POST  /livehost/recruitment/applicants/{applicant}/hire`
- `PATCH /livehost/recruitment/applicants/{applicant}/notes`

Public (blade, unauthenticated):
- `GET  /recruitment/{slug}` — show form
- `POST /recruitment/{slug}` — submit application
- `GET  /recruitment/{slug}/thank-you`

## Data Model

### `live_host_recruitment_campaigns`

| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| title | string | |
| slug | string, unique | used in public URL |
| description | longText | rich text, shown publicly |
| status | enum: `draft`, `open`, `paused`, `closed` | default `draft` |
| target_count | unsigned int, nullable | how many to hire |
| opens_at | timestamp, nullable | |
| closes_at | timestamp, nullable | |
| created_by | fk users | |
| timestamps | | |

### `live_host_recruitment_stages`

Configurable pipeline per campaign.

| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| campaign_id | fk campaigns | cascade delete |
| position | unsigned int | for ordering |
| name | string | e.g. "Review", "Interview" |
| description | text, nullable | internal admin note about the stage |
| is_final | boolean | exactly one stage per campaign has `is_final=true`; the Hire action is only available when applicant is at this stage |
| timestamps | | |

When a campaign is created, seed 4 default stages:
1. Review (position=1)
2. Interview (position=2)
3. Test Live (position=3)
4. Final (position=4, `is_final=true`)

Admin can rename/reorder/add/remove stages while the campaign has no applicants. Once applicants exist, restrict destructive edits (rename/reorder allowed; delete blocked if any applicant is at that stage).

### `live_host_applicants`

| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| campaign_id | fk campaigns | |
| applicant_number | string, unique | e.g. `LHA-202604-0001` |
| full_name | string | |
| email | string | |
| phone | string | |
| ic_number | string, nullable | |
| location | string, nullable | |
| platforms | json | e.g. `["tiktok", "shopee"]` |
| experience_summary | text | |
| motivation | text | "why join" |
| resume_path | string, nullable | storage disk path |
| source | string, nullable | e.g. `facebook_ad`, `referral` |
| current_stage_id | fk stages, nullable | null until first move (starts at the first stage by default on create) |
| status | enum: `active`, `rejected`, `hired`, `withdrawn` | default `active` |
| rating | tinyint, nullable | 1–5 |
| notes | text, nullable | admin-only private notes |
| applied_at | timestamp | |
| hired_at | timestamp, nullable | |
| hired_user_id | fk users, nullable | |
| timestamps | | |

Unique compound: `(campaign_id, email)` — prevents duplicate applications to the same campaign. A candidate can still apply to different campaigns.

### `live_host_applicant_stage_history`

Audit trail for every state change.

| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| applicant_id | fk applicants | |
| from_stage_id | fk stages, nullable | |
| to_stage_id | fk stages, nullable | null on reject/hire |
| action | enum: `applied`, `advanced`, `reverted`, `rejected`, `hired`, `note` | |
| notes | text, nullable | |
| changed_by | fk users, nullable | null for the `applied` system event |
| timestamps | | |

On create (public form submit), write an `applied` row. Every admin action writes one row.

## Public Application Form

### URL

`https://mudeerbedaie.test/recruitment/{slug}`

### Page content

Header: campaign title, description (rich text), closing date if set.

Form fields:
- Full name (required)
- Email (required, validated)
- Phone (required)
- IC number (optional)
- Location (optional, free text)
- Platforms interested in (checkbox group — TikTok / Shopee / Facebook; at least one required)
- Experience summary (textarea)
- Why do you want to join us? (textarea)
- Upload resume (optional, PDF/DOC, max 5 MB)

### Submission rules

- Reject if `status != 'open'` (show "This campaign is no longer accepting applications")
- Reject if `closes_at` has passed
- Reject if email already used for this campaign (show friendly message, not an error)
- On success:
  - Create `live_host_applicants` row, status `active`, current_stage_id = first stage of campaign
  - Write `applied` history row
  - Send confirmation email (application received)
  - Redirect to `/recruitment/{slug}/thank-you`

### Stack

Standard Blade view + Laravel controller (NOT Inertia — the public page has no auth context, and the rest of `/livehost/*` Inertia assumes authenticated PIC). File uploads handled with Laravel's built-in validation + default storage disk.

## Admin: Campaign Management

### List page (`/livehost/recruitment/campaigns`)

Table columns: Title, Status badge, Applicants count, Target, Opens / Closes, Actions.

Actions menu per row:
- Edit
- Copy public link (disabled unless status=`open`)
- Publish (if status=`draft`)
- Pause (if status=`open`)
- Resume (if status=`paused`)
- Close (final; can't reopen)
- Delete (only if 0 applicants)

### Create / Edit page

Form:
- Title → slug auto-generated (editable, validated unique)
- Description (rich text editor)
- Target count (optional)
- Opens at / Closes at (date-time pickers, both optional)
- Status (draft / open)

**Stage editor** below the form: drag-to-reorder list of stages with inline rename/add/remove and an `is_final` toggle (enforce exactly one final stage). New campaigns are seeded with the 4 default stages.

### Publish

Dedicated button on the detail page. Transitions `draft → open` and reveals the public URL with a copy-to-clipboard button.

## Admin: Applicant Review

### List page (`/livehost/recruitment/applicants`)

**Kanban board:** one column per stage of the currently selected campaign. Each column shows applicants whose `current_stage_id` matches and `status = active`.

**Filters (left sidebar):**
- Campaign selector (single-select, remembers last choice)
- Platform (multi-select chips)
- Rating (1–5)
- Status tabs at the top: `Active` (default) / `Rejected` / `Hired`

**Card content:** full name, applied-at (`diffForHumans`), platform chips, rating stars.

### Detail view (drawer or modal)

Tabs:
1. **Application** — all form fields read-only, resume link, copy-to-clipboard on email/phone
2. **Activity** — reverse-chronological `stage_history` timeline with action type, notes, admin name, timestamp
3. **Notes** — admin-only private notes (free text, debounced auto-save, not shown to candidate)

**Action bar (bottom of drawer):**
- `Move to next stage` — advances by one position
- `Move to specific stage ▾` — dropdown of all stages in this campaign
- `Reject` — opens confirm dialog with reason textarea
- `Hire` — **enabled only when** `current_stage_id.is_final = true`

Every action writes a `stage_history` row.

## Hire Action

When the admin clicks **Hire**:

1. Confirm modal pre-fills name, email, phone from the application (editable).
2. On confirm, within a DB transaction:
   - Create `User` with:
     - `name`, `email`, `phone` from the form
     - role: `live_host` (using the app's existing role assignment mechanism — TBD during implementation based on how roles are wired)
     - password: random 32-char string (candidate doesn't use it directly; gets a reset link)
     - `email_verified_at = now()`
   - Update applicant: `status = hired`, `hired_at = now()`, `hired_user_id = user.id`
   - Write `stage_history` row: action `hired`
3. After success, show a result panel with:
   - The new user's login email
   - A **"Send password reset link"** button (generates a standard Laravel password reset and copies/shows the URL for manual sharing — since automated onboarding emails are out of scope for v1)
   - A **"Create Live Host profile →"** CTA that deep-links to `/livehost/hosts/create?user_id={id}` so the PIC can finish setup (Host record, platform accounts, commission profile) manually

**Idempotency:** if the applicant is already `hired`, the Hire button is hidden.

## Rejection

Modal with reason textarea (stored in `stage_history.notes`). Sets `status = rejected` and removes the card from the default kanban view (it shows up under the Rejected tab).

No email sent in v1.

## Out of Scope for v1

Explicitly not building in this iteration (call out to prevent scope creep):
- Rejection, stage-movement, or hired emails to candidate
- Structured interview scheduling (calendar invites, availability) — use stage notes
- Custom form fields per campaign — fixed schema
- Auto-creation of LiveHost record / platform accounts / commission profile on hire (manual via the `Create Live Host profile` deep link)
- Recruitment analytics dashboard (conversion rates, time-per-stage, source tracking)
- Bulk actions on applicants
- Candidate self-service portal to check their own status
- Reopening a closed campaign

## Testing

- Browser (Pest 4): public form happy path, duplicate-email dedupe, closed/paused/expired campaign rejection, thank-you page
- Feature: campaign CRUD, stage reorder, applicant stage move, reject, hire (asserts User+role created, applicant status updated, history rows written)
- Factories + seeders for campaigns, stages, applicants

## Migration Compatibility

Follows the MySQL + SQLite dual-driver rule from `CLAUDE.md`. Enum columns use string with CHECK constraint (not MySQL enum) to avoid Doctrine DBAL on column modification. No `renameColumn` on enums.

## Open Questions (resolve during implementation)

1. Exact mechanism for assigning the `live_host` role on hire (existing role system — spatie, enum on users table, or custom pivot) — verify during implementation.
2. Resume storage disk — confirm `public` or a private disk with signed URLs for admin-only access.
3. Public thank-you page copy — placeholder text in v1, PIC can request edits.
