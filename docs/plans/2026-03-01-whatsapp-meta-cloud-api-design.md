# WhatsApp Meta Cloud API Integration — Design Document

**Date:** 2026-03-01
**Status:** Approved
**Author:** Claude + Ahmad Amin

---

## Problem

The current WhatsApp integration uses Onsend.io, an unofficial WhatsApp gateway. This carries:
- **High ban risk** — Meta actively detects and bans accounts using unofficial APIs
- **No delivery receipts** — no way to know if messages were delivered/read
- **One-way only** — cannot receive or reply to incoming messages
- **Anti-ban overhead** — complex delay/batching logic required to avoid detection

## Goal

Replace Onsend with the **official Meta WhatsApp Cloud API** via a provider-switchable architecture. Add 2-way messaging with an admin chat inbox. Keep Onsend as a fallback during transition.

## Requirements

- **Primary**: Reduce ban risk by migrating to official Meta Cloud API
- **Provider switching**: Admin can toggle between Onsend and Meta via settings
- **All message types**: Session reminders, certificates (PDF), enrollments, CRM workflows
- **Delivery tracking**: Real-time delivery/read receipts via Meta webhooks
- **2-way messaging**: Admin chat inbox for receiving and replying to student messages
- **Gradual migration**: Keep Onsend available as fallback

---

## Architecture

### 1. Provider Pattern

```
WhatsAppService (public API — unchanged for callers)
    └── WhatsAppManager (resolves provider from config)
        ├── OnsendProvider (wraps current Onsend logic)
        └── MetaCloudProvider (new — Meta Cloud API)

Both implement WhatsAppProviderInterface:
  - send(phone, message): array
  - sendImage(phone, imageUrl, caption?): array
  - sendDocument(phone, docUrl, mimeType, filename?): array
  - sendTemplate(phone, templateName, language, components): array  // Meta only
  - checkStatus(): array
  - isConfigured(): bool
```

**Key constraint**: Return shapes from all providers must match: `['success' => bool, 'message_id' => ?string, 'message' => string]` or `['success' => false, 'error' => string]`. This ensures zero changes to existing jobs.

**Anti-ban logic**: Stays in `WhatsAppService` but is conditionally applied — enabled for Onsend, disabled for Meta (not needed with official API).

### 2. Meta Cloud API Integration

**Endpoints used**:
- `POST /v21.0/{phone_number_id}/messages` — send all message types
- `GET /v21.0/{phone_number_id}/messages` — (webhook verification)
- `POST /v21.0/{phone_number_id}/media` — upload media files
- `GET /v21.0/{waba_id}/message_templates` — sync approved templates

**Config (new DB settings, group: `whatsapp`)**:

| Key | Type | Description |
|-----|------|-------------|
| `whatsapp_provider` | string | `onsend` or `meta` |
| `meta_phone_number_id` | string | Meta phone number ID |
| `meta_access_token` | encrypted | System User permanent token |
| `meta_waba_id` | string | WhatsApp Business Account ID |
| `meta_app_secret` | encrypted | For webhook signature verification |
| `meta_verify_token` | string | For webhook handshake |
| `meta_api_version` | string | Default `v21.0` |

### 3. Webhook System

**Routes** (no auth middleware — verified by signature):
- `GET /api/whatsapp/webhook` — Meta verification handshake
- `POST /api/whatsapp/webhook` — Incoming events (delivery status + messages)

**Security**: Verify `X-Hub-Signature-256` header using `meta_app_secret`.

**Processing flow**:
1. Controller receives webhook, validates signature
2. Returns `200 OK` immediately
3. Dispatches `ProcessWhatsAppWebhookJob` to queue
4. Job processes: delivery status updates → `NotificationLog` + `WhatsAppMessage`; incoming messages → `WhatsAppConversation` + `WhatsAppMessage`

### 4. Database Schema

**New tables**:

