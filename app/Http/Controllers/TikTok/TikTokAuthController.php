<?php

declare(strict_types=1);

namespace App\Http\Controllers\TikTok;

use App\Http\Controllers\Controller;
use App\Models\Platform;
use App\Models\PlatformAccount;
use App\Models\PlatformApp;
use App\Models\User;
use App\Services\TikTok\TikTokAuthService;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TikTokAuthController extends Controller
{
    public function __construct(
        private TikTokAuthService $authService
    ) {}

    /**
     * Initiate the OAuth flow - redirect to TikTok authorization.
     *
     * @param  Request  $request  Accepts optional 'link_account' query param to link existing account
     *                            and optional 'app' query param (slug or id) to pick which TikTok app
     *                            to authorize. Defaults to 'tiktok-multi-channel'.
     */
    public function redirect(Request $request): RedirectResponse
    {
        $platform = Platform::where('slug', 'tiktok-shop')->firstOrFail();

        $appIdentifier = $request->get('app', 'tiktok-multi-channel');
        $app = PlatformApp::where('platform_id', $platform->id)
            ->where(function ($q) use ($appIdentifier) {
                $q->where('slug', $appIdentifier)->orWhere('id', $appIdentifier);
            })
            ->where('is_active', true)
            ->first();

        if (! $app) {
            return redirect()
                ->route('platforms.index')
                ->with('error', "TikTok app '{$appIdentifier}' is not registered. Register it under Platform Management → Apps first.");
        }

        // Generate a unique state with user ID, link_account_id, and platform_app_id embedded
        // This is necessary for cross-domain OAuth (via Expose/ngrok) where sessions don't persist
        $csrfToken = Str::random(40);
        $userId = auth()->id();
        $linkAccountId = null;

        // If linking an existing account, validate and store the account ID
        if ($request->has('link_account')) {
            $linkAccountId = (int) $request->get('link_account');
            $existingAccount = PlatformAccount::find($linkAccountId);

            if ($existingAccount && $existingAccount->platform->slug === 'tiktok-shop') {
                Log::info('[TikTok OAuth] Linking existing account', [
                    'account_id' => $linkAccountId,
                    'account_name' => $existingAccount->name,
                ]);
            } else {
                // Invalid account, don't include in state
                $linkAccountId = null;
            }
        }

        // Encode state as: csrfToken.base64(encryptedPayload)
        // Payload contains user_id, link_account_id, and platform_app_id to survive cross-domain redirect
        $payload = json_encode([
            'user_id' => $userId,
            'link_account_id' => $linkAccountId,
            'platform_app_id' => $app->id,
        ]);
        $encryptedPayload = Crypt::encryptString($payload);
        $state = $csrfToken.'.'.base64_encode($encryptedPayload);

        // Store CSRF token in session for validation (as backup if session persists)
        session([
            'tiktok_oauth_state' => $csrfToken,
            'tiktok_oauth_user_id' => $userId,
            'tiktok_link_account_id' => $linkAccountId,
            'tiktok_oauth_app_id' => $app->id,
        ]);

        // Get the authorization URL
        try {
            $authUrl = $this->authService->getAuthorizationUrl($app, $state);

            Log::info('[TikTok OAuth] Redirecting to authorization', [
                'csrf_token' => $csrfToken,
                'user_id' => $userId,
                'link_account_id' => $linkAccountId,
                'platform_app_id' => $app->id,
            ]);

            return redirect()->away($authUrl);
        } catch (Exception $e) {
            Log::error('[TikTok OAuth] Failed to generate authorization URL', [
                'error' => $e->getMessage(),
                'platform_app_id' => $app->id,
            ]);

            return redirect()
                ->route('platforms.index')
                ->with('error', 'Failed to connect to TikTok: '.$e->getMessage());
        }
    }

    /**
     * Handle the OAuth callback from TikTok.
     */
    public function callback(Request $request): RedirectResponse
    {
        // Check for errors from TikTok
        if ($request->has('error')) {
            Log::warning('[TikTok OAuth] Authorization denied', [
                'error' => $request->get('error'),
                'error_description' => $request->get('error_description'),
            ]);

            return redirect()
                ->route('platforms.index')
                ->with('error', 'TikTok authorization was denied: '.$request->get('error_description', 'Unknown error'));
        }

        // Validate the authorization code
        $code = $request->get('code');
        if (empty($code)) {
            return redirect()
                ->route('platforms.index')
                ->with('error', 'No authorization code received from TikTok.');
        }

        // Parse state to extract CSRF token, user ID, link_account_id, and platform_app_id
        // State format: csrfToken.base64(encryptedPayload)
        // Payload: {user_id: int, link_account_id: int|null, platform_app_id: int|null}
        $state = $request->get('state');
        $user = null;
        $linkAccountId = null;
        $platformAppId = null;

        if ($state && str_contains($state, '.')) {
            [$csrfToken, $encodedPayload] = explode('.', $state, 2);

            // Try to extract payload from state (for cross-domain OAuth via Expose/ngrok)
            try {
                $encryptedPayload = base64_decode($encodedPayload);
                $payloadJson = Crypt::decryptString($encryptedPayload);
                $payload = json_decode($payloadJson, true);

                if (is_array($payload)) {
                    // New format with JSON payload
                    $userId = (int) ($payload['user_id'] ?? 0);
                    $linkAccountId = $payload['link_account_id'] ?? null;
                    $platformAppId = $payload['platform_app_id'] ?? null;
                    $user = User::find($userId);

                    Log::info('[TikTok OAuth] Extracted payload from state', [
                        'user_id' => $userId,
                        'user_found' => $user !== null,
                        'link_account_id' => $linkAccountId,
                        'platform_app_id' => $platformAppId,
                    ]);
                } else {
                    // Legacy format - payload was just the user ID string
                    $userId = (int) $payloadJson;
                    $user = User::find($userId);

                    Log::info('[TikTok OAuth] Extracted user from legacy state format', [
                        'user_id' => $userId,
                        'user_found' => $user !== null,
                    ]);
                }
            } catch (Exception $e) {
                Log::warning('[TikTok OAuth] Failed to decrypt payload from state', [
                    'error' => $e->getMessage(),
                ]);
            }

            // Validate CSRF token against session (if session is available)
            $sessionState = session('tiktok_oauth_state');
            if ($sessionState && $csrfToken !== $sessionState) {
                Log::warning('[TikTok OAuth] CSRF token mismatch', [
                    'expected' => $sessionState,
                    'received' => $csrfToken,
                ]);
                // Don't fail if we have a valid user from state - cross-domain sessions don't persist
            }
        } else {
            // Legacy state format (just CSRF token)
            $sessionState = session('tiktok_oauth_state');
            if ($sessionState && $state !== $sessionState) {
                Log::warning('[TikTok OAuth] State mismatch', [
                    'expected' => $sessionState,
                    'received' => $state,
                ]);

                return redirect()
                    ->route('platforms.index')
                    ->with('error', 'Invalid OAuth state. Please try again.');
            }
        }

        // Fall back to session values if state decryption failed
        if (! $user) {
            $user = auth()->user();
        }

        if (! $user) {
            $sessionUserId = session('tiktok_oauth_user_id');
            if ($sessionUserId) {
                $user = User::find($sessionUserId);
            }
        }

        // Fall back to session for link_account_id if not in state
        if ($linkAccountId === null) {
            $linkAccountId = session('tiktok_link_account_id');
        }

        if (! $user) {
            Log::error('[TikTok OAuth] No user found for callback');

            return redirect()
                ->route('platforms.index')
                ->with('error', 'Session expired. Please log in and try again.');
        }

        // Resolve PlatformApp from state payload, falling back to session
        if (! $platformAppId) {
            $platformAppId = session('tiktok_oauth_app_id');
        }

        $app = $platformAppId
            ? PlatformApp::find($platformAppId)
            : null;

        if (! $app) {
            Log::error('[TikTok OAuth] No PlatformApp resolved for callback', [
                'platform_app_id' => $platformAppId,
            ]);

            return redirect()
                ->route('platforms.index')
                ->with('error', 'OAuth callback could not identify which TikTok app this connection is for. Please retry from the Platform page.');
        }

        // Clear the state from session
        session()->forget(['tiktok_oauth_state', 'tiktok_oauth_user_id', 'tiktok_link_account_id']);

        try {
            // Exchange code for tokens
            $tokenData = $this->authService->handleCallback($app, $code);

            // Store tokens, user, link_account_id, and app_id temporarily in session for shop selection
            session([
                'tiktok_token_data' => $tokenData,
                'tiktok_auth_timestamp' => now()->timestamp,
                'tiktok_auth_user_id' => $user->id,
                'tiktok_link_account_id' => $linkAccountId,
                'tiktok_oauth_app_id' => $app->id,
            ]);

            Log::info('[TikTok OAuth] Tokens stored in session', [
                'user_id' => $user->id,
                'link_account_id' => $linkAccountId,
                'platform_app_id' => $app->id,
            ]);

            // When linking an existing account, the seller has already chosen the target shop
            // on TikTok's consent screen, and we already know the shop from the account record.
            // Skip the getAuthorizedShop API call — apps with scope-restricted categories (e.g.
            // Analytics & Reporting) are denied access to that endpoint.
            if ($linkAccountId) {
                $existing = PlatformAccount::find($linkAccountId);

                if ($existing && $existing->shop_id) {
                    $metadata = $existing->metadata ?? [];

                    $shopData = [
                        'shop_id' => $existing->shop_id,
                        'shop_name' => $existing->name,
                        'region' => $metadata['region'] ?? $existing->country_code ?? '',
                        'shop_cipher' => $metadata['shop_cipher'] ?? '',
                        'seller_base_region' => $metadata['seller_base_region'] ?? '',
                    ];

                    return $this->connectShop($shopData, $user, $linkAccountId, $app);
                }
            }

            // Get authorized shops (used when creating a new account)
            $shops = $this->authService->getAuthorizedShops($app, $tokenData['access_token']);

            if (empty($shops)) {
                Log::warning('[TikTok OAuth] No shops found for authorized account');

                return redirect()
                    ->route('platforms.index')
                    ->with('error', 'No TikTok Shop found for this account. Please ensure you have a TikTok Shop set up.');
            }

            // If only one shop, connect it directly
            if (count($shops) === 1) {
                return $this->connectShop($shops[0], $user, $linkAccountId, $app);
            }

            // Multiple shops - store in session and redirect to selection page
            session(['tiktok_available_shops' => $shops]);

            return redirect()->route('tiktok.select-shop');
        } catch (Exception $e) {
            Log::error('[TikTok OAuth] Callback processing failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()
                ->route('platforms.index')
                ->with('error', 'Failed to complete TikTok authorization: '.$e->getMessage());
        }
    }

    /**
     * Show shop selection page when user has multiple shops.
     */
    public function selectShop(Request $request)
    {
        $shops = session('tiktok_available_shops', []);
        $tokenData = session('tiktok_token_data');

        if (empty($shops) || empty($tokenData)) {
            return redirect()
                ->route('platforms.index')
                ->with('error', 'Session expired. Please try connecting again.');
        }

        // Get the TikTok platform
        $platform = Platform::where('slug', 'tiktok-shop')->first();

        // Get the linking account info if we're linking an existing account
        $linkAccountId = session('tiktok_link_account_id');
        $linkingAccount = null;
        $bestMatchIndex = 0;
        $linkedShops = [];

        // Check which shops are already linked to accounts
        if ($platform) {
            $shopIds = array_column($shops, 'shop_id');
            $existingAccounts = PlatformAccount::where('platform_id', $platform->id)
                ->whereIn('shop_id', $shopIds)
                ->when($linkAccountId, function ($query) use ($linkAccountId) {
                    // Exclude the account being linked (it's OK to re-link to itself)
                    return $query->where('id', '!=', $linkAccountId);
                })
                ->get()
                ->keyBy('shop_id');

            foreach ($existingAccounts as $shopId => $account) {
                $linkedShops[$shopId] = $account->name;
            }
        }

        if ($linkAccountId) {
            $linkingAccount = PlatformAccount::find($linkAccountId);

            // Try to find the best matching shop based on name similarity
            if ($linkingAccount) {
                $accountName = strtolower(trim($linkingAccount->name));
                $highestSimilarity = 0;

                foreach ($shops as $index => $shop) {
                    // Skip shops that are already linked to other accounts
                    if (isset($linkedShops[$shop['shop_id'] ?? ''])) {
                        continue;
                    }

                    $shopName = strtolower(trim($shop['shop_name'] ?? ''));

                    // Calculate similarity
                    similar_text($accountName, $shopName, $similarity);

                    // Also check for substring matches
                    if (str_contains($shopName, $accountName) || str_contains($accountName, $shopName)) {
                        $similarity = max($similarity, 70); // Boost for substring match
                    }

                    if ($similarity > $highestSimilarity) {
                        $highestSimilarity = $similarity;
                        $bestMatchIndex = $index;
                    }
                }

                Log::info('[TikTok OAuth] Shop selection - best match found', [
                    'account_name' => $linkingAccount->name,
                    'best_match_index' => $bestMatchIndex,
                    'best_match_shop' => $shops[$bestMatchIndex]['shop_name'] ?? 'Unknown',
                    'similarity' => $highestSimilarity,
                ]);
            }
        }

        return view('livewire.admin.platforms.tiktok.select-shop', [
            'shops' => $shops,
            'linkingAccount' => $linkingAccount,
            'bestMatchIndex' => $bestMatchIndex,
            'linkedShops' => $linkedShops,
        ]);
    }

    /**
     * Handle shop selection and create the account.
     */
    public function confirmShop(Request $request): RedirectResponse
    {
        $request->validate([
            'shop_index' => 'required|integer|min:0',
        ]);

        $shops = session('tiktok_available_shops', []);
        $shopIndex = (int) $request->get('shop_index');

        if (! isset($shops[$shopIndex])) {
            return redirect()
                ->route('platforms.index')
                ->with('error', 'Invalid shop selection.');
        }

        $appId = session('tiktok_oauth_app_id');
        $app = $appId ? PlatformApp::find($appId) : null;

        if (! $app) {
            return redirect()
                ->route('platforms.index')
                ->with('error', 'OAuth session expired. Please try connecting again.');
        }

        return $this->connectShop($shops[$shopIndex], null, null, $app);
    }

    /**
     * Connect a shop and create the platform account.
     *
     * @param  array  $shopData  Shop data from TikTok API
     * @param  User|null  $user  User to associate with the account
     * @param  int|null  $linkAccountId  Existing account ID to link (if linking instead of creating)
     * @param  PlatformApp|null  $app  PlatformApp providing the OAuth credentials
     */
    private function connectShop(
        array $shopData,
        ?User $user = null,
        ?int $linkAccountId = null,
        ?PlatformApp $app = null
    ): RedirectResponse {
        $tokenData = session('tiktok_token_data');

        if (empty($tokenData)) {
            return redirect()
                ->route('platforms.index')
                ->with('error', 'Session expired. Please try connecting again.');
        }

        // Get user from parameter, session, or auth
        if (! $user) {
            $userId = session('tiktok_auth_user_id');
            $user = $userId ? User::find($userId) : auth()->user();
        }

        if (! $user) {
            return redirect()
                ->route('platforms.index')
                ->with('error', 'Session expired. Please log in and try again.');
        }

        // Fall back to session for linkAccountId if not passed as parameter
        if ($linkAccountId === null) {
            $linkAccountId = session('tiktok_link_account_id');
        }

        // Resolve PlatformApp from session if not passed
        if (! $app) {
            $appId = session('tiktok_oauth_app_id');
            $app = $appId ? PlatformApp::find($appId) : null;
        }

        if (! $app) {
            return redirect()
                ->route('platforms.index')
                ->with('error', 'OAuth session expired. Please try connecting again.');
        }

        try {
            if ($linkAccountId) {
                // Link existing account
                $account = $this->authService->linkExistingAccount(
                    $linkAccountId,
                    $app,
                    $tokenData,
                    $shopData
                );
                $successMessage = "TikTok Shop '{$shopData['shop_name']}' has been linked to your existing account successfully!";
            } else {
                // Create new account
                $account = $this->authService->createOrUpdateAccount(
                    $user,
                    $app,
                    $tokenData,
                    $shopData
                );
                $successMessage = "TikTok Shop '{$shopData['shop_name']}' connected successfully!";
            }

            // Clear session data
            session()->forget([
                'tiktok_token_data',
                'tiktok_available_shops',
                'tiktok_auth_timestamp',
                'tiktok_auth_user_id',
                'tiktok_link_account_id',
                'tiktok_oauth_app_id',
            ]);

            Log::info('[TikTok OAuth] Account connected successfully', [
                'account_id' => $account->id,
                'shop_name' => $shopData['shop_name'],
                'linked_existing' => $linkAccountId !== null,
            ]);

            return redirect()
                ->route('platforms.accounts.show', ['platform' => 'tiktok-shop', 'account' => $account->id])
                ->with('success', $successMessage);
        } catch (Exception $e) {
            Log::error('[TikTok OAuth] Failed to create account', [
                'error' => $e->getMessage(),
                'shop_data' => $shopData,
                'linking_account' => $linkAccountId,
            ]);

            return redirect()
                ->route('platforms.index')
                ->with('error', 'Failed to connect TikTok Shop: '.$e->getMessage());
        }
    }

    /**
     * Disconnect a TikTok account.
     *
     * Accepts an optional 'app' query parameter (slug) to scope the disconnect to a single
     * PlatformApp's credentials, leaving credentials for other apps intact.
     */
    public function disconnect(Request $request, int $accountId): RedirectResponse
    {
        try {
            $account = PlatformAccount::findOrFail($accountId);

            // Verify the account belongs to TikTok platform
            if ($account->platform->slug !== 'tiktok-shop') {
                return redirect()
                    ->back()
                    ->with('error', 'Invalid account.');
            }

            $appSlug = $request->get('app');
            $app = $appSlug
                ? PlatformApp::where('platform_id', $account->platform_id)
                    ->where('slug', $appSlug)
                    ->first()
                : null;

            if ($app) {
                $account->credentials()
                    ->where('platform_app_id', $app->id)
                    ->update(['is_active' => false]);
                $message = "Disconnected '{$app->name}' from {$account->name}.";
            } else {
                $this->authService->disconnectAccount($account);
                $message = "TikTok Shop '{$account->name}' has been disconnected.";
            }

            return redirect()
                ->route('platforms.accounts.show', ['platform' => 'tiktok-shop', 'account' => $account->id])
                ->with('success', $message);
        } catch (Exception $e) {
            Log::error('[TikTok OAuth] Failed to disconnect account', [
                'account_id' => $accountId,
                'error' => $e->getMessage(),
            ]);

            return redirect()
                ->back()
                ->with('error', 'Failed to disconnect: '.$e->getMessage());
        }
    }

    /**
     * Refresh tokens for an account.
     */
    public function refreshTokens(Request $request, int $accountId): RedirectResponse
    {
        try {
            $account = PlatformAccount::findOrFail($accountId);

            if ($account->platform->slug !== 'tiktok-shop') {
                return redirect()
                    ->back()
                    ->with('error', 'Invalid account.');
            }

            $success = $this->authService->refreshToken($account);

            if ($success) {
                return redirect()
                    ->back()
                    ->with('success', 'Tokens refreshed successfully.');
            }

            return redirect()
                ->back()
                ->with('error', 'Failed to refresh tokens. Please reconnect your account.');
        } catch (Exception $e) {
            Log::error('[TikTok OAuth] Token refresh failed', [
                'account_id' => $accountId,
                'error' => $e->getMessage(),
            ]);

            return redirect()
                ->back()
                ->with('error', 'Failed to refresh tokens: '.$e->getMessage());
        }
    }
}
