<?php

namespace App\Http\Middleware;

use App\Models\LiveHostMentee;
use Illuminate\Http\Request;

/**
 * Inertia middleware for the Live Host Pocket bundle.
 *
 * The base `HandleInertiaRequests` middleware is appended to every `web`
 * request for the Live Host Desk (PIC dashboard). This subclass runs as a
 * route-level middleware on the `/live-host/*` group so its `$rootView`
 * overrides the PIC root view for Pocket requests only, and so it can
 * add Pocket-specific shared props without leaking them to the admin UI.
 */
class HandlePocketInertiaRequests extends HandleInertiaRequests
{
    /**
     * The root template that's loaded on the first page visit for Pocket.
     *
     * @var string
     */
    protected $rootView = 'livehost-pocket.app';

    /**
     * Shared props for every Pocket page. The PIC navCounts/branding
     * helpers from the parent are carried through unchanged so either
     * bundle can use them; Pocket adds its own feature flag bag.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'features' => [
                'allowance_enabled' => (bool) config('livehost.allowance_enabled'),
            ],
            // How many active mentees this host mentors — drives the "My Mentees"
            // entry point, shown only to hosts who actually lead someone.
            'mentorMenteeCount' => fn () => $this->mentorMenteeCount($request),
            // Identity for the header avatar. Since the "You" tab was replaced by
            // "Performance", the avatar in the top bar is now the entry point to
            // the profile page.
            'pocketUser' => fn () => $this->pocketUser($request),
        ];
    }

    /**
     * @return array{name: string|null, avatarUrl: string|null}|null
     */
    private function pocketUser(Request $request): ?array
    {
        $user = $request->user();

        if (! $user) {
            return null;
        }

        return [
            'name' => $user->name,
            'avatarUrl' => $user->avatar_url,
        ];
    }

    private function mentorMenteeCount(Request $request): int
    {
        $user = $request->user();

        if (! $user || $user->role !== 'live_host') {
            return 0;
        }

        return LiveHostMentee::query()
            ->where('status', 'active')
            ->mentoredBy($user->id)
            ->count();
    }
}
