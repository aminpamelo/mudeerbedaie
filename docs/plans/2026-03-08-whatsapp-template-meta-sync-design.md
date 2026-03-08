# WhatsApp Template Meta Sync — Design Document

**Date:** 2026-03-08
**Status:** Approved

## Problem

Locally created WhatsApp templates are not submitted to Meta for approval. Templates must be verified by Meta before they can be used for sending messages via WABA. Currently the system only syncs FROM Meta, not TO Meta.

## Solution

Extend `TemplateService` with 3 new methods (create, update, delete on Meta) and add corresponding UI actions in the Volt component.

## API Endpoints (Meta Graph API)

### Create Template
- **Method:** `POST /{wabaId}/message_templates`
- **Body:** `name`, `language`, `category` (uppercase), `components`
- **Response:** `{ id, status, category }`

### Update Template
- **Method:** `POST /{meta_template_id}`
- **Body:** `components`, optionally `category`
- **Response:** `{ success, id, name, category }`
- **Restriction:** Only templates with a `meta_template_id` can be updated

### Delete Template
- **Method:** `DELETE /{wabaId}/message_templates?name={name}`
- **Response:** `{ success: true }`
- **Note:** Deletes ALL language versions of the template on Meta

## TemplateService Changes

### `submitToMeta(WhatsAppTemplate $template): void`
1. Validate Meta credentials exist (`meta_waba_id`, `meta_access_token`)
2. Map local components to Meta format (add `format: "TEXT"` for HEADER text components)
3. POST to `/{wabaId}/message_templates` with `name`, `language`, `category` (uppercase), `components`
4. On success: store `meta_template_id`, set `status` to `PENDING`, update `last_synced_at`
5. On failure: throw RuntimeException with Meta's error message

### `updateOnMeta(WhatsAppTemplate $template): void`
1. Validate template has `meta_template_id`
2. Validate Meta credentials exist
3. Map components to Meta format
4. POST to `/{meta_template_id}` with `components` and `category`
5. On success: update `last_synced_at`
6. On failure: throw RuntimeException with Meta's error message

### `deleteFromMeta(WhatsAppTemplate $template): void`
1. Validate template has `meta_template_id`
2. Validate Meta credentials exist
3. DELETE `/{wabaId}/message_templates?name={name}`
4. On success: delete local record
5. On failure: throw RuntimeException with Meta's error message

## Component Format Mapping

Local storage format matches Meta's format closely:
```json
[
  {"type": "HEADER", "text": "Hello {{1}}"},
  {"type": "BODY", "text": "Your order {{1}} is ready"},
  {"type": "FOOTER", "text": "Thank you"}
]
```

Meta requires HEADER to include a `format` field:
```json
{"type": "HEADER", "format": "TEXT", "text": "Hello {{1}}"}
```

A private `mapComponentsForMeta()` helper handles this transformation.

## UI Changes (whatsapp-templates.blade.php)

### New Volt Methods
- `submitToMeta(int $templateId)` — calls `TemplateService::submitToMeta()`
- `updateOnMeta(int $templateId)` — calls `TemplateService::updateOnMeta()`
- `deleteFromMeta(int $templateId)` — calls `TemplateService::deleteFromMeta()`

### New Action Buttons (per template row)
| Condition | Button | Action |
|-----------|--------|--------|
| No `meta_template_id` | "Submit to Meta" | Calls `submitToMeta` |
| Has `meta_template_id` | "Update on Meta" | Calls `updateOnMeta` |
| Has `meta_template_id` | "Delete from Meta" | Calls `deleteFromMeta` with confirmation modal |

### Feedback
- Success/error flash messages via Flux toast/banner
- Status badge auto-updates after submission (shows PENDING)
- Loading states on buttons during API calls

## Status Check
Manual only — user clicks existing "Sync from Meta" to check approval status updates (PENDING → APPROVED/REJECTED).

## Error Handling
- Validate Meta credentials before any API call
- Surface Meta API error messages to user (e.g., "template name already exists")
- No automatic retry — user retries manually
- Common Meta errors: code 100 (invalid parameter), 131009 (invalid value), 80008 (rate limit)

## Files to Modify
1. `app/Services/WhatsApp/TemplateService.php` — Add 3 methods + helper
2. `resources/views/livewire/admin/whatsapp-templates.blade.php` — Add UI actions
3. `tests/Feature/` — New test file for Meta sync operations
