# Certificate WABA Sending with Template Management

**Date**: 2026-03-08
**Status**: Approved

## Problem

The certificate send modal currently supports Email and WhatsApp (via Onsend) delivery channels. WABA (Meta WhatsApp Business API) requires template messages for first contact outside the 24h service window. We need to:

1. Add WABA as a delivery sub-option under WhatsApp
2. Allow creating WABA templates from the certificate page and submitting to Meta for approval
3. Auto-map template variables to certificate context (student name, certificate name, etc.)

## Design

### 1. Delivery Channel UI Changes

**Current**: Radio options — Email | WhatsApp | Both
**New**: When WhatsApp or Both is selected, show a sub-choice:
- **Onsend** — existing free-form text + PDF (unchanged)
- **WABA (Official)** — template-based sending with PDF header

When WABA is selected:
- Template picker dropdown appears (approved templates only)
- Template preview with auto-filled variables shown
- Custom message textarea is hidden (template body IS the message)
- PDF attaches as document header component

### 2. Certificate WABA Template Management Section

Add a "WABA Templates" section on the certificate page (below existing templates section or as a sub-tab) with:

- **Template List**: Name, language, status (Pending/Approved/Rejected), last synced
- **Create Template** button → form:
  - Template name (auto-prefixed, e.g. `certificate_delivery_*`)
  - Language (default: `ms` for Malay, support `en`)
  - Category: `UTILITY` (fixed for transactional)
  - Header: Document type (PDF — auto-configured for certificates)
  - Body: Text with numbered variables `{{1}}`, `{{2}}`, etc.
  - Variable mapping (configured once, saved to DB):
    - `{{1}}` → `student_name`
    - `{{2}}` → `certificate_name`
    - `{{3}}` → `certificate_number` (optional)
  - Footer: Optional text
- **Submit to Meta** via Graph API `POST /{waba_id}/message_templates`
- **Sync** button to pull latest status from Meta
- **Status badges**: Pending → Approved / Rejected

### 3. Variable Mapping (Configure Once)

When a template is created, user maps each `{{N}}` variable to a certificate context field:

Available context fields:
- `student_name` — Student's full name
- `certificate_name` — Certificate template name
- `certificate_number` — Issue certificate number (e.g. CERT-2026-0008)
- `class_name` — Class title
- `course_name` — Course name
- `issue_date` — Date the certificate was issued

Mappings stored on the `WhatsAppTemplate` model (in `components` JSON or a new `variable_mappings` column).

### 4. New Job: `SendCertificateWabaJob`

Queued job that:
1. Loads certificate issue with student + certificate relationships
2. Resolves variable mappings from certificate context
3. Builds Meta template components:
   - Header: `{ type: "document", document: { link: pdf_url, filename: cert_filename } }`
   - Body parameters: `[{ type: "text", text: resolved_value }, ...]`
4. Calls `MetaCloudProvider::sendTemplate(phone, templateName, language, components)`
5. Stores outbound message in `WhatsAppMessage` (type: `template`)
6. Logs `'sent_waba'` action to `CertificateLog`

### 5. Data Model Changes

**`WhatsAppTemplate` model** — add:
- `variable_mappings` (JSON, nullable) — maps `{{N}}` to context field names
- `is_certificate_template` (boolean, default false) — filter for certificate page
- `submitted_by_user_id` (nullable FK) — who created/submitted it

**New migration**: Add columns to `whatsapp_templates` table.

### 6. Meta Graph API Integration for Template Creation

**Submit template**: `POST https://graph.facebook.com/{api_version}/{waba_id}/message_templates`

Payload:
```json
{
  "name": "certificate_delivery_quran",
  "language": "ms",
  "category": "UTILITY",
  "components": [
    { "type": "HEADER", "format": "DOCUMENT", "example": { "header_handle": ["..."] } },
    { "type": "BODY", "text": "Assalamualaikum {{1}}, Tahniah! Sijil anda ({{2}}) telah dikeluarkan." },
    { "type": "FOOTER", "text": "Kelasify" }
  ]
}
```

Add `createTemplate()` method to `MetaCloudProvider` or `TemplateService`.

### 7. Flow Summary

```
Certificate Page
  ├── WABA Templates Section
  │   ├── List synced templates (filtered: is_certificate_template)
  │   ├── Create → Submit to Meta API → Status: Pending
  │   └── Sync → Pull latest status from Meta
  │
  └── Send Modal
      ├── Channel: Email / WhatsApp / Both
      ├── Sub-option (when WhatsApp): Onsend / WABA
      ├── Template picker (when WABA, approved only)
      ├── Variable preview (auto-filled from certificate context)
      └── Send → SendCertificateWabaJob dispatched
```

## Files to Create/Modify

**New files:**
- `app/Jobs/SendCertificateWabaJob.php` — WABA sending job
- Migration: add columns to `whatsapp_templates`

**Modified files:**
- `resources/views/livewire/admin/certificates/class-certificate-management.blade.php` — UI changes (sub-option, template picker, template management section)
- `app/Services/WhatsApp/TemplateService.php` — add `createTemplate()` method
- `app/Services/WhatsApp/MetaCloudProvider.php` — add template creation API call if needed
- `app/Models/WhatsAppTemplate.php` — new columns, scopes
- `tests/Feature/CertificateSendTest.php` — update tests for WABA channel
