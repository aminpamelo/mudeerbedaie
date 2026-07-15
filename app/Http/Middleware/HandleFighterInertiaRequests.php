<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;

/**
 * Inertia middleware for the Fighter portal bundle.
 *
 * The base `HandleInertiaRequests` middleware is appended to every `web`
 * request (its `$rootView` targets the Live Host Desk). This subclass runs as
 * route-level middleware on the `/fighter/*` group so its `$rootView` overrides
 * the PIC root view for Fighter requests only, and seeds the unread new-order
 * notification badge for the portal's bell.
 */
class HandleFighterInertiaRequests extends HandleInertiaRequests
{
    protected $rootView = 'fighter.app';

    /**
     * Notification type prefix used to scope the Fighter portal's feed to its
     * own notifications (currently new-order alerts).
     */
    public const NOTIFICATION_NAMESPACE = 'App\\Notifications\\Fighter\\';

    /**
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'unreadNotificationCount' => fn () => $this->unreadNotificationCount($request),
        ];
    }

    /**
     * Count of unread Fighter notifications (new-order alerts) for the badge.
     * Scoped to the Fighter namespace so unrelated notifications never leak in.
     */
    private function unreadNotificationCount(Request $request): int
    {
        $user = $request->user();

        if (! $user) {
            return 0;
        }

        return $user->unreadNotifications()
            ->where('type', 'like', self::NOTIFICATION_NAMESPACE.'%')
            ->count();
    }
}
