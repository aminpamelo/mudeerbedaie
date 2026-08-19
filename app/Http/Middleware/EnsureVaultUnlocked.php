<?php

namespace App\Http\Middleware;

use App\Services\VaultLockService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks vault content routes until the current session has entered the shared
 * vault password. Unauthenticated-to-the-vault requests are bounced to the
 * unlock screen (which doubles as first-time setup when no password is set).
 */
class EnsureVaultUnlocked
{
    public function __construct(private readonly VaultLockService $lock) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->lock->isUnlocked()) {
            return redirect()->route('vault.unlock');
        }

        return $next($request);
    }
}
