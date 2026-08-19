<?php

namespace App\Http\Controllers\Vault;

use App\Http\Controllers\Controller;
use App\Http\Requests\Vault\UpdateVaultPasswordRequest;
use App\Services\VaultAuditService;
use App\Services\VaultLockService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function __construct(
        private readonly VaultLockService $lock,
        private readonly VaultAuditService $audit,
    ) {}

    public function index(): Response
    {
        return Inertia::render('Settings');
    }

    /**
     * Rotate the shared vault password. Changing it re-locks every other
     * session immediately (their unlock token no longer matches), which is
     * how access is revoked when a staff member leaves.
     */
    public function updatePassword(UpdateVaultPasswordRequest $request): RedirectResponse
    {
        if (! $this->lock->verify($request->validated('current_password'))) {
            return back()->withErrors(['current_password' => 'The current vault password is incorrect.']);
        }

        $this->lock->setPassword($request->validated('password'));

        // Keep this session unlocked against the new password; all others re-lock.
        $this->lock->unlock();
        $this->audit->log('password_changed');

        return back()->with('success', 'Vault password changed. Everyone else must re-enter the new password.');
    }
}
