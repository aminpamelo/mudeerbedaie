<?php

namespace App\Services\Shipping;

use App\Services\SettingsService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * OAuth 2.0 (Authorization Code) client for the EasyParcel Open API.
 *
 * EasyParcel's new API has no API-key mode: an admin links their EasyParcel
 * account once via the hosted login, after which we hold a long-lived refresh
 * token and mint short-lived (10h) access tokens from it on demand. This service
 * owns the authorize URL, the code→token exchange, and transparent refreshing.
 *
 * @see https://easyparcel.github.io/OpenAPI/#oauth-authentications
 */
class EasyParcelOAuthService
{
    private const OAUTH_BASE = 'https://api.easyparcel.com';

    /** Refresh a little before the token actually expires. */
    private const EXPIRY_BUFFER_SECONDS = 120;

    public function __construct(private SettingsService $settings) {}

    /**
     * The exact callback URL EasyParcel must redirect back to — and which must
     * be registered as a Redirect URI on the Developer Hub app. Honours the
     * `EASYPARCEL_REDIRECT_URI` override (for tunnelled local testing), else the
     * app's own callback route. Single source of truth for both the authorize
     * redirect and the token exchange so they stay byte-identical.
     */
    public function redirectUri(): string
    {
        return config('services.easyparcel.redirect_uri') ?: route('admin.easyparcel.callback');
    }

    /**
     * The EasyParcel hosted-login URL to redirect the admin to so they can
     * authorize (and pick a Demo or Live account).
     */
    public function authorizeUrl(string $redirectUri, string $state): string
    {
        return self::OAUTH_BASE.'/oauth/login?'.http_build_query([
            'client_id' => (string) $this->settings->get('easyparcel_client_id'),
            'redirect_uri' => $redirectUri,
            'state' => $state,
        ]);
    }

    /**
     * Exchange the authorization code returned to our callback for tokens.
     */
    public function exchangeCode(string $code, string $redirectUri): bool
    {
        $token = $this->requestToken([
            'grant_type' => 'authorization_code',
            'redirect_uri' => $redirectUri,
            'code' => $code,
        ]);

        if (! $token || empty($token['access_token'])) {
            return false;
        }

        $this->settings->setEasyParcelTokens(
            $token['access_token'],
            $token['refresh_token'] ?? null,
            $this->resolveExpiry($token),
        );

        return true;
    }

    /**
     * A currently-valid access token, refreshing first if it has (nearly)
     * expired. Returns null when the account isn't linked or refresh fails.
     */
    public function accessToken(): ?string
    {
        if (! $this->settings->isEasyParcelConnected()) {
            return null;
        }

        if ($this->tokenIsFresh()) {
            return (string) $this->settings->get('easyparcel_access_token');
        }

        return $this->refresh()
            ? (string) $this->settings->get('easyparcel_access_token')
            : null;
    }

    /**
     * Mint a new access token from the stored refresh token.
     */
    public function refresh(): bool
    {
        $refreshToken = (string) $this->settings->get('easyparcel_refresh_token');

        if ($refreshToken === '') {
            return false;
        }

        $token = $this->requestToken([
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
        ]);

        if (! $token || empty($token['access_token'])) {
            Log::warning('EasyParcel token refresh failed', ['response' => $token]);

            return false;
        }

        $this->settings->setEasyParcelTokens(
            $token['access_token'],
            $token['refresh_token'] ?? $refreshToken,
            $this->resolveExpiry($token),
        );

        return true;
    }

    private function tokenIsFresh(): bool
    {
        $accessToken = (string) $this->settings->get('easyparcel_access_token');

        if ($accessToken === '') {
            return false;
        }

        $expiresAt = (string) $this->settings->get('easyparcel_token_expires_at');

        if ($expiresAt === '') {
            return false;
        }

        try {
            return CarbonImmutable::parse($expiresAt)->subSeconds(self::EXPIRY_BUFFER_SECONDS)->isFuture();
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * @param  array<string, mixed>  $token
     */
    private function resolveExpiry(array $token): ?string
    {
        if (! empty($token['expires_at'])) {
            return (string) $token['expires_at'];
        }

        if (! empty($token['expires_in'])) {
            return CarbonImmutable::now()->addSeconds((int) $token['expires_in'])->toIso8601String();
        }

        return null;
    }

    /**
     * POST to the token endpoint with HTTP Basic auth (client_id:client_secret).
     *
     * @param  array<string, string>  $body
     * @return array<string, mixed>|null
     */
    private function requestToken(array $body): ?array
    {
        $clientId = (string) $this->settings->get('easyparcel_client_id');
        $clientSecret = (string) $this->settings->get('easyparcel_client_secret');

        if ($clientId === '' || $clientSecret === '') {
            return null;
        }

        try {
            $response = Http::timeout(30)
                ->withBasicAuth($clientId, $clientSecret)
                ->asForm()
                ->post(self::OAUTH_BASE.'/oauth/token', $body);

            if (! $response->successful()) {
                Log::error('EasyParcel token endpoint error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return null;
            }

            return $response->json();
        } catch (\Throwable $e) {
            Log::error('EasyParcel token request failed', ['error' => $e->getMessage()]);

            return null;
        }
    }
}
