<?php

namespace App\Providers;

use App\Services\SettingsService;
use Illuminate\Support\ServiceProvider;

class MailConfigServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Only configure mail settings if we're not in console or during testing
        // to avoid issues during migrations and tests
        if ($this->app->runningInConsole() && ! $this->app->runningUnitTests()) {
            return;
        }

        // Check if settings table exists before trying to load settings
        if (! $this->shouldConfigureMail()) {
            return;
        }

        $this->configureMailFromSettings();
    }

    /**
     * Check if we should configure mail settings
     */
    private function shouldConfigureMail(): bool
    {
        try {
            // Check if database is available
            if (! \Illuminate\Support\Facades\Schema::hasTable('settings')) {
                return false;
            }

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Configure mail settings from database
     */
    private function configureMailFromSettings(): void
    {
        try {
            $settingsService = app(SettingsService::class);
            $emailConfig = $settingsService->getEmailConfig();

            // Only configure SMTP if host is set
            if (! empty($emailConfig['smtp_host'])) {
                config([
                    'mail.default' => 'smtp',
                    'mail.mailers.smtp.transport' => 'smtp',
                    'mail.mailers.smtp.host' => $emailConfig['smtp_host'],
                    'mail.mailers.smtp.port' => $emailConfig['smtp_port'] ?? 587,
                    'mail.mailers.smtp.encryption' => $emailConfig['smtp_encryption'] ?? 'tls',
                    'mail.mailers.smtp.username' => $emailConfig['smtp_username'],
                    'mail.mailers.smtp.password' => $emailConfig['smtp_password'],
                ]);
            }

            // Always configure from address and name if they exist
            if (! empty($emailConfig['from_address'])) {
                config([
                    'mail.from.address' => $emailConfig['from_address'],
                    'mail.from.name' => $emailConfig['from_name'] ?? config('app.name'),
                ]);
            }
        } catch (\Exception $e) {
            // Silently fail if settings cannot be loaded
            // This prevents errors during installation or migration
            \Illuminate\Support\Facades\Log::debug('Failed to configure mail settings: '.$e->getMessage());
        }
    }
}