```sql
-- whatsapp_conversations
id                       bigint PK
phone_number             varchar unique indexed
student_id               FK students nullable
contact_name             varchar nullable (from WhatsApp profile)
last_message_at          timestamp nullable
last_message_preview     varchar(255) nullable
unread_count             int default 0
is_service_window_open   boolean default false
service_window_expires_at timestamp nullable
status                   enum('active', 'archived') default 'active'
timestamps

-- whatsapp_messages
id                       bigint PK
conversation_id          FK whatsapp_conversations
direction                enum('inbound', 'outbound')
wamid                    varchar unique nullable (Meta message ID)
type                     varchar (text/image/document/audio/video/template/interactive)
body                     text nullable
media_url                varchar nullable
media_mime_type          varchar nullable
media_filename           varchar nullable
template_name            varchar nullable
status                   enum('pending', 'sent', 'delivered', 'read', 'failed')
status_updated_at        timestamp nullable
error_code               varchar nullable
error_message            text nullable
sent_by_user_id          FK users nullable
metadata                 json nullable
timestamps

-- whatsapp_templates (synced from Meta)
id                       bigint PK
name                     varchar
language                 varchar
category                 enum('marketing', 'utility', 'authentication')
status                   enum('APPROVED', 'PENDING', 'REJECTED')
components               json
meta_template_id         varchar nullable
last_synced_at           timestamp nullable
timestamps
```

### 5. Admin Chat Inbox (React Island)

**Pattern**: Same as POS, funnel builder, workflow builder — React app mounted inside Blade wrapper.

**Route**: `/admin/whatsapp-inbox`

**File structure**:
```
resources/js/whatsapp-inbox/
├── index.jsx
├── App.jsx
├── components/
│   ├── ConversationList.jsx
│   ├── ChatPanel.jsx
│   ├── MessageBubble.jsx
│   ├── ReplyInput.jsx
│   ├── TemplatePicker.jsx
│   ├── ConversationHeader.jsx
│   └── ServiceWindowBadge.jsx
└── styles/whatsapp-inbox.css
```

**API routes** (authenticated, admin-only):
```
GET    /api/admin/whatsapp/conversations
GET    /api/admin/whatsapp/conversations/{id}
POST   /api/admin/whatsapp/conversations/{id}/reply
POST   /api/admin/whatsapp/conversations/{id}/template
POST   /api/admin/whatsapp/conversations/{id}/media
POST   /api/admin/whatsapp/conversations/{id}/archive
GET    /api/admin/whatsapp/templates
POST   /api/admin/whatsapp/templates/sync
```

**Real-time**: Polling every 5 seconds via `setInterval`. Upgradeable to WebSockets later.

**Layout**: Two-panel (conversation list left, chat thread right). Clean, utilitarian design matching existing admin. Tailwind CSS v4.

### 6. Template Management

- Templates synced from Meta API → `whatsapp_templates` table
- Admin can view approved templates in settings
- Template picker modal in chat inbox for sending business-initiated messages
- Template parameter mapping for automated notifications (session reminders, certificates)

---

## Implementation Phases

| Phase | Scope | Estimated Effort |
|-------|-------|------------------|
| 1 | Provider interface + OnsendProvider refactor | 2-3 days |
| 2 | MetaCloudProvider + config + admin settings | 2-3 days |
| 3 | Webhook controller + delivery tracking | 1-2 days |
| 4 | Conversation + Message models + incoming messages | 1-2 days |
| 5 | Admin chat inbox (React) | 3-4 days |
| 6 | Template management + template picker | 1-2 days |
| 7 | CRM workflow handler migration | 1 day |
| 8 | Testing + production cutover | 2-3 days |

**After Phase 2**: Can switch to Meta for all outbound messages.
**Phase 5**: Can be built in parallel after Phase 3.

---

## Meta Setup Requirements (One-Time)

1. Create Meta Developer App at developers.facebook.com
2. Add WhatsApp product to the app
3. Register dedicated phone number in the WABA
4. Generate System User permanent access token
5. Submit message templates for approval:
   - `session_reminder` (Utility)
   - `session_followup` (Utility)
   - `enrollment_welcome` (Utility)
   - `certificate_delivery` (Utility)
   - `general_notification` (Utility)
6. Configure webhook URL: `https://yourdomain.com/api/whatsapp/webhook`

## Pricing (Malaysia)

| Category | Rate | MYR ~ |
|----------|------|-------|
| Marketing | $0.086/msg | ~RM 0.37 |
| Utility | $0.014/msg | ~RM 0.06 |
| Authentication | $0.014/msg | ~RM 0.06 |
| Service (replies) | FREE | FREE |
