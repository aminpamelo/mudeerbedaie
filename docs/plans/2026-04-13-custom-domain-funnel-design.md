# Custom Domain for Funnels — Design Document

**Date:** 2026-04-13  
**Status:** Approved  
**Approach:** Cloudflare for SaaS + Laravel Middleware

## Overview

Allow funnel owners to connect custom domains (e.g., `checkout.mybrand.com`) or platform subdomains (e.g., `mybrand.kelasify.com`) to individual funnels. SSL is handled automatically via Cloudflare for SaaS.

## Requirements

- **Domain types:** Full custom domains AND platform subdomains
- **Scope:** Per funnel — each funnel can have its own domain
- **SSL:** Cloudflare for SaaS (automatic certificate provisioning)
- **Automation:** Semi-automated — user enters domain + adds CNAME, system verifies via Cloudflare API and activates

## Architecture

### Flow: Custom Domain

```
1. User enters domain in funnel settings (e.g., checkout.mybrand.com)
2. System calls Cloudflare API → POST /custom_hostnames → gets hostname_id
3. UI shows CNAME instructions: "Point checkout.mybrand.com → cdn.kelasify.com"
4. User adds CNAME at their DNS provider
5. Scheduled job polls Cloudflare every 5 min for verification status
6. Once DNS verified → ssl provisioned → domain marked active
7. Funnel is now live at checkout.mybrand.com
```

### Flow: Subdomain

```
1. User picks subdomain name (e.g., "mybrand")
2. System checks availability in custom_domains table
3. Instant activation — wildcard DNS on *.kelasify.com handles routing
4. Funnel is live at mybrand.kelasify.com
```

### Flow: Visitor Request

```
Browser → DNS → CNAME to cdn.kelasify.com → Cloudflare edge
→ SSL terminated by Cloudflare → forwarded to origin server
→ Nginx accepts any hostname → Laravel receives request
→ ResolveCustomDomain middleware checks Host header
→ Looks up custom_domains table → binds funnel to request
→ PublicFunnelController serves funnel at root path
```

## Data Model

### `custom_domains` table

| Column | Type | Description |
|--------|------|-------------|
| id | bigint PK | Auto-increment |
| uuid | uuid, unique | Public identifier |
| funnel_id | FK → funnels | Which funnel this domain serves |
| user_id | FK → users | Owner |
| domain | string(255), unique | The custom domain or subdomain |
| type | enum: custom, subdomain | Domain type |
| cloudflare_hostname_id | string, nullable | Cloudflare custom hostname ID |
| verification_status | enum: pending, active, failed, deleting | DNS verification state |
| ssl_status | enum: pending, active, failed | SSL certificate state |
| verification_errors | json, nullable | Error details from Cloudflare |
| verified_at | timestamp, nullable | When DNS was verified |
| ssl_active_at | timestamp, nullable | When SSL became active |
| last_checked_at | timestamp, nullable | Last Cloudflare status poll |
| created_at | timestamp | |
| updated_at | timestamp | |
| deleted_at | timestamp, nullable | Soft delete |

**Indexes:**
- unique on `domain`
- index on `funnel_id`
- index on `user_id`
- index on `verification_status` (for scheduled job queries)

**Relationships:**
- `Funnel hasOne CustomDomain`
- `User hasMany CustomDomains`
- `CustomDomain belongsTo Funnel`
- `CustomDomain belongsTo User`

## Cloudflare for SaaS Integration

### Prerequisites (One-Time Setup)

1. Enable Cloudflare for SaaS on `kelasify.com` zone
2. Create fallback origin DNS record: `cdn.kelasify.com` → server IP
3. Set `cdn.kelasify.com` as fallback origin in Cloudflare dashboard
4. Create API token with permissions: Zone → SSL and Certificates → Edit, Zone → Zone → Read
5. Configure Nginx/Apache to accept any hostname (catch-all server block)

### Environment Variables

```
CLOUDFLARE_API_TOKEN=          # API token with Custom Hostnames permission
CLOUDFLARE_ZONE_ID=            # Zone ID for kelasify.com
CLOUDFLARE_FALLBACK_ORIGIN=    # cdn.kelasify.com
CUSTOM_DOMAIN_CNAME_TARGET=    # What users CNAME to (cdn.kelasify.com)
CUSTOM_DOMAIN_SUBDOMAIN_BASE=  # kelasify.com (for subdomain feature)
```

### Service: `CloudflareCustomHostnameService`

