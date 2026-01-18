<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\ImpersonationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ImpersonationController extends Controller
{
    public function __construct(
        private ImpersonationService $impersonationService
    ) {}

    /**
     * Start impersonating a user
     */
    public function start(Request $request, User $user): RedirectResponse
    {
        $admin = $request->user();

        // Validate the admin can impersonate
        if (! $this->impersonationService->canImpersonate($admin)) {
            abort(403, 'You do not have permission to impersonate users.');
        }

        // Validate the target can be impersonated
        if (! $this->impersonationService->canBeImpersonated($user, $admin)) {
            return back()->with('error', 'You cannot impersonate this user.');
        }

        // Start impersonation
        $this->impersonationService->start($admin, $user);

        // Redirect to appropriate dashboard
        $dashboardRoute = $this->impersonationService->getDashboardRoute($user);

        return redirect()->route($dashboardRoute)
            ->with('success', "You are now impersonating {$user->name}.");
    }

    /**
     * Stop impersonating and return to admin
     */
    public function stop(): RedirectResponse
    {
        $admin = $this->impersonationService->stop();

        if (! $admin) {
            return redirect()->route('dashboard')
                ->with('error', 'No active impersonation session found.');
        }

        return redirect()->route('users.index')
            ->with('success', 'Impersonation ended. Welcome back!');
    }
}
