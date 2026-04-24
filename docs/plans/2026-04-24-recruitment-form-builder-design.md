# Recruitment Form Builder — Design

**Date:** 2026-04-24
**Owner:** Live Host Desk (admin_livehost / admin)
**Status:** Approved for implementation
**Relates to:** [2026-04-24-live-host-recruitment-design.md](2026-04-24-live-host-recruitment-design.md) (replaces that doc's "Custom form fields per campaign — fixed schema" non-goal)

## Goal

Replace the hardcoded 9-field recruitment application form with an admin-authored, per-campaign form built via a drag-and-drop UI. Admins can define pages, add fields of various types, set labels/placeholders/help text/required, and tag fields with semantic roles (name/email/phone/resume) so the hire flow keeps working.

## Context

The existing recruitment module (Live Host Recruitment, M1–M6) hardcodes the form fields in Blade templates and the database schema:

- `live_host_applicants` has dedicated columns for `full_name`, `email`, `phone`, `ic_number`, `location`, `platforms` (json), `experience_summary`, `motivation`, `resume_path`
- `resources/views/recruitment/show.blade.php` renders those fields literally
- `ApplyRequest` validates a fixed rule set

Admin wants full control over what's asked per campaign — different platforms, different events, different levels of scrutiny. The original design explicitly deferred this to future work. That decision is being reversed now.

## Approach — pure JSON rewrite

All applicant answers live in an `applicants.form_data` JSON column. Campaign defines the schema. Existing columns are dropped (except `email`, which stays for DB-level dedupe + indexing). Migration backfills existing records by mapping old columns into the new JSON shape.

This approach was chosen over "hybrid mapping" and "additive-only" for cleanliness and because the admin explicitly wants full customizability of core fields.

## Data Model

### `live_host_recruitment_campaigns`

Add column:
- `form_schema` json — **NOT NULL** after migration. Stores the field layout for this campaign.

### `live_host_applicants`

Add columns:
- `form_data` json — **NOT NULL** after migration. Stores the applicant's answers keyed by field id.
- `form_schema_snapshot` json — **NOT NULL** after migration. Copy of the schema at the moment of submission, so later schema edits don't rewrite history for that applicant.

Keep column:
- `email` — remains as a first-class indexed, unique-per-campaign column. Populated automatically on insert from the form_data value of whichever field is tagged `role: "email"`. Used for dedupe, uniqueness, and fast admin filtering.

Drop columns (from the old hardcoded schema):
- `full_name`
- `phone`
- `ic_number`
- `location`
- `platforms`
- `experience_summary`
- `motivation`
- `resume_path`

All previously stored in those columns ends up inside `form_data`.

### `form_schema` JSON shape

```json
{
  "version": 1,
  "pages": [
    {
      "id": "page-1",
      "title": "About you",
      "fields": [
        {
          "id": "f_name",
          "type": "text",
          "label": "Full name",
          "placeholder": "Your full name",
          "help_text": null,
          "required": true,
          "role": "name"
        },
        {
          "id": "f_email",
          "type": "email",
          "label": "Email",
          "placeholder": "you@example.com",
          "help_text": null,
          "required": true,
          "role": "email"
        },
        {
          "id": "f_phone",
          "type": "phone",
          "label": "Phone",
          "placeholder": "60123456789",
          "help_text": null,
          "required": true,
          "role": "phone"
        },
        {
          "id": "f_platforms",
          "type": "checkbox_group",
          "label": "Platforms",
          "help_text": "Pick at least one.",
          "required": true,
          "options": [
            {"value": "tiktok", "label": "TikTok"},
            {"value": "shopee", "label": "Shopee"},
            {"value": "facebook", "label": "Facebook"}
          ]
        }
      ]
    },
    {
      "id": "page-2",
      "title": "Your story",
      "fields": [
        {
          "id": "b_heading_1",
          "type": "heading",
          "text": "Tell us a bit about yourself"
        },
        {
          "id": "b_para_1",
          "type": "paragraph",
          "text": "We read every application. Keep it short and honest."
        },
        {
          "id": "f_experience",
          "type": "textarea",
          "label": "Experience",
          "rows": 4,
          "required": false
        },
        {
          "id": "f_resume",
          "type": "file",
          "label": "Resume",
          "accept": ["pdf", "doc", "docx"],
          "max_size_kb": 5120,
          "required": false,
          "role": "resume"
        }
      ]
    }
  ]
}
```

### `form_data` JSON shape (applicant answers)

```json
{
  "f_name": "Ahmad Rahman",
  "f_email": "ahmad@example.com",
  "f_phone": "60123456789",
  "f_platforms": ["tiktok", "shopee"],
  "f_experience": "5 years hosting TikTok Live sessions…",
  "f_resume": "recruitment/resumes/abc123.pdf"
}
```

- Checkbox groups store an array of selected values.
- Single-choice selects/radios store a string.
- Dates/datetimes store ISO 8601 strings.
- Files store the storage path relative to the `local` disk.
- Headings/paragraphs have no corresponding entry in form_data (display-only).

## Field Types (v1)

**Text**
- `text` — short single-line
- `textarea` — multi-line (configurable rows)
- `email` — email validation
- `phone` — loose phone regex
- `number` — numeric
- `url` — URL validation

**Choice**
- `select` — dropdown (single)
- `radio` — radio group (single)
- `checkbox_group` — multi-select checkboxes

**Other**
- `file` — file upload (accept + max_size_kb)
- `date` — date picker
- `datetime` — date + time picker

**Display-only (no data)**
- `heading` — section heading (`text` only)
- `paragraph` — explanatory copy (`text` only, plain)

## Roles (semantic tags)

Fields may carry an optional `role`:
- `name` — resolves to User.name at hire
- `email` — resolves to User.email at hire, indexed/unique at DB level
- `phone` — resolves to User.phone at hire
- `resume` — indicates the primary resume file for download links

All roles are optional except `email` (enforced by validation at campaign save — can't save a schema without a field tagged `role: "email"`). If admin tries to remove the email-tagged field or untag it, the campaign save is blocked with an inline error.

## Admin: Form Builder UI

**Placement:** Inside the campaign edit page (`/livehost/recruitment/campaigns/{id}/edit`), below the stage editor. Opens as a new tab labelled "Application form".

**Layout:** 3 panels on desktop, stacked on mobile.

```
┌─────────┬───────────────────────────────┬──────────────┐
│ PAGES   │   Canvas (selected page)      │ FIELD SETTINGS│
│ ▸ About │   ┌─────────────────────────┐ │ Label        │
│ ▸ Story │   │ Full name *             │ │ [Full name ] │
│ ▸ + Add │   │ [text input preview]    │ │ Placeholder  │
│         │   └─────────────────────────┘ │ Help text    │
│         │   ┌─────────────────────────┐ │ ☐ Required   │
│         │   │ Email *                 │ │ Role [email] │
│         │   └─────────────────────────┘ │              │
│         │   [+ Add field ▾]             │              │
└─────────┴───────────────────────────────┴──────────────┘
```

**Left rail — Pages**
- Ordered list, drag-to-reorder, click to select
- Row menu: Rename, Duplicate, Delete (blocked if page has fields that are in use by applicants)
- "Add page" at bottom

**Canvas — Fields**
- Compact field previews (real label, real input type, non-interactive)
- Drag to reorder within page
- Click a field to select (highlights, opens settings)
- "+ Add field" opens a type picker grouped by Text / Choice / Other / Display
- Delete button per field (with guard)

**Right rail — Field settings**
Common:
- Label (required, text)
- Placeholder
- Help text
- Required (checkbox)
- Role dropdown (None / Name / Email / Phone / Resume) — visible when the field type matches

Choice-specific:
- Options: list of `{label, value}`, reorderable, add/remove. Values must be unique within a field.

File-specific:
- Accepted extensions (multi-select: pdf, doc, docx, png, jpg, …)
- Max size in KB

Textarea-specific:
- Rows (default 4)

**Preview button** (top right of builder) → opens the public form for this campaign in a new tab.

**Save button** is in the campaign form itself (shared save with other campaign settings).

**Guards:**
- Blank label not allowed on any data field
- At least one page with at least one data field required
- Exactly one field must have `role: "email"` (enforced on save)
- Can't change a field's `type` after applicants exist (shown as a disabled dropdown with a tooltip)
- Can't delete a field that has submitted data — shown as disabled with count: "Used by 3 applicants — cannot delete"
- Rename / placeholder / help text / required edits are always allowed

## Public Form Rendering

`resources/views/recruitment/show.blade.php` becomes schema-driven:

- Controller loads `campaign.form_schema`
- Template walks `pages[].fields[]`
- Each field renders with its type's component (text input, textarea, select, radio, checkbox, file, date, datetime)
- Headings/paragraphs render as `<h2>`/`<p>` in place
- Page titles render as section dividers between field groups
- **Layout stays single-scroll** (no next/back stepped flow) — per user's preference to keep v1 tight
- Styling reuses the existing modern Geist-based look from the v2 public form redesign
- Validation errors show under each field (keyed by field id, e.g. `form.f_email.email`)
- `old('f_email')` repopulates values after failed submits

## Submission

`PublicRecruitmentController::apply` changes:

1. Load campaign + schema
2. Build a dynamic validation ruleset from the schema:
   - Required-ness per field
   - Type-based rules (email, phone, numeric, url, date, etc.)
   - Choice fields: `in:option,option,…`
   - File: mimes + max_size
3. Resolve the email-role field id, read its value from validated input
4. Dedupe: `live_host_applicants.where('campaign_id', $id).where('email', $email).exists()` (column still exists)
5. Store uploaded files to `recruitment/resumes/…`, replace the request value with the stored path
6. Insert applicant:
   - `form_data` = validated values keyed by field id
   - `form_schema_snapshot` = copy of current campaign schema
   - `email` column = email-role value (auto-populated via model observer for robustness)
   - `applied_at`, `current_stage_id` = first stage, `status` = active
7. Write history row, queue confirmation email, redirect to thank-you

**Model observer:** `LiveHostApplicant::saving` pulls the email-role field's value from `form_data` and writes it to the `email` column. Keeps the column in sync automatically; nothing elsewhere in the app needs to care about writing to it directly.

## Admin: Applicant Review Changes

**Kanban card:**
- Name = value of the `role: "name"` field, falls back to email
- Applicant number, rating, applied_at unchanged (metadata, not form data)
- Platforms chips = value of the first `checkbox_group` field tagged `role: "platforms"` (if present); hide chips if no such field

**Applicant detail:**
- Application tab iterates `applicant.form_schema_snapshot` and renders each field's label + the value from `form_data`
- Text / email / phone / URL / number / date / datetime: render as formatted value
- Select / radio: look up the selected option label (not raw value)
- Checkbox group: render each selected option label as a chip
- File: render as a download link via signed URL
- Headings / paragraphs: skip — they're builder display only

**Hire flow:**
- Read email from `form_data` via `role: "email"` (already guaranteed present — campaign save prevents missing email role)
- Read name from `form_data` via `role: "name"` if present, else the email's local-part
- Read phone from `form_data` via `role: "phone"` if present, else null
- Insert User as before

## Migration Path

One migration file. Safe for MySQL + SQLite via `DB::getDriverName()` branching for the destructive column-drop step.

Order of operations:

1. Add nullable `form_schema` to campaigns
2. Add nullable `form_data` and `form_schema_snapshot` to applicants
3. Data step (inside a transaction):
   - For each campaign with `form_schema IS NULL`: set it to a constant default schema matching today's 9-field layout (exact field ids specified below)
   - For each applicant: build `form_data` from the 9 columns using those same field ids; set `form_schema_snapshot` to the same default schema
4. Enforce NOT NULL on the three new columns
5. Drop the 8 old columns (`full_name`, `phone`, `ic_number`, `location`, `platforms`, `experience_summary`, `motivation`, `resume_path`) — keep `email`
6. Add unique composite index `(campaign_id, email)` if not already present (it exists)

**Default schema field IDs used in the backfill:**
- `f_name` — text, role name
- `f_email` — email, role email
- `f_phone` — phone, role phone
- `f_ic_number` — text
- `f_location` — text
- `f_platforms` — checkbox_group (tiktok/shopee/facebook)
- `f_experience` — textarea
- `f_motivation` — textarea
- `f_resume` — file, role resume

## Code Touchpoints

Files that change as part of this work (informational — implementation plan will enumerate):

- `app/Models/LiveHostRecruitmentCampaign.php` — casts form_schema, helper methods to resolve roles
- `app/Models/LiveHostApplicant.php` — casts form_data + snapshot, `saving` observer to mirror email column, accessors for role-tagged values
- `app/Http/Controllers/LiveHost/PublicRecruitmentController.php` — schema-driven validation + submission
- `app/Http/Requests/LiveHost/Recruitment/ApplyRequest.php` — rewrite to generate rules dynamically
- `app/Http/Controllers/LiveHost/RecruitmentApplicantController.php` — read from form_data + snapshot; hire resolves by role
- `app/Http/Controllers/LiveHost/RecruitmentCampaignController.php` — validate schema on save, seed default on create
- `resources/views/recruitment/show.blade.php` — schema-walking renderer with per-type blade partials in `recruitment/fields/*.blade.php`
- `resources/js/livehost/pages/recruitment/applicants/Index.jsx` — display updates (name/platforms from roles)
- `resources/js/livehost/pages/recruitment/applicants/Show.jsx` — schema-driven field list
- `resources/js/livehost/pages/recruitment/campaigns/Edit.jsx` — add "Application form" tab hosting the builder
- New React components for the form builder:
  - `resources/js/livehost/components/form-builder/FormBuilder.jsx`
  - `resources/js/livehost/components/form-builder/PageList.jsx`
  - `resources/js/livehost/components/form-builder/FieldCanvas.jsx`
  - `resources/js/livehost/components/form-builder/FieldSettings.jsx`
  - `resources/js/livehost/components/form-builder/fieldTypes.js` (type registry)
- `app/Services/Recruitment/FormSchemaValidator.php` — validates schema structure on campaign save
- `app/Services/Recruitment/FormRuleBuilder.php` — turns a schema into Laravel validation rules
- Factories + seeders — default schema + form_data
- Tests — rewrite the ~55 recruitment tests to use form_data

## Testing

- Unit: `FormRuleBuilder` — every field type generates the right rules
- Unit: `FormSchemaValidator` — rejects invalid schemas (no email role, duplicate field ids, blank labels, …)
- Feature: public submission via each field type + validation errors
- Feature: dedupe still works with email column mirroring
- Feature: hire reads form_data correctly; shows proper 422 if email role value is empty
- Feature: applicant detail renders from `form_schema_snapshot`, not current campaign schema (verify label drift)
- Feature: campaign save rejects schema without email role
- Browser: admin builds a form (add page → add fields → configure → save), candidate submits, admin reviews — end-to-end

## Out of Scope for v1

- Conditional logic (show/hide by answer)
- Stepped wizard flow (multi-page is single-scroll, not next/back)
- Form templates / library (copy schema between campaigns)
- Drag-and-drop fields between pages (reorder within page only)
- Import/export schema JSON
- Custom field validation (regex, min/max length, min/max value)
- Rich text editor for paragraph blocks (plain text only)
- Field archiving — destructive deletes are simply blocked once applicants exist
- Translation / locale support for schemas
- Signatures, rating scales, address autocomplete, any other exotic field types
- Ability to edit an applicant's submitted answers

## Migration Compatibility

Follows the MySQL + SQLite dual-driver rule from `CLAUDE.md`. Column drops use `DB::getDriverName()` to branch:

```php
if (DB::getDriverName() === 'mysql') {
    Schema::table('live_host_applicants', fn ($t) =>
        $t->dropColumn(['full_name', 'phone', 'ic_number', 'location',
                        'platforms', 'experience_summary', 'motivation', 'resume_path']));
} else {
    // SQLite: drop columns one at a time inside a single Schema::table call
    Schema::table('live_host_applicants', function ($t) {
        $t->dropColumn(['full_name', 'phone', 'ic_number', 'location',
                        'platforms', 'experience_summary', 'motivation', 'resume_path']);
    });
}
```

SQLite 3.35+ supports multi-column drops, so the branching is largely defensive; the migration is tested on both drivers.

## Open Questions (resolve during implementation)

1. Do we keep a default schema constant in PHP or seed it via a migration-only array? (Lean: constant, so admin-built campaigns reuse it if needed.)
2. Signed URL lifetime for file downloads on the admin side — 15 min default or longer?
3. For file-type fields, what happens when admin edits the schema to remove a file field after applicants uploaded? (Lean: block the delete, surface "used by N applicants".)
