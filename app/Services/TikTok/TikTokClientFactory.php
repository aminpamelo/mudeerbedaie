<?php

declare(strict_types=1);

namespace App\Services\TikTok;

use App\Exceptions\MissingPlatformAppConnectionException;
use App\Models\PlatformAccount;
use App\Models\PlatformApiCredential;
use App\Models\PlatformApp;
use EcomPHP\TiktokShop\Client;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class TikTokClientFactory
{
    /**
     * Create an unauthenticated client using the app's keys for OAuth bootstrap.
     */
    public function createBaseClient(PlatformApp $app): Client
    {
        if (empty($app->app_key)) {
            throw new RuntimeException(
                "PlatformApp #{$app->id} is missing app_key."
            );
        }

        if (empty($app->encrypted_app_secret)) {
            throw new RuntimeException(
                "PlatformApp #{$app->id} is missing app_secret."
            );
        }

        $appSecret = $app->getAppSecret();

        if (empty($appSecret)) {
            throw new RuntimeException(
                "PlatformApp #{$app->id} app_secret could not be decrypted (likely an APP_KEY rotation issue)."
            );
        }

        return new Client($app->app_key, $appSecret);
    }

    /**
     * Create an authenticated client for a specific account + app category.
     */
    public function createClientForAccount(PlatformAccount $account, string $category): Client
    {
        $app = $this->resolveApp($account, $category);
        $credential = $this->resolveCredential($account, $app);

        $accessToken = $credential->getValue();

        if (! $accessToken) {
            if (! empty($credential->encrypted_value)) {
                throw new RuntimeException(
                    "Access token for account #{$account->id} (app category: {$category}) could not be decrypted — likely an APP_KEY rotation issue. Reconnect the account."
                );
            }

            throw new RuntimeException(
                "Access token for account #{$account->id} (app category: {$category}) is missing."
            );
        }

        $client = $this->createBaseClient($app);
        $client->setAccessToken($accessToken);

        $shopCipher = $account->metadata['shop_cipher'] ?? null;
        if ($shopCipher) {
            $client->setShopCipher($shopCipher);
        }

        $credential->markAsUsed();

        return $client;
    }

    public function resolveApp(PlatformAccount $account, string $category): PlatformApp
    {
        $app = PlatformApp::query()
            ->where('platform_id', $account->platform_id)
            ->where('category', $category)
            ->where('is_active', true)
            ->first();

        if (! $app) {
            throw new MissingPlatformAppConnectionException(
                $account,
                $category,
                "No active PlatformApp registered for platform_id={$account->platform_id}, category='{$category}'. Register the app under Platform Management → Apps."
            );
        }

        return $app;
    }

    public function resolveCredential(PlatformAccount $account, PlatformApp $app): PlatformApiCredential
    {
        $credential = $account->credentials()
            ->where('platform_app_id', $app->id)
            ->where('credential_type', 'oauth_token')
            ->where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->orderByDesc('created_at')
            ->first();

        if (! $credential) {
            throw new MissingPlatformAppConnectionException($account, $app->category);
        }

        return $credential;
    }

    public function getRedirectUri(?PlatformApp $app = null): string
    {
        if ($app && ! empty($app->redirect_uri)) {
            return $app->redirect_uri;
        }

        $configured = config('services.tiktok.redirect_uri');

        return $configured ?: url('/tiktok/callback');
    }

    public function getApiVersion(): string
    {
        return config('services.tiktok.api_version', '202309');
    }

    public function isSandbox(): bool
    {
        return (bool) config('services.tiktok.sandbox', false);
    }

    public function logError(string $message, array $context = []): void
    {
        Log::channel('daily')->error("[TikTok API] {$message}", array_merge($context, [
            'sandbox' => $this->isSandbox(),
        ]));
    }
}
