<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class SettingsService
{
    private const CACHE_PREFIX = 'settings_';

    private const CACHE_TTL = 3600; // 1 hour

    /**
     * Get a setting value by key
     */
    public function get(string $key, $default = null)
    {
        $cacheKey = self::CACHE_PREFIX.$key;

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($key, $default) {
            $setting = Setting::byKey($key)->first();

            return $setting ? $setting->value : $default;
        });
    }

    /**
     * Set a setting value
     */
    public function set(string $key, $value, string $type = 'string', string $group = 'general', ?string $description = null): Setting
    {
        $setting = Setting::updateOrCreate(
            ['key' => $key],
            [
                'value' => $value,
                'type' => $type,
                'group' => $group,
                'description' => $description,
            ]
        );

        // Clear cache for this setting
        $this->forget($key);

        return $setting;
    }

    /**
     * Get all settings for a specific group
     */
    public function getGroup(string $group): array
    {
        $cacheKey = self::CACHE_PREFIX.'group_'.$group;

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($group) {
            return Setting::group($group)
                ->get()
                ->keyBy('key')
                ->map(fn ($setting) => $setting->value)
                ->toArray();
        });
    }

    /**
     * Get all settings as a key-value array
     */
    public function all(): array
    {
        $cacheKey = self::CACHE_PREFIX.'all';

        return Cache::remember($cacheKey, self::CACHE_TTL, function () {
            return Setting::all()
                ->keyBy('key')
                ->map(fn ($setting) => $setting->value)
                ->toArray();
        });
    }

    /**
     * Get public settings (for frontend)
     */
    public function getPublic(): array
    {
        $cacheKey = self::CACHE_PREFIX.'public';

        return Cache::remember($cacheKey, self::CACHE_TTL, function () {
            return Setting::public()
                ->get()
                ->keyBy('key')
                ->map(fn ($setting) => $setting->value)
                ->toArray();
        });
    }

    /**
     * Remove a setting from cache
     */
    public function forget(string $key): void
    {
        Cache::forget(self::CACHE_PREFIX.$key);

        // Also clear group and all caches
        $setting = Setting::byKey($key)->first();
        if ($setting) {
            Cache::forget(self::CACHE_PREFIX.'group_'.$setting->group);
        }

        Cache::forget(self::CACHE_PREFIX.'all');
        Cache::forget(self::CACHE_PREFIX.'public');
    }

    /**
     * Clear all settings cache
     */
    public function flush(): void
    {
        $groups = Setting::distinct('group')->pluck('group');

        foreach ($groups as $group) {
            Cache::forget(self::CACHE_PREFIX.'group_'.$group);
        }

        $keys = Setting::pluck('key');
        foreach ($keys as $key) {
            Cache::forget(self::CACHE_PREFIX.$key);
        }

        Cache::forget(self::CACHE_PREFIX.'all');
        Cache::forget(self::CACHE_PREFIX.'public');
    }

    /**
     * Handle file upload for settings
     */
    public function uploadFile(UploadedFile $file, string $directory = 'settings'): string
    {
        $filename = time().'_'.$file->getClientOriginalName();
        $path = $file->storeAs($directory, $filename, 'public');

        return $path;
    }

    /**
     * Update a file setting
     */
    public function setFile(string $key, UploadedFile $file, string $group = 'appearance', ?string $description = null): Setting
    {
        // Delete old file if exists
        $oldSetting = Setting::byKey($key)->first();
        if ($oldSetting && $oldSetting->type === 'file') {
            $oldPath = $oldSetting->getFilePath();
            if ($oldPath && Storage::disk('public')->exists($oldPath)) {
                Storage::disk('public')->delete($oldPath);
            }
        }

        // Upload new file
        $path = $this->uploadFile($file);

        return $this->set($key, $path, 'file', $group, $description);
    }

    /**
     * Get logo URL
     */
    public function getLogo(): ?string
    {
        $setting = Setting::byKey('logo_path')->first();
        if ($setting && $setting->type === 'file') {
            $logoPath = $setting->getRawValue();

            return $logoPath ? Storage::disk('public')->url($logoPath) : null;
        }

        return null;
    }

    /**
     * Get favicon URL
     */
    public function getFavicon(): ?string
    {
        $setting = Setting::byKey('favicon_path')->first();
        if ($setting && $setting->type === 'file') {
            $faviconPath = $setting->getRawValue();

            return $faviconPath ? Storage::disk('public')->url($faviconPath) : null;
        }

        return null;
    }

    /**
     * Get Stripe configuration
     */
    public function getStripeKeys(): array
    {
        return [
            'publishable_key' => $this->get('stripe_publishable_key'),
            'secret_key' => $this->get('stripe_secret_key'),
            'webhook_secret' => $this->get('stripe_webhook_secret'),
            'mode' => $this->get('payment_mode', 'test'),
        ];
    }

    /**
     * Check if Stripe is configured
     */
    public function isStripeConfigured(): bool
    {
        $keys = $this->getStripeKeys();

        return ! empty($keys['publishable_key']) && ! empty($keys['secret_key']);
    }

    /**
     * Get bank transfer details
     */
    public function getBankDetails(): array
    {
        return [
            'bank_name' => $this->get('bank_name'),
            'account_name' => $this->get('bank_account_name'),
            'account_number' => $this->get('bank_account_number'),
            'swift_code' => $this->get('bank_swift_code'),
        ];
    }

    /**
     * Check if bank transfers are enabled
     */
    public function isBankTransferEnabled(): bool
    {
        return (bool) $this->get('enable_bank_transfers', false);
    }

    /**
     * Get site configuration
     */
    public function getSiteConfig(): array
    {
        return [
            'name' => $this->get('site_name', 'Mudeer Bedaie'),
            'description' => $this->get('site_description', 'Educational Management System'),
            'admin_email' => $this->get('admin_email', 'admin@example.com'),
            'timezone' => $this->get('timezone', 'Asia/Kuala_Lumpur'),
            'language' => $this->get('language', 'en'),
            'date_format' => $this->get('date_format', 'd/m/Y'),
            'time_format' => $this->get('time_format', 'h:i A'),
        ];
    }

    /**
     * Get appearance configuration
     */
    public function getAppearanceConfig(): array
    {
        return [
            'logo' => $this->getLogo(),
            'favicon' => $this->getFavicon(),
            'primary_color' => $this->get('primary_color', '#3B82F6'),
            'secondary_color' => $this->get('secondary_color', '#10B981'),
            'footer_text' => $this->get('footer_text', '© 2025 Mudeer Bedaie. All rights reserved.'),
        ];
    }

    /**
     * Get email configuration
     */
    public function getEmailConfig(): array
    {
        return [
            'from_address' => $this->get('mail_from_address', 'noreply@example.com'),
            'from_name' => $this->get('mail_from_name', 'Mudeer Bedaie'),
            'smtp_host' => $this->get('smtp_host'),
            'smtp_port' => $this->get('smtp_port'),
            'smtp_username' => $this->get('smtp_username'),
            'smtp_password' => $this->get('smtp_password'),
            'smtp_encryption' => $this->get('smtp_encryption', 'tls'),
        ];
    }

    /**
     * Bulk update settings
     */
    public function updateMultiple(array $settings, ?string $group = null): void
    {
        foreach ($settings as $key => $data) {
            if (is_array($data)) {
                $this->set(
                    $key,
                    $data['value'] ?? null,
                    $data['type'] ?? 'string',
                    $data['group'] ?? $group ?? 'general',
                    $data['description'] ?? null
                );
            } else {
                $this->set($key, $data, 'string', $group ?? 'general');
            }
        }
    }

    /**
     * Export settings as JSON
     */
    public function export(): string
    {
        $settings = Setting::all()->map(function ($setting) {
            return [
                'key' => $setting->key,
                'value' => $setting->type === 'encrypted' ? '[ENCRYPTED]' : $setting->getRawValue(),
                'type' => $setting->type,
                'group' => $setting->group,
                'description' => $setting->description,
                'is_public' => $setting->is_public,
            ];
        });

        return json_encode($settings, JSON_PRETTY_PRINT);
    }

    /**
     * Check if a setting exists
     */
    public function exists(string $key): bool
    {
        return Setting::byKey($key)->exists();
    }
}
