<?php

namespace App\Services;

use App\Models\WhatsAppSendLog;
use App\Services\WhatsApp\WhatsAppManager;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    protected int $messagesSentInBatch = 0;

    protected array $config = [];

    public function __construct(
        private WhatsAppManager $manager,
    ) {
        $this->loadConfig();
    }

    /**
     * Load configuration from database settings (with env fallback).
     */
    protected function loadConfig(): void
    {
        $settingsService = app(SettingsService::class);
        $dbConfig = $settingsService->getWhatsAppConfig();

        $this->config = [
            'enabled' => $dbConfig['enabled'] || config('services.onsend.enabled', false),
            'min_delay_seconds' => $dbConfig['min_delay_seconds'] ?: config('services.onsend.min_delay_seconds', 10),
            'max_delay_seconds' => $dbConfig['max_delay_seconds'] ?: config('services.onsend.max_delay_seconds', 30),
            'batch_size' => $dbConfig['batch_size'] ?: config('services.onsend.batch_size', 15),
            'batch_pause_minutes' => $dbConfig['batch_pause_minutes'] ?: config('services.onsend.batch_pause_minutes', 1),
            'daily_limit' => $dbConfig['daily_limit'], // 0 means unlimited
            'time_restriction_enabled' => $dbConfig['time_restriction_enabled'] || config('services.onsend.time_restriction_enabled', false),
            'send_hours_start' => $dbConfig['send_hours_start'] ?: config('services.onsend.send_hours_start', 8),
            'send_hours_end' => $dbConfig['send_hours_end'] ?: config('services.onsend.send_hours_end', 22),
            'message_variation_enabled' => $dbConfig['message_variation_enabled'] || config('services.onsend.message_variation_enabled', false),
        ];
    }

    /**
     * Get a config value.
     */
    protected function getConfig(string $key, $default = null)
    {
        return $this->config[$key] ?? $default;
    }

    /**
     * Check if WhatsApp service is enabled and configured.
     */
    public function isEnabled(): bool
    {
        return $this->getConfig('enabled', false) && $this->manager->provider()->isConfigured();
    }

    /**
     * Check device connection status via the active provider.
     */
    public function checkDeviceStatus(): array
    {
        return $this->manager->provider()->checkStatus();
    }

    /**
     * Check if we can send a message now (time restrictions + daily limit).
     */
    public function canSendNow(): bool
    {
        if (! $this->isEnabled()) {
            return false;
        }

        // Check if within allowed hours (only if time restriction is enabled)
        if ($this->getConfig('time_restriction_enabled', false)) {
            $hour = now()->hour;
            $startHour = $this->getConfig('send_hours_start', 8);
            $endHour = $this->getConfig('send_hours_end', 22);

            if ($hour < $startHour || $hour >= $endHour) {
                return false;
            }
        }

        // Check if batch pause is active
        $pauseUntil = Cache::get('whatsapp_batch_pause_until');
        if ($pauseUntil && now()->lt($pauseUntil)) {
            return false;
        }

        // Check daily limit
        return ! $this->isDailyLimitReached();
    }

    /**
     * Check if daily message limit has been reached.
     * Returns false if daily_limit is 0 (unlimited).
     */
    public function isDailyLimitReached(): bool
    {
        $dailyLimit = $this->getConfig('daily_limit', 0);

        // 0 means unlimited
        if ($dailyLimit <= 0) {
            return false;
        }

        $todayCount = WhatsAppSendLog::where('send_date', today())->sum('message_count');

        return $todayCount >= $dailyLimit;
    }

    /**
     * Get remaining messages for today.
     * Returns -1 if unlimited.
     */
    public function getRemainingMessages(): int
    {
        $dailyLimit = $this->getConfig('daily_limit', 0);

        // 0 means unlimited, return -1 to indicate unlimited
        if ($dailyLimit <= 0) {
            return -1;
        }

        $todayCount = WhatsAppSendLog::where('send_date', today())->sum('message_count');

        return max(0, $dailyLimit - $todayCount);
    }

    /**
     * Get today's send statistics.
     */
    public function getTodayStats(): array
    {
        $log = WhatsAppSendLog::where('send_date', today())->first();
        $dailyLimit = $this->getConfig('daily_limit', 0);
        $remaining = $this->getRemainingMessages();

        return [
            'message_count' => $log?->message_count ?? 0,
            'success_count' => $log?->success_count ?? 0,
            'failure_count' => $log?->failure_count ?? 0,
            'daily_limit' => $dailyLimit,
            'is_unlimited' => $dailyLimit <= 0,
            'remaining' => $remaining,
        ];
    }

    /**
     * Get a random delay between messages (in seconds).
     */
    public function getRandomDelay(): int
    {
        return random_int(
            $this->getConfig('min_delay_seconds', 10),
            $this->getConfig('max_delay_seconds', 30)
        );
    }

    /**
     * Check if we should pause for a batch break.
     */
    public function shouldPauseBatch(): bool
    {
        $this->messagesSentInBatch++;
        $batchSize = $this->getConfig('batch_size', 15);

        if ($this->messagesSentInBatch >= $batchSize) {
            $this->messagesSentInBatch = 0;

            return true;
        }

        return false;
    }

    /**
     * Get batch pause duration in seconds.
     */
    public function getBatchPauseDuration(): int
    {
        return $this->getConfig('batch_pause_minutes', 3) * 60;
    }

    /**
     * Format phone number to international format (Malaysian).
     */
    public function formatPhoneNumber(string $phone): string
    {
        // Remove all non-numeric characters
        $phone = preg_replace('/[^0-9]/', '', $phone);

        // Remove leading zeros
        $phone = ltrim($phone, '0');

        // If starts with 1 (Malaysian mobile), add 60
        if (strlen($phone) === 9 || strlen($phone) === 10) {
            if (str_starts_with($phone, '1')) {
                $phone = '60'.$phone;
            }
        }

        // If already has country code (60), ensure no leading +
        if (! str_starts_with($phone, '60') && strlen($phone) >= 11) {
            // Assume it might already have country code
            return $phone;
        }

        // Add 60 if not present
        if (! str_starts_with($phone, '60')) {
            $phone = '60'.$phone;
        }

        return $phone;
    }

    /**
     * Add subtle variation to message to make each unique.
     * This helps avoid detection as automated messages.
     * Only applies if message_variation_enabled is true in config.
     */
    public function addMessageVariation(string $message): string
    {
        // Check if message variation is enabled (disabled by default for safety)
        if (! $this->getConfig('message_variation_enabled', false)) {
            return $message;
        }

        // Add invisible zero-width characters at random positions
        $variations = [
            "\u{200B}", // Zero-width space
            "\u{200C}", // Zero-width non-joiner
            "\u{200D}", // Zero-width joiner
            "\u{FEFF}", // Zero-width no-break space
        ];

        // Add 1-3 random invisible characters at the end
        $count = random_int(1, 3);
        for ($i = 0; $i < $count; $i++) {
            $message .= $variations[array_rand($variations)];
        }

        return $message;
    }

    /**
     * Send a text message via WhatsApp, delegating to the active provider.
     */
    public function send(string $phoneNumber, string $message, string $type = 'text'): array
    {
        if (! $this->isEnabled()) {
            return [
                'success' => false,
                'error' => 'WhatsApp service is not enabled',
            ];
        }

        $formattedPhone = $this->formatPhoneNumber($phoneNumber);

        // Only apply message variation for onsend provider (anti-ban)
        if ($this->manager->getProviderName() === 'onsend') {
            $message = $this->addMessageVariation($message);
        }

        try {
            $result = $this->manager->provider()->send($formattedPhone, $message);
            $this->logSendAttempt($result['success']);

            return $result;
        } catch (\Exception $e) {
            $this->logSendAttempt(false);

            Log::error('WhatsApp send exception', [
                'phone' => $formattedPhone,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Send an image with optional caption, delegating to the active provider.
     */
    public function sendImage(string $phoneNumber, string $imageUrl, ?string $caption = null): array
    {
        if (! $this->isEnabled()) {
            return [
                'success' => false,
                'error' => 'WhatsApp service is not enabled',
            ];
        }

        $formattedPhone = $this->formatPhoneNumber($phoneNumber);

        // Only apply caption variation for onsend provider (anti-ban)
        if ($caption && $this->manager->getProviderName() === 'onsend') {
            $caption = $this->addMessageVariation($caption);
        }

        try {
            $result = $this->manager->provider()->sendImage($formattedPhone, $imageUrl, $caption);
            $this->logSendAttempt($result['success']);

            return $result;
        } catch (\Exception $e) {
            $this->logSendAttempt(false);

            Log::error('WhatsApp sendImage exception', [
                'phone' => $formattedPhone,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Send a document/file, delegating to the active provider.
     */
    public function sendDocument(string $phoneNumber, string $documentUrl, string $mimeType, ?string $filename = null): array
    {
        if (! $this->isEnabled()) {
            return [
                'success' => false,
                'error' => 'WhatsApp service is not enabled',
            ];
        }

        $formattedPhone = $this->formatPhoneNumber($phoneNumber);

        try {
            $result = $this->manager->provider()->sendDocument($formattedPhone, $documentUrl, $mimeType, $filename);
            $this->logSendAttempt($result['success']);

            return $result;
        } catch (\Exception $e) {
            $this->logSendAttempt(false);

            Log::error('WhatsApp sendDocument exception', [
                'phone' => $formattedPhone,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Send a template message, delegating to the active provider.
     */
    public function sendTemplate(string $phoneNumber, string $templateName, string $language, array $components = []): array
    {
        if (! $this->isEnabled()) {
            return [
                'success' => false,
                'error' => 'WhatsApp service is not enabled',
            ];
        }

        $formattedPhone = $this->formatPhoneNumber($phoneNumber);

        try {
            $result = $this->manager->provider()->sendTemplate($formattedPhone, $templateName, $language, $components);
            $this->logSendAttempt($result['success']);

            return $result;
        } catch (\Exception $e) {
            $this->logSendAttempt(false);

            Log::error('WhatsApp sendTemplate exception', [
                'phone' => $formattedPhone,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Log a send attempt for daily tracking.
     */
    protected function logSendAttempt(bool $success): void
    {
        $log = WhatsAppSendLog::firstOrCreate(
            ['send_date' => today()],
            ['message_count' => 0, 'success_count' => 0, 'failure_count' => 0]
        );

        $log->increment('message_count');

        if ($success) {
            $log->increment('success_count');
        } else {
            $log->increment('failure_count');
        }
    }

    /**
     * Get the next allowed send time if currently outside allowed hours.
     * Returns null if time restriction is disabled or within allowed hours.
     */
    public function getNextAllowedSendTime(): ?\Carbon\Carbon
    {
        // If time restriction is disabled, always return null (can send anytime)
        if (! $this->getConfig('time_restriction_enabled', false)) {
            return null;
        }

        $hour = now()->hour;
        $startHour = $this->getConfig('send_hours_start', 8);
        $endHour = $this->getConfig('send_hours_end', 22);

        if ($hour < $startHour) {
            // Before start hour today
            return now()->setTime($startHour, 0, 0);
        }

        if ($hour >= $endHour) {
            // After end hour, wait until tomorrow
            return now()->addDay()->setTime($startHour, 0, 0);
        }

        // Within allowed hours
        return null;
    }

    /**
     * Check if the device is connected and ready.
     */
    public function isDeviceConnected(): bool
    {
        $status = $this->checkDeviceStatus();

        return $status['success'] && $status['status'] === 'connected';
    }
}
