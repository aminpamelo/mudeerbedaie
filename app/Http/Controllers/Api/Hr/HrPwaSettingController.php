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
    ];

    public function index(): JsonResponse
    {
        $settings = Setting::where('group', self::SETTINGS_GROUP)->get()->keyBy('key');

        $data = [];
        foreach (self::DEFAULTS as $key => $default) {
            $data[$key] = $settings->has($key) ? $settings[$key]->value : $default;
        }

        // VAPID keys always come from .env config (source of truth for WebPush sending)
        $data['pwa_vapid_public'] = config('webpush.vapid.public_key', '');
        $data['pwa_vapid_private'] = config('webpush.vapid.private_key') ? '••••••••' : '';
        $data['pwa_vapid_subject'] = config('webpush.vapid.subject', '');
        $data['pwa_vapid_configured'] = ! empty(config('webpush.vapid.public_key'));

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
            if ($key === 'pwa_push_enabled') {
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

    private function getIconUrl(string $key): ?string
    {
        $setting = Setting::where('key', $key)->first();
        if ($setting && $setting->getRawValue()) {
            return Storage::disk('public')->url($setting->getRawValue());
        }

        return null;
    }
}
