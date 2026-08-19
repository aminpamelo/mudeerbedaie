<?php

namespace App\Http\Controllers\Vault;

use App\Http\Controllers\Controller;
use App\Http\Requests\Vault\SetupVaultRequest;
use App\Http\Requests\Vault\UnlockVaultRequest;
use App\Services\VaultAuditService;
use App\Services\VaultLockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LockController extends Controller
{
    public function __construct(
        private readonly VaultLockService $lock,
        private readonly VaultAuditService $audit,
    ) {}

    /**
     * The lock screen. Doubles as first-time setup when no password is set.
     */
    public function show(): Response|RedirectResponse
    {
        if ($this->lock->isUnlocked()) {
            return redirect()->route('vault.dashboard');
        }

        return Inertia::render('Unlock', [
            'mode' => $this->lock->isConfigured() ? 'locked' : 'setup',
        ]);
    }

    /**
     * First-time setup: create the shared vault password.
     */
    public function setup(SetupVaultRequest $request): RedirectResponse
    {
        if ($this->lock->isConfigured()) {
            return redirect()->route('vault.unlock');
        }

        $this->lock->setPassword($request->validated('password'));
        $this->lock->unlock();
        $this->audit->log('password_set');

        return redirect()->route('vault.dashboard')->with('success', 'Vault password set. The vault is now protected.');
    }

    /**
     * Verify the vault password and unlock the session.
     */
    public function unlock(UnlockVaultRequest $request): RedirectResponse
    {
        if (! $this->lock->isConfigured()) {
            return redirect()->route('vault.unlock');
        }

        if (! $this->lock->verify($request->validated('password'))) {
            $this->audit->log('unlock_failed');

            return back()->withErrors(['password' => 'Incorrect vault password.']);
        }

        $this->lock->unlock();
        $this->audit->log('unlocked');

        return redirect()->intended(route('vault.dashboard'))->with('success', 'Vault unlocked.');
    }

    /**
     * Manually re-lock the vault for the current session.
     */
    public function lock(Request $request): RedirectResponse
    {
        $this->lock->lock();
        $this->audit->log('locked');

        return redirect()->route('vault.unlock')->with('success', 'Vault locked.');
    }
}
