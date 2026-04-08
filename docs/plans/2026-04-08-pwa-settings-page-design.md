# PWA Settings Page Design

**Date:** 2026-04-08
**Location:** HR Module → Settings → PWA

## Overview

Add a PWA (Progressive Web App) settings page to the HR module that allows admins to customize the app's branding, display behavior, and push notification configuration. Settings are stored in the database and the manifest.json is served dynamically.

## Sections

### 1. Branding
- **App Name** — full name shown in app install prompt (default: "Mudeer HR")
- **Short Name** — shown on home screen (default: "HR")
- **Description** — app description text
- **App Icon 192x192** — file upload with preview (PNG/SVG)
- **App Icon 512x512** — file upload with preview (PNG/SVG)
- **Theme Color** — color picker (browser chrome color, default: #1e40af)
- **Background Color** — color picker (splash screen, default: #ffffff)

### 2. Display Settings
- **Display Mode** — select: standalone (default), fullscreen, minimal-ui, browser
- **Orientation** — select: portrait-primary (default), any, landscape
- **Start URL** — text input (default: /hr/clock)
- **Scope** — text input (default: /hr)

### 3. Push Notifications
- **Enable Push Notifications** — toggle on/off
- **VAPID Public Key** — text input (read from config, editable)
- **VAPID Private Key** — text input (masked, read from config, editable)
- **VAPID Subject** — text input (email/URL)
- Generate new VAPID keys button

## Technical Design

### Frontend (React)
- New page: `resources/js/hr/pages/settings/PwaSettings.jsx`
- Route: `settings/pwa` in admin routes
- Sidebar: New "Settings" group at bottom of navigation with "PWA" child item
- Pattern: matches existing AttendanceSettings/PayrollSettings (useQuery + useMutation)
- File uploads use FormData for icon uploads

### API Endpoints
- `GET /api/hr/settings/pwa` — fetch current PWA settings
- `PUT /api/hr/settings/pwa` — update PWA settings (accepts multipart/form-data for icons)
- `POST /api/hr/settings/pwa/generate-vapid` — generate new VAPID key pair

### Controller
- New: `app/Http/Controllers/Api/Hr/HrPwaSettingController.php`
- Uses SettingsService with group `pwa` for all settings
- Icon uploads stored in `storage/app/public/pwa-icons/`

### Dynamic Manifest Route
- New route: `GET /hr/manifest.json` → returns JSON manifest from DB settings
- Update `resources/views/hr/index.blade.php` to use `/hr/manifest.json`
- Cached with 1-hour TTL, invalidated on save

### Database
- Uses existing `settings` table — no migration needed
- Keys prefixed with `pwa_` in group `pwa`:
  - `pwa_app_name`, `pwa_short_name`, `pwa_description`
  - `pwa_icon_192`, `pwa_icon_512` (file paths)
  - `pwa_theme_color`, `pwa_background_color`
  - `pwa_display`, `pwa_orientation`, `pwa_start_url`, `pwa_scope`
  - `pwa_push_enabled`, `pwa_vapid_public`, `pwa_vapid_private`, `pwa_vapid_subject`

## Navigation
Add to HrLayout sidebar navigation array:
```js
{
    name: 'Settings',
    icon: Settings2,
    prefix: '/settings',
    children: [
        { name: 'PWA', to: '/settings/pwa', icon: Smartphone },
    ],
},
```
