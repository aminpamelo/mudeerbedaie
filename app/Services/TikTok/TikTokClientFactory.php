<?php

declare(strict_types=1);

namespace App\Services\TikTok;

use App\Models\PlatformAccount;
use App\Models\PlatformApiCredential;
use EcomPHP\TiktokShop\Client;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class TikTokClientFactory
{
    private string $appKey;

    private string $appSecret;

    public function __construct()
    {
        $this->appKey = config('services.tiktok.app_key', '');
        $this->appSecret = config('services.tiktok.app_secret', '');
    }

    /**
     * Create a base TikTok client without account-specific credentials.
     * Used for OAuth initialization.
     */
    public function createBaseClient(): Client
    {
        $this->validateAppCredentials();

        return new Client($this->appKey, $this->appSecret);
    }

    /**
     * Create an authenticated TikTok client for a specific platform account.
     */
    public function createClientForAccount(PlatformAccount $account): Client
    {
        $this->validateAppCredentials();

        $credential = $this->getActiveCredential($account);

        if (! $credential) {
            throw new RuntimeException(
                "No active TikTok API credentials found for account: {$account->name}"
            );
        }

        $accessToken = $credential->getValue();
        if (! $accessToken) {
            throw new RuntimeException(
                "Unable to decrypt access token for account: {$account->name}"
            );
        }

        $client = new Client($this->appKey, $this->appSecret);
        $client->setAccessToken($accessToken);

        // Set shop cipher if available
        $shopCipher = $account->metadata['shop_cipher'] ?? null;
        if ($shopCipher) {
            $client->setShopCipher($shopCipher);
        }

        // Mark credential as used for tracking
        $credential->markAsUsed();

        return $client;
    }

    /**
     * Check if the TikTok integration is properly configured.
     */
    public function isConfigured(): bool
    {
        return ! empty($this->appKey) && ! empty($this->appSecret);
    }

    /**
     * Get the OAuth redirect URI.
     */
    public function getRedirectUri(): string
    {
        $redirectUri = config('services.tiktok.redirect_uri');

        if (empty($redirectUri)) {
            // Generate default redirect URI
            return url('/tiktok/callback');
        }

        return $redirectUri;
    }

    /**
     * Get the configured API version.
     */
    public function getApiVersion(): string
    {
        return config('services.tiktok.api_version', '202309');
    }

    /**
     * Check if sandbox mode is enabled.
     */
    public function isSandbox(): bool
    {
        return (bool) config('services.tiktok.sandbox', false);
    }

    /**
     * Get the active credential for an account.
     */
    private function getActiveCredential(PlatformAccount $account): ?PlatformApiCredential
    {
        return $account->credentials()
            ->where('credential_type', 'oauth_token')
            ->where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->orderByDesc('created_at')
            ->first();
    }

    /**
     * Validate that app credentials are configured.
     */
    private function validateAppCredentials(): void
    {
        if (empty($this->appKey)) {
            throw new RuntimeException(
                'TikTok App Key not configured. Set TIKTOK_APP_KEY in .env file.'
            );
        }

        if (empty($this->appSecret)) {
            throw new RuntimeException(
                'TikTok App Secret not configured. Set TIKTOK_APP_SECRET in .env file.'
            );
        }
    }

    /**
     * Log API errors for debugging.
     */
    public function logError(string $message, array $context = []): void
    {
        Log::channel('daily')->error("[TikTok API] {$message}", array_merge($context, [
            'app_key' => substr($this->appKey, 0, 8).'...',
            'sandbox' => $this->isSandbox(),
        ]));
    }
}
