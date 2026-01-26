<?php

declare(strict_types=1);

namespace App\Services\MergeTag\DataProviders;

use App\Services\MergeTag\DataProviderInterface;
use Carbon\Carbon;

class SystemDataProvider implements DataProviderInterface
{
    public function getValue(string $field, array $context): ?string
    {
        // Set timezone to Malaysia by default
        $timezone = $context['timezone'] ?? config('app.timezone', 'Asia/Kuala_Lumpur');
        $now = Carbon::now($timezone);

        return match ($field) {
            'current_date' => $now->format('d M Y'),
            'current_time' => $now->format('h:i A'),
            'current_datetime' => $now->format('d M Y, h:i A'),
            'current_year' => $now->format('Y'),
            'current_month' => $now->format('F'),
            'current_day' => $now->format('l'),
            'company_name' => $this->getCompanyName($context),
            'company_email' => $this->getCompanyEmail($context),
            'company_phone' => $this->getCompanyPhone($context),
            'company_address' => $this->getCompanyAddress($context),
            'company_website' => $this->getCompanyWebsite($context),
            'app_name' => config('app.name'),
            'app_url' => config('app.url'),
            default => null,
        };
    }

    protected function getCompanyName(array $context): ?string
    {
        // Check context first (allows override per funnel/automation)
        if (isset($context['company_name'])) {
            return $context['company_name'];
        }

        // Check settings
        return $this->getSettingValue('company_name')
            ?? $this->getSettingValue('business_name')
            ?? config('app.name');
    }

    protected function getCompanyEmail(array $context): ?string
    {
        if (isset($context['company_email'])) {
            return $context['company_email'];
        }

        return $this->getSettingValue('company_email')
            ?? $this->getSettingValue('support_email')
            ?? $this->getSettingValue('contact_email')
            ?? config('mail.from.address');
    }

    protected function getCompanyPhone(array $context): ?string
    {
        if (isset($context['company_phone'])) {
            return $this->formatPhone($context['company_phone']);
        }

        $phone = $this->getSettingValue('company_phone')
            ?? $this->getSettingValue('support_phone')
            ?? $this->getSettingValue('contact_phone');

        return $phone ? $this->formatPhone($phone) : null;
    }

    protected function getCompanyAddress(array $context): ?string
    {
        if (isset($context['company_address'])) {
            return $context['company_address'];
        }

        return $this->getSettingValue('company_address')
            ?? $this->getSettingValue('business_address');
    }

    protected function getCompanyWebsite(array $context): ?string
    {
        if (isset($context['company_website'])) {
            return $context['company_website'];
        }

        return $this->getSettingValue('company_website')
            ?? $this->getSettingValue('website_url')
            ?? config('app.url');
    }

    /**
     * Get setting value from database settings table or cache.
     */
    protected function getSettingValue(string $key): ?string
    {
        // Try to use the SettingsService if available
        if (class_exists(\App\Services\SettingsService::class)) {
            try {
                return app(\App\Services\SettingsService::class)->get($key);
            } catch (\Exception $e) {
                // Silently fail and try config
            }
        }

        // Fallback to config
        return config("settings.{$key}");
    }

    /**
     * Format phone number for display.
     */
    protected function formatPhone(string $phone): string
    {
        // Clean up the phone number
        $phone = preg_replace('/[^0-9+]/', '', $phone);

        // Add Malaysian prefix if needed
        if (str_starts_with($phone, '0')) {
            $phone = '+60'.substr($phone, 1);
        } elseif (str_starts_with($phone, '60') && ! str_starts_with($phone, '+60')) {
            $phone = '+'.$phone;
        }

        return $phone;
    }
}
