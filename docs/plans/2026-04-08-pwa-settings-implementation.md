# PWA Settings Page Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Add a PWA settings page to the HR module where admins can configure app branding, display mode, and push notification settings — with a dynamically served manifest.json.

**Architecture:** React settings page (like PayrollSettings) → API endpoints → Setting model (group: `pwa`) → Dynamic manifest route. Icon uploads stored in `storage/app/public/pwa-icons/`.

**Tech Stack:** React + TanStack Query (frontend), Laravel controller + Setting model (backend), existing SettingsService pattern.

---

### Task 1: Backend — PWA Settings Controller

**Files:**
- Create: `app/Http/Controllers/Api/Hr/HrPwaSettingController.php`

**Step 1: Create the controller**

```bash
php artisan make:class App/Http/Controllers/Api/Hr/HrPwaSettingController --no-interaction
```

Then replace with:

```php
<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class HrPwaSettingController extends Controller
{
    private const SETTINGS_GROUP = 'pwa';

    private const DEFAULTS = [
        'pwa_app_name' => 'Mudeer HR',
        'pwa_short_name' => 'HR',
        'pwa_description' => 'Mudeer HR - Attendance & Leave Management',
        'pwa_theme_color' => '#1e40af',
        'pwa_background_color' => '#ffffff',
        'pwa_display' => 'standalone',
        'pwa_orientation' => 'portrait-primary',
        'pwa_start_url' => '/hr/clock',
        'pwa_scope' => '/hr',
        'pwa_push_enabled' => false,
        'pwa_vapid_public' => '',
        'pwa_vapid_private' => '',
        'pwa_vapid_subject' => '',
    ];

    public function index(): JsonResponse
    {
        $settings = Setting::where('group', self::SETTINGS_GROUP)->get()->keyBy('key');

        $data = [];
        foreach (self::DEFAULTS as $key => $default) {
            $data[$key] = $settings->has($key) ? $settings[$key]->value : $default;
        }

        // Add icon URLs
        $data['pwa_icon_192_url'] = $this->getIconUrl('pwa_icon_192');
        $data['pwa_icon_512_url'] = $this->getIconUrl('pwa_icon_512');

        return response()->json(['data' => $data]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'pwa_app_name' => ['required', 'string', 'max:100'],
            'pwa_short_name' => ['required', 'string', 'max:30'],
            'pwa_description' => ['nullable', 'string', 'max:255'],
            'pwa_theme_color' => ['required', 'string', 'regex:/^#[a-fA-F0-9]{6}$/'],
            'pwa_background_color' => ['required', 'string', 'regex:/^#[a-fA-F0-9]{6}$/'],
            'pwa_display' => ['required', 'in:standalone,fullscreen,minimal-ui,browser'],
            'pwa_orientation' => ['required', 'in:any,portrait-primary,landscape'],
            'pwa_start_url' => ['required', 'string', 'max:255'],
            'pwa_scope' => ['required', 'string', 'max:255'],
            'pwa_push_enabled' => ['boolean'],
            'pwa_vapid_public' => ['nullable', 'string', 'max:500'],
            'pwa_vapid_private' => ['nullable', 'string', 'max:500'],
            'pwa_vapid_subject' => ['nullable', 'string', 'max:255'],
            'pwa_icon_192' => ['nullable', 'file', 'mimes:png,svg,jpg,jpeg,webp', 'max:2048'],
            'pwa_icon_512' => ['nullable', 'file', 'mimes:png,svg,jpg,jpeg,webp', 'max:2048'],
        ]);

        // Handle icon uploads
        foreach (['pwa_icon_192', 'pwa_icon_512'] as $iconKey) {
            if ($request->hasFile($iconKey)) {
                $oldSetting = Setting::where('key', $iconKey)->first();
                if ($oldSetting && $oldSetting->getRawValue() && Storage::disk('public')->exists($oldSetting->getRawValue())) {
                    Storage::disk('public')->delete($oldSetting->getRawValue());
                }

                $path = $request->file($iconKey)->store('pwa-icons', 'public');
                Setting::setValue($iconKey, $path, 'file', self::SETTINGS_GROUP);
            }
            unset($validated[$iconKey]);
        }

        // Save text/string settings
        foreach ($validated as $key => $value) {
            if ($key === 'pwa_vapid_private' && $value) {
                Setting::setValue($key, $value, 'encrypted', self::SETTINGS_GROUP);
            } elseif ($key === 'pwa_push_enabled') {
                Setting::setValue($key, $value, 'boolean', self::SETTINGS_GROUP);
            } else {
                Setting::setValue($key, (string) $value, 'string', self::SETTINGS_GROUP);
            }
        }

        return response()->json(['message' => 'PWA settings updated successfully.']);
    }

    public function manifest(): JsonResponse
    {
        $settings = Setting::where('group', self::SETTINGS_GROUP)->get()->keyBy('key');

        $get = function (string $key) use ($settings) {
            return $settings->has($key) ? $settings[$key]->value : (self::DEFAULTS[$key] ?? '');
        };

        $icons = [];
        foreach ([['pwa_icon_192', '192x192'], ['pwa_icon_512', '512x512']] as [$key, $size]) {
            $url = $this->getIconUrl($key);
            if ($url) {
                $icons[] = [
                    'src' => $url,
                    'sizes' => $size,
                    'type' => 'image/png',
                    'purpose' => 'any maskable',
                ];
            }
        }

        // Fallback to existing icons if none uploaded
        if (empty($icons)) {
            $icons = [
                ['src' => '/icons/hr-192.svg', 'sizes' => '192x192', 'type' => 'image/svg+xml', 'purpose' => 'any maskable'],
                ['src' => '/icons/hr-512.svg', 'sizes' => '512x512', 'type' => 'image/svg+xml', 'purpose' => 'any maskable'],
            ];
        }

        $manifest = [
            'name' => $get('pwa_app_name'),
            'short_name' => $get('pwa_short_name'),
            'description' => $get('pwa_description'),
            'start_url' => $get('pwa_start_url'),
            'display' => $get('pwa_display'),
            'background_color' => $get('pwa_background_color'),
            'theme_color' => $get('pwa_theme_color'),
            'orientation' => $get('pwa_orientation'),
            'scope' => $get('pwa_scope'),
            'icons' => $icons,
        ];

        return response()->json($manifest)->header('Cache-Control', 'public, max-age=3600');
    }

    public function generateVapid(): JsonResponse
    {
        $vapid = \Minishlink\WebPush\VAPID::createVapidKeys();

        return response()->json([
            'public_key' => $vapid['publicKey'],
            'private_key' => $vapid['privateKey'],
        ]);
    }

    private function getIconUrl(string $key): ?string
    {
        $setting = Setting::where('key', $key)->first();
        if ($setting && $setting->getRawValue()) {
            return Storage::disk('public')->url($setting->getRawValue());
        }

        return null;
    }
}
```

