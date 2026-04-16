# Broadcast Email Template Integration

**Date:** 2026-04-16
**Status:** Approved

## Goal

Integrate the existing visual email builder and Funnel Email Template library into the Broadcast system so users can create professional email campaigns using templates or the drag-and-drop builder, rather than only raw HTML.

## Key Decisions

- Users can **select an existing Funnel Email Template** OR **build from scratch** in the visual editor
- Template content is **copied** into the broadcast (no live linking — edits don't affect originals)
- Step 3 of the broadcast wizard gets enhanced with a **template picker + "Open Builder" button**
- Full-page React email builder (Unlayer) is reused — same component as notification/funnel templates

## Architecture

### Database Changes

Add columns to `broadcasts` table:

| Column | Type | Description |
|--------|------|-------------|
| `design_json` | longText, nullable | Unlayer JSON design data |
| `html_content` | longText, nullable | Compiled HTML from visual builder |
| `editor_type` | string, default 'text' | 'text' (legacy) or 'visual' (builder) |

### Modified Components

#### 1. broadcast-create.blade.php (Step 3 Enhancement)

Current Step 3 has: from_name, from_email, reply_to_email, subject, preview_text, content (textarea).

Enhanced Step 3:
- Keep sender fields (from_name, from_email, reply_to_email, subject, preview_text)
- Add **template picker section** below sender fields:
  - Searchable grid of Funnel Email Templates with name and category badge
  - Clicking a template copies its `subject`, `html_content`, and `design_json` into the broadcast
  - "Selected" state indicator on chosen template
- Add **action buttons**:
  - "Open Builder" — navigates to full-page React email editor
  - "Start from Scratch" — opens builder with blank canvas
- Add **content preview** — rendered HTML preview of the current broadcast content
- Keep legacy textarea as fallback (shown when editor_type is 'text')

#### 2. New Route: Broadcast Email Builder

Route: `GET /admin/crm/broadcasts/{broadcast}/builder`

New Volt component: `resources/views/livewire/crm/broadcast-builder.blade.php`
- Wraps the existing React email builder component
- Loads `design_json` from Broadcast model (or from selected template)
- On save: compiles HTML, stores `design_json` + `html_content` back to Broadcast
- Redirects back to broadcast create/edit Step 3

#### 3. SendBroadcastEmail Job (Minor Update)

- When `editor_type` is 'visual', use `html_content` for the email body
- When `editor_type` is 'text', use existing `content` field (backward compatible)

### Data Flow

```
User creates broadcast
  → Step 1: Info (name, type)
  → Step 2: Contacts (audiences, students)
  → Step 3: Content
      ├── Pick existing Funnel Email Template
      │     → Copies subject + html_content + design_json into broadcast
      │     → Optionally "Edit in Builder" → full-page editor → saves back
      ├── "Start from Scratch" → full-page editor → saves back
      └── Legacy: type content manually (plain HTML, editor_type stays 'text')
  → Step 4: Review & Send
```

### Files to Create/Modify

| Action | File | Purpose |
|--------|------|---------|
| Create | `database/migrations/xxxx_add_email_builder_fields_to_broadcasts.php` | Add design_json, html_content, editor_type |
| Modify | `app/Models/Broadcast.php` | Add new columns to fillable, cast design_json |
| Create | `resources/views/livewire/crm/broadcast-builder.blade.php` | Full-page email builder for broadcasts |
| Modify | `resources/views/livewire/crm/broadcast-create.blade.php` | Enhance Step 3 with template picker + builder button |
| Modify | `resources/js/react-email-builder.jsx` | Support broadcast context (placeholders, save endpoint) |
| Modify | `routes/web.php` | Add broadcast builder route |
| Modify | `app/Jobs/SendBroadcastEmail.php` | Use html_content when editor_type is visual |

### No Changes To

- `FunnelEmailTemplate` model (read-only from broadcast context)
- `NotificationTemplate` model (not involved)
- Existing email builder React component structure (reused as-is, just new route/context)

## Merge Tags for Broadcasts

Existing broadcast merge tags remain: `{{name}}`, `{{email}}`, `{{student_id}}`

These will be available in the visual builder as merge tag options.
