<?php

namespace App\Services;

use App\Models\ImpersonationLog;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class ImpersonationService
{
    public const SESSION_KEY_IMPERSONATOR = 'impersonator_id';

    public const SESSION_KEY_TIMESTAMP = 'impersonated_at';

    public const SESSION_KEY_LOG = 'impersonation_log_id';

    /**
     * Start impersonating a user
     */
    public function start(User $admin, User $targetUser): ImpersonationLog
    {
        // Create audit log
        $log = ImpersonationLog::create([
            'impersonator_id' => $admin->id,
            'impersonated_id' => $targetUser->id,
            'started_at' => now(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        // Store impersonator info in session
        Session::put(self::SESSION_KEY_IMPERSONATOR, $admin->id);
        Session::put(self::SESSION_KEY_TIMESTAMP, now()->toIso8601String());
        Session::put(self::SESSION_KEY_LOG, $log->id);

        // Login as the target user
        Auth::login($targetUser);

        return $log;
    }

    /**
     * Stop impersonating and return to original admin
     */
    public function stop(): ?User
    {
        $impersonatorId = Session::get(self::SESSION_KEY_IMPERSONATOR);
        $logId = Session::get(self::SESSION_KEY_LOG);

        if (! $impersonatorId) {
            return null;
        }

        // Mark the log as ended
        if ($logId) {
            $log = ImpersonationLog::find($logId);
            $log?->markAsEnded();
        }

        // Get the original admin
        $admin = User::find($impersonatorId);

        // Clear impersonation session data
        Session::forget([
            self::SESSION_KEY_IMPERSONATOR,
            self::SESSION_KEY_TIMESTAMP,
            self::SESSION_KEY_LOG,
        ]);

        // Login as the original admin
        if ($admin) {
            Auth::login($admin);
        }

        return $admin;
    }

    /**
     * Check if currently impersonating
     */
    public function isImpersonating(): bool
    {
        return Session::has(self::SESSION_KEY_IMPERSONATOR);
    }

    /**
     * Get the original admin user (impersonator)
     */
    public function getImpersonator(): ?User
    {
        $impersonatorId = Session::get(self::SESSION_KEY_IMPERSONATOR);

        return $impersonatorId ? User::find($impersonatorId) : null;
    }

    /**
     * Get the impersonation start timestamp
     */
    public function getImpersonationStart(): ?string
    {
        return Session::get(self::SESSION_KEY_TIMESTAMP);
    }

    /**
     * Check if a user can impersonate others
     */
    public function canImpersonate(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Check if a user can be impersonated
     */
    public function canBeImpersonated(User $target, User $impersonator): bool
    {
        // Cannot impersonate yourself
        if ($target->id === $impersonator->id) {
            return false;
        }

        // Only admins can impersonate
        if (! $impersonator->isAdmin()) {
            return false;
        }

        // Can impersonate any user (including other admins per requirements)
        return true;
    }

    /**
     * Get the appropriate dashboard route for a user role
     */
    public function getDashboardRoute(User $user): string
    {
        if ($user->isStudent()) {
            return 'student.dashboard';
        }

        if ($user->isTeacher()) {
            return 'teacher.dashboard';
        }

        if ($user->isLiveHost()) {
            return 'live-host.dashboard';
        }

        if ($user->hasRole('class_admin')) {
            return 'class-admin.dashboard';
        }

        return 'dashboard';
    }
}
