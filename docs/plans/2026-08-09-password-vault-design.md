# Password Vault Module — Design

**Date:** 2026-08-09
**Status:** Approved
**Access:** Admin only (`role:admin`)

## Overview

A standalone Inertia React workspace at `/admin/vault` for managing external service credentials (TikTok Shop, social media, hosting, domains, email accounts, API keys, etc.). All passwords encrypted at rest.

## Architecture

| Layer | Detail |
|-------|--------|
| **Route prefix** | `/admin/vault` |
| **Middleware** | `auth`, `role:admin`, `HandleVaultInertiaRequests` |
| **React pages** | `resources/js/vault/pages/*` |
| **Layout** | `VaultLayout.jsx` — dedicated layout (dark-themed, like Blog & SEO) |
| **Controllers** | `app/Http/Controllers/Vault/*` |
| **Models** | `VaultCredential`, `VaultCategory`, `VaultTag`, `VaultAuditLog` |

## Data Model

### `vault_categories`
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| name | string | e.g. "Social Media", "Hosting & Domain" |
| slug | string unique | |
| icon | string nullable | Icon name (Flux/Heroicons) |
| color | string nullable | Hex or Tailwind color |
| sort_order | int default 0 | |
| timestamps | | |

### `vault_tags`
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| name | string | |
| slug | string unique | |
| timestamps | | |

### `vault_credentials`
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| name | string | Service name (e.g. "TikTok Shop MY") |
| url | string nullable | Login URL |
| username | string nullable | Username or email |
| password | text encrypted | Laravel Crypt |
| notes | text nullable encrypted | Additional info |
| category_id | foreign nullable | → vault_categories |
| created_by | foreign | → users |
| updated_by | foreign nullable | → users |
| timestamps | | |

### `vault_credential_tag` (pivot)
| Column | Type |
|--------|------|
| vault_credential_id | foreign |
| vault_tag_id | foreign |

### `vault_audit_logs`
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| credential_id | foreign nullable | → vault_credentials (nullable for deleted) |
| user_id | foreign | → users |
| action | string | viewed / created / updated / deleted |
| changes | json nullable | Old vs new values (password values NEVER stored) |
| ip_address | string nullable | |
| timestamps | | |

## Pages

| Page | Path | Description |
|------|------|-------------|
| Dashboard | `/admin/vault` | Overview: credential count by category, recent activity |
| Credentials | `/admin/vault/credentials` | Main list — search, filter by category/tag, sort |
| Create/Edit | Modal from Credentials | Form with password generator |
| Categories | `/admin/vault/categories` | CRUD categories |
| Tags | `/admin/vault/tags` | CRUD tags |
| Audit Log | `/admin/vault/audit-log` | Activity timeline |

## Key Features

### Password Security
- Encrypted at rest via `Crypt::encryptString()` (AES-256-CBC)
- Default display: masked (`••••••••`)
- Show/Hide toggle per credential
- Copy-to-clipboard without revealing

### Password Generator
- Configurable: length (8–64), uppercase, lowercase, numbers, symbols
- Generate directly in create/edit form
- One-click regenerate

### Search & Filter
- Search by name, username, URL
- Filter by category and/or tags
- Sort by name, category, date created/updated

### Audit Log
- Every view/create/update/delete recorded
- Tracks: user, action, timestamp, IP address
- Changes JSON shows field diffs (NEVER includes password values)

## Security Considerations

- All passwords encrypted at rest (Laravel Crypt / APP_KEY)
- Password values excluded from audit log changes
- Admin-only access (`role:admin` middleware)
- HTTPS via Herd
- No plaintext password in any API response — always encrypted, decrypted only on explicit "show" action

## UI/UX Pattern

Follows Blog & SEO workspace pattern:
- Standalone Inertia React app
- Dedicated dark-themed layout with sidebar navigation
- `HandleVaultInertiaRequests` middleware for stats injection
- Responsive design
