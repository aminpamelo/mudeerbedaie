<?php

declare(strict_types=1);

namespace App\Http\Controllers\TikTok;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\TikTok\TikTokAuthService;
use App\Services\TikTok\TikTokClientFactory;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TikTokAuthController extends Controller
{
    public function __construct(
        private TikTokAuthService $authService,
        private TikTokClientFactory $clientFactory
    ) {}

    /**
     * Initiate the OAuth flow - redirect to TikTok authorization.
     *
     * @param  Request  $request  Accepts optional 'link_account' query param to link existing account
     */
    public function redirect(Request $request): RedirectResponse
    {
        // Check if TikTok is configured
        if (! $this->clientFactory->isConfigured()) {
            return redirect()
                ->route('platforms.index')
                ->with('error', 'TikTok API is not configured. Please set TIKTOK_APP_KEY and TIKTOK_APP_SECRET in your .env file.');
        }

        // Generate a unique state with user ID and link_account_id embedded
        // This is necessary for cross-domain OAuth (via Expose/ngrok) where sessions don't persist
        $csrfToken = Str::random(40);
        $userId = auth()->id();
        $linkAccountId = null;

        // If linking an existing account, validate and store the account ID
        if ($request->has('link_account')) {
            $linkAccountId = (int) $request->get('link_account');
            $existingAccount = \App\Models\PlatformAccount::find($linkAccountId);

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
        // Payload contains both user_id and link_account_id to survive cross-domain redirect
        $payload = json_encode([
            'user_id' => $userId,
            'link_account_id' => $linkAccountId,
        ]);
        $encryptedPayload = Crypt::encryptString($payload);
        $state = $csrfToken.'.'.base64_encode($encryptedPayload);

        // Store CSRF token in session for validation (as backup if session persists)
        session([
            'tiktok_oauth_state' => $csrfToken,
            'tiktok_oauth_user_id' => $userId,
            'tiktok_link_account_id' => $linkAccountId,
        ]);

        // Get the authorization URL
        try {
            $authUrl = $this->authService->getAuthorizationUrl($state);

            Log::info('[TikTok OAuth] Redirecting to authorization', [
                'csrf_token' => $csrfToken,
                'user_id' => $userId,
                'link_account_id' => $linkAccountId,
            ]);

            return redirect()->away($authUrl);
        } catch (Exception $e) {
            Log::error('[TikTok OAuth] Failed to generate authorization URL', [
                'error' => $e->getMessage(),
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

        // Parse state to extract CSRF token, user ID, and link_account_id
        // State format: csrfToken.base64(encryptedPayload)
        // Payload: {user_id: int, link_account_id: int|null}
        $state = $request->get('state');
        $user = null;
        $linkAccountId = null;

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
                    $user = User::find($userId);

                    Log::info('[TikTok OAuth] Extracted payload from state', [
                        'user_id' => $userId,
                        'user_found' => $user !== null,
                        'link_account_id' => $linkAccountId,
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

        // Clear the state from session
        session()->forget(['tiktok_oauth_state', 'tiktok_oauth_user_id', 'tiktok_link_account_id']);

        try {
            // Exchange code for tokens
            $tokenData = $this->authService->handleCallback($code);

            // Store tokens, user, and link_account_id temporarily in session for shop selection
            session([
                'tiktok_token_data' => $tokenData,
                'tiktok_auth_timestamp' => now()->timestamp,
                'tiktok_auth_user_id' => $user->id,
                'tiktok_link_account_id' => $linkAccountId,
            ]);

            Log::info('[TikTok OAuth] Tokens stored in session', [
                'user_id' => $user->id,
                'link_account_id' => $linkAccountId,
            ]);

            // Get authorized shops
            $shops = $this->authService->getAuthorizedShops($tokenData['access_token']);

            if (empty($shops)) {
                Log::warning('[TikTok OAuth] No shops found for authorized account');

                return redirect()
                    ->route('platforms.index')
                    ->with('error', 'No TikTok Shop found for this account. Please ensure you have a TikTok Shop set up.');
            }

            // If only one shop, connect it directly
            if (count($shops) === 1) {
                return $this->connectShop($shops[0], $user, $linkAccountId);
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

        // Get the linking account info if we're linking an existing account
        $linkAccountId = session('tiktok_link_account_id');
        $linkingAccount = null;
        $bestMatchIndex = 0;

        if ($linkAccountId) {
            $linkingAccount = \App\Models\PlatformAccount::find($linkAccountId);

            // Try to find the best matching shop based on name similarity
            if ($linkingAccount) {
                $accountName = strtolower(trim($linkingAccount->name));
                $highestSimilarity = 0;

                foreach ($shops as $index => $shop) {
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

        return $this->connectShop($shops[$shopIndex]);
    }

    /**
     * Connect a shop and create the platform account.
     *
     * @param  array  $shopData  Shop data from TikTok API
     * @param  User|null  $user  User to associate with the account
     * @param  int|null  $linkAccountId  Existing account ID to link (if linking instead of creating)
     */
    private function connectShop(array $shopData, ?User $user = null, ?int $linkAccountId = null): RedirectResponse
    {
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

        try {
            if ($linkAccountId) {
                // Link existing account
                $account = $this->authService->linkExistingAccount(
                    $linkAccountId,
                    $tokenData,
                    $shopData
                );
                $successMessage = "TikTok Shop '{$shopData['shop_name']}' has been linked to your existing account successfully!";
            } else {
                // Create new account
                $account = $this->authService->createOrUpdateAccount(
                    $user,
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
     */
    public function disconnect(Request $request, int $accountId): RedirectResponse
    {
        try {
            $account = \App\Models\PlatformAccount::findOrFail($accountId);

            // Verify the account belongs to TikTok platform
            if ($account->platform->slug !== 'tiktok-shop') {
                return redirect()
                    ->back()
                    ->with('error', 'Invalid account.');
            }

            $this->authService->disconnectAccount($account);

            return redirect()
                ->route('platforms.accounts.index', ['platform' => 'tiktok-shop'])
                ->with('success', "TikTok Shop '{$account->name}' has been disconnected.");
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
            $account = \App\Models\PlatformAccount::findOrFail($accountId);

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
