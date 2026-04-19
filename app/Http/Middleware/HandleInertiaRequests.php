<?php

namespace App\Http\Middleware;

use App\Models\LiveSchedule;
use App\Models\LiveSession;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'livehost.app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => fn () => $request->user()
                    ? [
                        'id' => $request->user()->id,
                        'name' => $request->user()->name,
                        'email' => $request->user()->email,
                        'role' => $request->user()->role,
                    ]
                    : null,
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
            'navCounts' => fn () => $this->navCounts($request),
        ];
    }

    /**
     * Counts powering the sidebar badges on the Live Host Desk. Shared so every
     * PIC/admin page shows real numbers without each controller passing them
     * through manually. Cached briefly to keep the cost negligible.
     *
     * @return array{hosts: int, schedules: int, sessions: int}|null
     */
    private function navCounts(Request $request): ?array
    {
        $user = $request->user();

        if (! $user || ! in_array($user->role, ['admin', 'admin_livehost'], true)) {
            return null;
        }

        return Cache::remember('livehost.navCounts', 60, fn () => [
            'hosts' => User::query()->where('role', 'live_host')->count(),
            'schedules' => LiveSchedule::query()->where('is_active', true)->count(),
            'sessions' => LiveSession::query()->count(),
        ]);
    }
}
