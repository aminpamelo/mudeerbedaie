# Funnel Email Templates - Design Document

**Date:** 2026-03-07
**Status:** Approved

## Problem

The funnel automation "Send Email" action only supports inline subject/content entry. Users need the ability to create reusable email templates (both text and visual HTML) and select them when configuring Send Email actions.

## Decisions

- **Approach:** New `FunnelEmailTemplate` model (separate from notification templates)
- **Management:** Global templates page, reusable across all funnels
- **Editor:** Both plain text and visual HTML builder (reusing existing React email builder)
- **Override:** Template provides content (read-only), subject can be overridden per automation action

## Architecture

### 1. Database Schema

**New table: `funnel_email_templates`**

| Column | Type | Description |
|--------|------|-------------|
| id | bigint PK | Auto-increment |
| name | string | Display name (e.g., "Purchase Confirmation") |
| slug | string unique | URL-safe identifier |
| subject | string nullable | Default email subject line |
| content | longText nullable | Plain text content with merge tags |
| design_json | json nullable | Visual builder design data |
| html_content | longText nullable | Compiled HTML from visual builder |
| editor_type | string default:'text' | 'text' or 'visual' |
| category | string nullable | Grouping (e.g., 'purchase', 'cart', 'welcome') |
| is_active | boolean default:true | Enable/disable template |
| created_at | timestamp | |
| updated_at | timestamp | |
| deleted_at | timestamp nullable | Soft delete |

### 2. Model: `FunnelEmailTemplate`

```php
class FunnelEmailTemplate extends Model
{
    use HasFactory, SoftDeletes;

    // Key methods:
    // - isVisualEditor() / isTextEditor()
    // - getEffectiveContent() -> returns html_content or content
    // - scopeActive()
    // - scopeByCategory()
    // - getCategories() -> static list of categories
}
```

### 3. Backend Components

**API Controller: `FunnelEmailTemplateController`**
- `GET /api/funnel-email-templates` - List all (for dropdown in automation builder)
- `GET /api/funnel-email-templates/{id}` - Get single template
- `POST /api/funnel-email-templates` - Create
- `PUT /api/funnel-email-templates/{id}` - Update
- `DELETE /api/funnel-email-templates/{id}` - Soft delete
- `POST /api/funnel-email-templates/{id}/duplicate` - Clone

**Livewire Volt Pages:**
- `admin/funnel-email-templates` - Global management page (list, create, edit, delete, preview, toggle active)
- `admin/funnel-email-template-builder/{template}` - Visual builder page (reuses existing React email builder with funnel merge tags)

**Service Update: `FunnelAutomationService::executeSendEmail()`**
- Check for `template_id` in action config
- If present, load `FunnelEmailTemplate`, use its content
- Apply subject override if provided in action config
- For visual templates, send as HTML email (not `Mail::raw()`)
- For text templates, continue using `Mail::raw()`

### 4. Frontend Changes

**Automation Builder `NodeConfigPanel` (Send Email section):**

```
+-------------------------------------+
| Email Source:                        |
| (*) Use Template  ( ) Write Custom   |
|                                      |
| Template: [Select template v]       |
|   > Purchase Confirmation            |
|   > Welcome Email                    |
|   > Cart Abandonment Reminder        |
|                                      |
| Subject: [editable, pre-filled]     |
| [Insert variable]                    |
|                                      |
| Content Preview:                     |
| +----------------------------------+|
| | (read-only preview of template)  ||
| | Hi {{contact.first_name}},       ||
| | Thank you for your order...      ||
| +----------------------------------+|
|                                      |
| [Edit Template ->]                   |
+-------------------------------------+
```

**Behavior:**
- Radio toggle: "Use Template" vs "Write Custom" (default: Write Custom for backward compatibility)
- When "Use Template" selected: show template dropdown, read-only content preview, editable subject
- When "Write Custom" selected: show current inline subject + content fields (no change)
- Template dropdown fetches from `GET /api/funnel-email-templates?active=true`
- Config stored as: `{ email_source: 'template', template_id: 123, subject: 'override...' }` or `{ email_source: 'custom', subject: '...', content: '...' }`

**Global Templates Management Page:**
- Located under CRM & Automation sidebar section
- Table with columns: Name, Category, Editor Type, Status, Actions
- Create/Edit modal or page with: name, slug, category, subject, content (text editor with merge tag picker), toggle for visual builder
- Visual builder link opens the dedicated builder page
- Preview button with sample data
- Duplicate, delete, toggle active actions

### 5. Merge Tags

Funnel email templates use the existing funnel merge tag system (MergeTagEngine):

**Contact:** `{{contact.name}}`, `{{contact.first_name}}`, `{{contact.email}}`, `{{contact.phone}}`
**Order:** `{{order.number}}`, `{{order.total}}`, `{{order.date}}`, `{{order.items_list}}`
**Payment:** `{{payment.method}}`, `{{payment.status}}`
**Funnel:** `{{funnel.name}}`, `{{funnel.url}}`
**System:** `{{current_date}}`, `{{current_time}}`, `{{company_name}}`, `{{company_email}}`

### 6. Email Sending Changes

Current flow (`Mail::raw()`):
```
action_config.content -> MergeTagEngine -> Mail::raw()
```

New flow with template:
```
FunnelEmailTemplate.getEffectiveContent() -> MergeTagEngine -> Mail::send() or Mail::raw()
```

- If template is visual (html_content present): use `Mail::html()` for HTML email
- If template is text: use `Mail::raw()` for plain text
- Subject: use action_config.subject override if present, else template.subject

### 7. Data Flow

```
User creates template (global page)
    -> FunnelEmailTemplate stored in DB

User configures Send Email action in automation builder
    -> Selects template from dropdown (API fetch)
    -> Optionally overrides subject
    -> Saves: { email_source: 'template', template_id: X, subject: 'override' }

Automation triggers (e.g., purchase_completed)
    -> FunnelAutomationService::executeSendEmail()
    -> Loads FunnelEmailTemplate by template_id
    -> Gets effective content (HTML or text)
    -> Resolves merge tags via MergeTagEngine
    -> Sends email (HTML or plain text)
    -> Logs result
```

## Files to Create/Modify

### New Files
1. `database/migrations/XXXX_create_funnel_email_templates_table.php`
2. `app/Models/FunnelEmailTemplate.php`
3. `app/Http/Controllers/Api/V1/FunnelEmailTemplateController.php`
4. `resources/views/livewire/admin/funnel-email-templates.blade.php` (management page)
5. `resources/views/livewire/admin/funnel-email-template-builder.blade.php` (visual builder)
6. `tests/Feature/FunnelEmailTemplateTest.php`

### Modified Files
1. `resources/js/funnel-builder/components/FunnelAutomationBuilder.jsx` - Add template selection UI in NodeConfigPanel
2. `resources/js/funnel-builder/types/funnel-automation-types.js` - Update SEND_EMAIL config defaults
3. `app/Services/Funnel/FunnelAutomationService.php` - Update `executeSendEmail()` for template support
4. `routes/api.php` - Add funnel email template API routes
5. `routes/web.php` - Add admin management page routes

## Testing Strategy

- Feature tests for CRUD API endpoints
- Feature test for template selection in automation execution
- Feature test for subject override behavior
- Feature test for HTML vs text email sending
- Test backward compatibility (existing automations with inline content still work)
