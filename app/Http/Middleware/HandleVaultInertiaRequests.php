<?php

namespace App\Http\Middleware;

use App\Models\VaultCredential;
use Illuminate\Http\Request;

/**
 * Inertia middleware for the Password Vault workspace.
 *
 * Overrides the root view to `vault.app` and shares the total credential
 * count so the sidebar can show it without each controller passing it.
 */
class HandleVaultInertiaRequests extends HandleInertiaRequests
{
    protected $rootView = 'vault.app';

    /**
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'totalCredentials' => fn () => VaultCredential::query()->count(),
        ];
    }
}
