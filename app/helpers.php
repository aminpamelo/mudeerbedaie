<?php

use App\Services\SettingsService;

if (! function_exists('setting')) {
    /**
     * Get a setting value by key
     *
     * @param  mixed  $default
     * @return mixed
     */
    function setting(string $key, $default = null)
    {
        return app(SettingsService::class)->get($key, $default);
    }
}

if (! function_exists('settings')) {
    /**
     * Get the settings service instance
     */
    function settings(): SettingsService
    {
        return app(SettingsService::class);
    }
}

if (! function_exists('site_name')) {
    /**
     * Get the site name
     */
    function site_name(): string
    {
        return setting('site_name', 'Mudeer Bedaie');
    }
}

if (! function_exists('site_logo')) {
    /**
     * Get the site logo URL
     */
    function site_logo(): ?string
    {
        return settings()->getLogo();
    }
}

if (! function_exists('site_favicon')) {
    /**
     * Get the site favicon URL
     */
    function site_favicon(): ?string
    {
        return settings()->getFavicon();
    }
}

if (! function_exists('admin_email')) {
    /**
     * Get the admin email
     */
    function admin_email(): string
    {
        return setting('admin_email', 'admin@example.com');
    }
}

if (! function_exists('stripe_configured')) {
    /**
     * Check if Stripe is configured
     */
    function stripe_configured(): bool
    {
        return settings()->isStripeConfigured();
    }
}

if (! function_exists('bank_transfer_enabled')) {
    /**
     * Check if bank transfers are enabled
     */
    function bank_transfer_enabled(): bool
    {
        return settings()->isBankTransferEnabled();
    }
}
