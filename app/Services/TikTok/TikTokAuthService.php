<?php

declare(strict_types=1);

namespace App\Services\TikTok;

use App\Models\Platform;
use App\Models\PlatformAccount;
use App\Models\PlatformApiCredential;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TikTokAuthService
{
    public function __construct(
        private TikTokClientFactory $clientFactory
    ) {}

    /**
     * Generate the OAuth authorization URL.
     */
    public function getAuthorizationUrl(?string $state = null): string
    {
        $client = $this->clientFactory->createBaseClient();
        $auth = $client->Auth();

        $state = $state ?? Str::random(32);

        return $auth->createAuthRequest($state);
    }

    /**
     * Handle the OAuth callback and exchange code for tokens.
     *
     * @return array{access_token: string, refresh_token: string, expires_in: int, refresh_expires_in: int}
     */
    public function handleCallback(string $code): array
    {
        $client = $this->clientFactory->createBaseClient();
        $auth = $client->Auth();

        try {
            $tokenResponse = $auth->getToken($code);

            if (! isset($tokenResponse['access_token'])) {
                throw new Exception('Invalid token response from TikTok');
            }

            return [
                'access_token' => $tokenResponse['access_token'],
                'refresh_token' => $tokenResponse['refresh_token'] ?? null,
                'expires_in' => $tokenResponse['access_token_expire_in'] ?? 86400,
                'refresh_expires_in' => $tokenResponse['refresh_token_expire_in'] ?? 31536000,
            ];
        } catch (Exception $e) {
            $this->clientFactory->logError('OAuth callback failed', [
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Get authorized shops for the given access token.
     *
     * @return array<array{shop_id: string, shop_name: string, region: string, shop_cipher: string}>
     */
    public function getAuthorizedShops(string $accessToken): array
    {
        $client = $this->clientFactory->createBaseClient();
        $client->setAccessToken($accessToken);

        try {
            $response = $client->Authorization->getAuthorizedShop();

            if (! isset($response['shops']) || ! is_array($response['shops'])) {
                return [];
            }

            return array_map(function ($shop) {
                return [
                    'shop_id' => $shop['id'] ?? $shop['shop_id'] ?? '',
                    'shop_name' => $shop['name'] ?? $shop['shop_name'] ?? '',
                    'region' => $shop['region'] ?? '',
                    'shop_cipher' => $shop['cipher'] ?? $shop['shop_cipher'] ?? '',
                    'seller_base_region' => $shop['seller_base_region'] ?? '',
                ];
            }, $response['shops']);
        } catch (Exception $e) {
            $this->clientFactory->logError('Failed to get authorized shops', [
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Create or update a platform account from OAuth response.
     */
    public function createOrUpdateAccount(
        User $user,
        array $tokenData,
        array $shopData
    ): PlatformAccount {
        $platform = Platform::where('slug', 'tiktok-shop')->firstOrFail();

        return DB::transaction(function () use ($platform, $user, $tokenData, $shopData) {
            // Find or create the platform account
            $account = PlatformAccount::updateOrCreate(
                [
                    'platform_id' => $platform->id,
                    'shop_id' => $shopData['shop_id'],
                ],
                [
                    'user_id' => $user->id,
                    'name' => $shopData['shop_name'] ?: 'TikTok Shop',
                    'account_id' => $shopData['shop_id'],
                    'seller_center_id' => $shopData['seller_base_region'] ?? null,
                    'country_code' => $shopData['region'] ?? null,
                    'currency' => $this->getCurrencyForRegion($shopData['region'] ?? ''),
                    'metadata' => [
                        'shop_cipher' => $shopData['shop_cipher'],
                        'region' => $shopData['region'],
                        'seller_base_region' => $shopData['seller_base_region'] ?? null,
                        'connected_via' => 'oauth',
                        'api_version' => $this->clientFactory->getApiVersion(),
                    ],
                    'permissions' => $tokenData['scopes'] ?? [],
                    'connected_at' => now(),
                    'is_active' => true,
                    'auto_sync_orders' => true,
                    'auto_sync_products' => false,
                ]
            );

            // Store the API credentials
            $this->storeCredentials($account, $tokenData);

            Log::info('[TikTok] Account connected successfully', [
                'account_id' => $account->id,
                'shop_id' => $shopData['shop_id'],
                'shop_name' => $shopData['shop_name'],
            ]);

            return $account;
        });
    }

    /**
     * Link an existing platform account with OAuth credentials.
     */
    public function linkExistingAccount(
        int $accountId,
        array $tokenData,
        array $shopData
    ): PlatformAccount {
        $account = PlatformAccount::findOrFail($accountId);

        // Verify the account belongs to TikTok Shop platform
        if ($account->platform->slug !== 'tiktok-shop') {
            throw new Exception('Account does not belong to TikTok Shop platform');
        }

        // Check if this TikTok shop is already linked to another account
        $existingAccount = PlatformAccount::where('platform_id', $account->platform_id)
            ->where('shop_id', $shopData['shop_id'])
            ->where('id', '!=', $accountId)
            ->first();

        if ($existingAccount) {
            Log::warning('[TikTok] Shop already linked to another account', [
                'shop_id' => $shopData['shop_id'],
                'shop_name' => $shopData['shop_name'],
                'existing_account_id' => $existingAccount->id,
                'existing_account_name' => $existingAccount->name,
                'requested_account_id' => $accountId,
                'requested_account_name' => $account->name,
            ]);

            throw new Exception(
                "This TikTok Shop '{$shopData['shop_name']}' is already linked to account '{$existingAccount->name}'. ".
                'Please unlink it from that account first, or select a different shop.'
            );
        }

        // Also check by account_id (which stores shop_id) for the unique constraint
        $existingByAccountId = PlatformAccount::where('platform_id', $account->platform_id)
            ->where('account_id', $shopData['shop_id'])
            ->where('id', '!=', $accountId)
            ->first();

        if ($existingByAccountId) {
            Log::warning('[TikTok] Shop already linked to another account (by account_id)', [
                'shop_id' => $shopData['shop_id'],
                'existing_account_id' => $existingByAccountId->id,
                'existing_account_name' => $existingByAccountId->name,
            ]);

            throw new Exception(
                "This TikTok Shop '{$shopData['shop_name']}' is already linked to account '{$existingByAccountId->name}'. ".
                'Please unlink it from that account first, or select a different shop.'
            );
        }

        return DB::transaction(function () use ($account, $tokenData, $shopData) {
            // Update the account with OAuth data
            $account->update([
                'shop_id' => $shopData['shop_id'],
                'account_id' => $shopData['shop_id'],
                'seller_center_id' => $shopData['seller_base_region'] ?? $account->seller_center_id,
                'country_code' => $shopData['region'] ?? $account->country_code,
                'currency' => $this->getCurrencyForRegion($shopData['region'] ?? ''),
                'metadata' => array_merge($account->metadata ?? [], [
                    'shop_cipher' => $shopData['shop_cipher'],
                    'region' => $shopData['region'],
                    'seller_base_region' => $shopData['seller_base_region'] ?? null,
                    'connected_via' => 'oauth',
                    'linked_at' => now()->toIso8601String(),
                    'api_version' => $this->clientFactory->getApiVersion(),
                ]),
                'permissions' => $tokenData['scopes'] ?? [],
                'connected_at' => now(),
                'is_active' => true,
                'auto_sync_orders' => true,
            ]);

            // Store the API credentials
            $this->storeCredentials($account, $tokenData);

            Log::info('[TikTok] Existing account linked successfully', [
                'account_id' => $account->id,
                'account_name' => $account->name,
                'shop_id' => $shopData['shop_id'],
                'shop_name' => $shopData['shop_name'],
            ]);

            return $account;
        });
    }

    /**
     * Refresh the access token for an account.
     */
    public function refreshToken(PlatformAccount $account): bool
    {
        $credential = $account->credentials()
            ->where('credential_type', 'oauth_token')
            ->where('is_active', true)
            ->first();

        if (! $credential) {
            $this->clientFactory->logError('No active credential found for refresh', [
                'account_id' => $account->id,
            ]);

            return false;
        }

        $refreshToken = $credential->getRefreshToken();
        if (! $refreshToken) {
            $this->clientFactory->logError('No refresh token available', [
                'account_id' => $account->id,
            ]);

            return false;
        }

        try {
            $client = $this->clientFactory->createBaseClient();
            $auth = $client->Auth();

            $tokenResponse = $auth->refreshNewToken($refreshToken);

            if (! isset($tokenResponse['access_token'])) {
                throw new Exception('Invalid refresh token response');
            }

            // Update the credential with new tokens
            $credential->setValue($tokenResponse['access_token']);
            $credential->setRefreshToken($tokenResponse['refresh_token'] ?? $refreshToken);
            $credential->expires_at = $this->calculateExpiryDate(
                $tokenResponse['access_token_expire_in'] ?? 86400
            );
            $credential->metadata = array_merge($credential->metadata ?? [], [
                'last_refresh' => now()->toIso8601String(),
                'refresh_count' => ($credential->metadata['refresh_count'] ?? 0) + 1,
            ]);
            $credential->save();

            Log::info('[TikTok] Token refreshed successfully', [
                'account_id' => $account->id,
                'expires_at' => $credential->expires_at,
            ]);

            return true;
        } catch (Exception $e) {
            $this->clientFactory->logError('Token refresh failed', [
                'account_id' => $account->id,
                'error' => $e->getMessage(),
                'credential_id' => $credential->id,
                'expires_at' => $credential->expires_at?->toIso8601String(),
            ]);

            // Don't mark credential as inactive on refresh failure
            // The credential might still be valid, and marking it inactive
            // would prevent all future sync attempts until re-authentication.
            // Only mark inactive if the refresh token itself is expired/revoked.

            return false;
        }
    }

    /**
     * Check if an account needs token refresh.
     *
     * TikTok access tokens typically expire in ~24 hours.
     * We only refresh when the token is close to expiring (within 1 hour)
     * or already expired, not days in advance.
     */
    public function needsTokenRefresh(PlatformAccount $account): bool
    {
        $credential = $account->credentials()
            ->where('credential_type', 'oauth_token')
            ->where('is_active', true)
            ->first();

        if (! $credential || ! $credential->expires_at) {
            return true;
        }

        // Check if token is already expired
        if ($credential->isExpired()) {
            return true;
        }

        // Only refresh if expiring within 1 hour (access tokens last ~24 hours)
        // Don't use the 7-day config - that's for refresh tokens, not access tokens
        $hoursBeforeExpiry = config('services.tiktok.token_refresh_hours_before_expiry', 1);

        return $credential->expires_at->isBefore(now()->addHours($hoursBeforeExpiry));
    }

    /**
     * Disconnect an account (revoke tokens and deactivate).
     */
    public function disconnectAccount(PlatformAccount $account): void
    {
        DB::transaction(function () use ($account) {
            // Deactivate all credentials
            $account->credentials()->update(['is_active' => false]);

            // Update account status
            $account->update([
                'is_active' => false,
                'auto_sync_orders' => false,
                'auto_sync_products' => false,
                'metadata' => array_merge($account->metadata ?? [], [
                    'disconnected_at' => now()->toIso8601String(),
                ]),
            ]);

            Log::info('[TikTok] Account disconnected', [
                'account_id' => $account->id,
            ]);
        });
    }

    /**
     * Store OAuth credentials for an account.
     */
    private function storeCredentials(PlatformAccount $account, array $tokenData): PlatformApiCredential
    {
        // Deactivate any existing credentials
        $account->credentials()
            ->where('credential_type', 'oauth_token')
            ->update(['is_active' => false]);

        // Create new credential
        $credential = new PlatformApiCredential([
            'platform_id' => $account->platform_id,
            'platform_account_id' => $account->id,
            'credential_type' => 'oauth_token',
            'name' => 'TikTok OAuth Token',
            'metadata' => [
                'created_via' => 'oauth_callback',
                'api_version' => $this->clientFactory->getApiVersion(),
            ],
            'scopes' => $tokenData['scopes'] ?? [],
            'expires_at' => $this->calculateExpiryDate($tokenData['expires_in'] ?? 86400),
            'is_active' => true,
            'auto_refresh' => true,
        ]);

        $credential->setValue($tokenData['access_token']);

        if (! empty($tokenData['refresh_token'])) {
            $credential->setRefreshToken($tokenData['refresh_token']);
        }

        $credential->save();

        return $credential;
    }

    /**
     * Get the currency code for a TikTok region.
     */
    private function getCurrencyForRegion(string $region): string
    {
        $regionCurrencies = [
            'MY' => 'MYR',
            'SG' => 'SGD',
            'TH' => 'THB',
            'VN' => 'VND',
            'PH' => 'PHP',
            'ID' => 'IDR',
            'US' => 'USD',
            'UK' => 'GBP',
            'GB' => 'GBP',
        ];

        return $regionCurrencies[strtoupper($region)] ?? 'USD';
    }

    /**
     * Calculate expiry date from TikTok's expires_in value.
     *
     * TikTok sometimes returns a UNIX timestamp instead of seconds until expiry.
     * This method handles both cases.
     */
    private function calculateExpiryDate(int $expiresIn): \Carbon\Carbon
    {
        $oneYearInSeconds = 31536000;

        if ($expiresIn > $oneYearInSeconds) {
            // This is likely a UNIX timestamp, convert to Carbon
            $expiresAt = \Carbon\Carbon::createFromTimestamp($expiresIn);

            // Safety check: if in the past or too far in future, use default
            if ($expiresAt->isPast() || $expiresAt->isAfter(now()->addYears(2))) {
                Log::warning('[TikTok] Invalid expires_in value, using default', [
                    'original_value' => $expiresIn,
                    'interpreted_as' => $expiresAt->toIso8601String(),
                ]);

                return now()->addDay(); // Default to 1 day
            }

            return $expiresAt;
        }

        return now()->addSeconds($expiresIn);
    }
}