```php
class CloudflareCustomHostnameService
{
    public function createHostname(string $domain): array;
    // POST /zones/{zone_id}/custom_hostnames
    // Returns: hostname_id, verification_status, ssl_status

    public function getHostnameStatus(string $hostnameId): array;
    // GET /zones/{zone_id}/custom_hostnames/{id}
    // Returns: current verification + SSL status

    public function deleteHostname(string $hostnameId): bool;
    // DELETE /zones/{zone_id}/custom_hostnames/{id}
}
```

## Request Routing: Middleware

### `ResolveCustomDomain` Middleware

Registered early in the HTTP middleware stack (before route resolution).

```
1. Extract Host header from request
2. Skip if Host matches main app domains (kelasify.com, mudeerbedaie.test, localhost, etc.)
3. Check if Host is a platform subdomain (*.kelasify.com):
   - Extract subdomain portion
   - Query: custom_domains WHERE domain = subdomain AND type = 'subdomain' AND verification_status = 'active'
4. Otherwise, check custom domain:
   - Query: custom_domains WHERE domain = Host AND type = 'custom' AND verification_status = 'active'
5. If found:
   - Bind funnel to request attributes
   - Rewrite route to serve funnel at root path (no /f/ prefix)
6. If not found:
   - Return 404
```

**Caching:** Cache domain→funnel mappings in Redis/database cache (invalidate on domain status change).

### Route Changes

Custom domain requests serve funnels at root:
- `checkout.mybrand.com/` → funnel landing step
- `checkout.mybrand.com/checkout` → checkout step
- `checkout.mybrand.com/thank-you` → thank you step

The `/f/{slug}` routes remain for funnels without custom domains.

## User Interface

### Funnel Settings → Custom Domain Section

**No domain configured:**
- Two buttons: "Add Custom Domain" / "Use Subdomain"
- Custom domain: text input for full domain
- Subdomain: text input for subdomain prefix + `.kelasify.com` suffix shown

**Pending verification (custom domain only):**
- Status badge: yellow "Pending Verification"
- CNAME instructions box:
  ```
  Add this DNS record at your domain provider:
  Type: CNAME
  Name: checkout.mybrand.com
  Target: cdn.kelasify.com
  ```
- "Check Status" button for manual refresh
- "Remove Domain" button

**Active:**
- Status badge: green "Active"
- Domain displayed as clickable link
- SSL status indicator
- "Remove Domain" button

**Failed:**
- Status badge: red "Failed"
- Error message displayed
- "Retry" and "Remove" buttons

## Background Jobs

| Job / Command | Schedule | Purpose |
|---------------|----------|---------|
| `VerifyCustomDomains` | Every 5 minutes | Poll Cloudflare API for all domains with `verification_status = pending`, update statuses |
| `CleanupFailedDomains` | Daily | Notify users about domains stuck in `failed` for >7 days, optionally auto-remove |

## Admin Dashboard

Admin page at `/admin/custom-domains`:
- Table of all custom domains across all users/funnels
- Columns: domain, funnel name, user, type, verification status, SSL status, created date
- Filters: by status, by type
- Actions: manually activate/deactivate, delete
- View Cloudflare errors for failed domains

## Security Considerations

- Validate domain format (no IP addresses, no reserved TLDs)
- Prevent domain hijacking: only allow adding domains if Cloudflare verification passes
- Rate limit domain creation (e.g., max 5 pending domains per user)
- CNAME target is always your controlled domain (cdn.kelasify.com)
- Soft delete domains to maintain audit trail

## Files to Create/Modify

### New Files
- `app/Models/CustomDomain.php` — Eloquent model
- `app/Services/CloudflareCustomHostnameService.php` — Cloudflare API client
- `app/Http/Middleware/ResolveCustomDomain.php` — Request routing middleware
- `app/Jobs/VerifyCustomDomains.php` — Scheduled verification job
- `app/Jobs/CleanupFailedDomains.php` — Cleanup job
- `database/migrations/xxxx_create_custom_domains_table.php` — Migration
- `resources/views/livewire/admin/custom-domains-index.blade.php` — Admin list page
- Funnel settings UI component for domain management

### Modified Files
- `app/Models/Funnel.php` — Add `customDomain()` relationship
- `app/Models/User.php` — Add `customDomains()` relationship
- `app/Http/Controllers/PublicFunnelController.php` — Handle custom domain context
- `bootstrap/app.php` — Register middleware
- `routes/web.php` — Add admin routes for custom domains
- `routes/console.php` — Register scheduled jobs
- `.env.example` — Add Cloudflare env vars
- `config/services.php` — Add Cloudflare config section