**Step 2: Add API routes**

In `routes/api.php`, inside the HR middleware group, after the payroll settings routes (~line 749), add:

```php
// PWA Settings
Route::get('settings/pwa', [HrPwaSettingController::class, 'index'])->name('api.hr.settings.pwa.index');
Route::post('settings/pwa', [HrPwaSettingController::class, 'update'])->name('api.hr.settings.pwa.update');
Route::post('settings/pwa/generate-vapid', [HrPwaSettingController::class, 'generateVapid'])->name('api.hr.settings.pwa.generate-vapid');
```

Add the import at the top of the HR routes section:
```php
use App\Http\Controllers\Api\Hr\HrPwaSettingController;
```

**Step 3: Add dynamic manifest web route**

In `routes/web.php`, BEFORE the HR catch-all route (line 480), add:

```php
Route::get('hr/manifest.json', [HrPwaSettingController::class, 'manifest'])->name('hr.manifest');
```

With import:
```php
use App\Http\Controllers\Api\Hr\HrPwaSettingController;
```

**Step 4: Update HR index.blade.php**

Change line 12 in `resources/views/hr/index.blade.php`:
```html
<link rel="manifest" href="{{ route('hr.manifest') }}">
```

**Step 5: Commit**

```
feat(hr): add PWA settings API controller and dynamic manifest route
```

---

### Task 2: Frontend — API Functions

**Files:**
- Modify: `resources/js/hr/lib/api.js`

**Step 1: Add PWA settings API functions**

Append to `resources/js/hr/lib/api.js`:

```js
// ========== PWA Settings ==========
export const fetchPwaSettings = () => api.get('/settings/pwa').then(r => r.data);
export const updatePwaSettings = (data) => {
    // Use FormData for file uploads
    const formData = new FormData();
    Object.entries(data).forEach(([key, value]) => {
        if (value !== null && value !== undefined) {
            formData.append(key, value);
        }
    });
    return api.post('/settings/pwa', formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
    }).then(r => r.data);
};
export const generateVapidKeys = () => api.post('/settings/pwa/generate-vapid').then(r => r.data);
```

**Step 2: Commit**

```
feat(hr): add PWA settings API client functions
```

---

### Task 3: Frontend — PWA Settings Page Component

**Files:**
- Create: `resources/js/hr/pages/settings/PwaSettings.jsx`

**Step 1: Create the settings page**

Create `resources/js/hr/pages/settings/PwaSettings.jsx` following the PayrollSettings pattern with three card sections: Branding, Display, Push Notifications. Include:
- Color picker inputs for theme/background color
- File upload inputs for 192x192 and 512x512 icons with preview
- Select dropdowns for display mode and orientation
- Toggle for push notifications
- VAPID key inputs with generate button
- Save button with loading/success states

Use the same patterns from PayrollSettings (useQuery, useMutation, handleChange, Card layout).

**Step 2: Commit**

```
feat(hr): add PWA settings page component
```

---

### Task 4: Frontend — Register Route and Sidebar Navigation

**Files:**
- Modify: `resources/js/hr/App.jsx`
- Modify: `resources/js/hr/layouts/HrLayout.jsx`

**Step 1: Add route in App.jsx**

Import and add route:
```jsx
import PwaSettings from './pages/settings/PwaSettings';
```

Inside AdminRoutes, add before the Shared section:
```jsx
{/* Settings */}
<Route path="settings/pwa" element={<PwaSettings />} />
```

**Step 2: Add sidebar nav in HrLayout.jsx**

Import `Smartphone` from lucide-react, then add to the navigation array (after "Meetings" group):
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

**Step 3: Commit**

```
feat(hr): register PWA settings route and sidebar navigation
```

---

### Task 5: Build and Test

**Step 1: Build frontend**
```bash
npm run build
```

**Step 2: Test the page manually via Playwright**
- Navigate to `/hr/settings/pwa`
- Verify the page loads with all three sections
- Verify the sidebar shows Settings > PWA

**Step 3: Test manifest endpoint**
```bash
curl http://mudeerbedaie.test/hr/manifest.json
```

**Step 4: Commit any fixes if needed**
