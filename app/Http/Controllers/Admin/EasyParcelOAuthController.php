<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\SettingsService;
use App\Services\Shipping\EasyParcelOAuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Drives the one-time "Connect EasyParcel" OAuth handshake: send the admin to
 * EasyParcel's hosted login, then receive the authorization code back and trade
 * it for tokens. The fixed callback URL must be registered as a Redirect URI on
 * the app in the EasyParcel Developer Hub.
 */
class EasyParcelOAuthController extends Controller
{
    public function __construct(
        private EasyParcelOAuthService $oauth,
        private SettingsService $settings,
    ) {}

    /**
     * Begin the OAuth flow — redirect to EasyParcel's authorization page.
     */
    public function connect(Request $request): RedirectResponse
    {
        if (! $this->settings->isEasyParcelConfigured()) {
            return redirect()
                ->route('admin.settings.shipping', ['tab' => 'easyparcel'])
                ->with('error', 'Enter and save your EasyParcel Client ID and Client Secret first.');
        }

        $state = Str::random(40);
        $request->session()->put('easyparcel_oauth_state', $state);

        return redirect()->away(
            $this->oauth->authorizeUrl($this->callbackUrl(), $state)
        );
    }

    /**
     * Handle the redirect back from EasyParcel and exchange the code for tokens.
     */
    public function callback(Request $request): RedirectResponse
    {
        $target = redirect()->route('admin.settings.shipping', ['tab' => 'easyparcel']);

        if ($request->filled('error')) {
            return $target->with('error', 'EasyParcel authorization was cancelled or denied.');
        }

        $expectedState = $request->session()->pull('easyparcel_oauth_state');

        if (! $expectedState || ! hash_equals($expectedState, (string) $request->query('state'))) {
            return $target->with('error', 'EasyParcel connection failed a security check (state mismatch). Please try again.');
        }

        $code = (string) $request->query('code');

        if ($code === '' || ! $this->oauth->exchangeCode($code, $this->callbackUrl())) {
            return $target->with('error', 'Could not complete the EasyParcel connection. Please try again.');
        }

        return $target->with('success', 'EasyParcel account connected successfully.');
    }

    /**
     * Disconnect the linked account.
     */
    public function disconnect(): RedirectResponse
    {
        $this->settings->clearEasyParcelTokens();

        return redirect()
            ->route('admin.settings.shipping', ['tab' => 'easyparcel'])
            ->with('success', 'EasyParcel account disconnected.');
    }

    /**
     * The exact Redirect URI EasyParcel must be configured to call back. Kept as
     * a single source of truth so authorize and exchange use an identical value.
     */
    private function callbackUrl(): string
    {
        return route('admin.easyparcel.callback');
    }
}
