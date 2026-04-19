<?php

namespace App\Http\Middleware;

use App\Models\LiveSchedule;
use App\Models\LiveSession;
use App\Models\PlatformAccount;
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
                        'avatarUrl' => $request->user()->avatar_url,
                        'commission' => $this->hostCommission($request->user()),
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
     * Shape the host's commission snapshot used by the Pocket recap UI
     * (EarningsEstimate component) to preview expected earnings.
     *
     * Only live hosts get this block — non-live-host roles (admin,
     * admin_livehost, teacher, etc.) receive `null` so the key is always
     * present but cheap for non-hosts (no DB reads).
     *
     * Trade-off: this adds two extra queries per Inertia request for a live
     * host (one for `commissionProfile`, one for `platformCommissionRates`).
     * Acceptable for v1 — Pocket pages are not hot paths and the host set is
     * small. Revisit if we start seeing N+1 reports on Pocket.
     *
     * For `primaryPlatformRatePercent` we take the first active rate
     * ordered by whatever default the relationship uses. If a host has
     * rates on multiple platforms the heuristic picks one deterministically;
     * multi-platform per-session commission is out of scope for v1.
     *
     * @return array{perLiveRateMyr: float, primaryPlatformRatePercent: float}|null
     */
    private function hostCommission(?User $user): ?array
    {
        if (! $user || $user->role !== 'live_host') {
            return null;
        }

        return [
            'perLiveRateMyr' => (float) optional($user->commissionProfile)->per_live_rate_myr,
            'primaryPlatformRatePercent' => (float) optional($user->platformCommissionRates->first())->commission_rate_percent,
        ];
    }

    /**
     * Counts powering the sidebar badges on the Live Host Desk. Shared so every
     * PIC/admin page shows real numbers without each controller passing them
     * through manually. Cached briefly to keep the cost negligible.
     *
     * @return array{hosts: int, schedules: int, sessions: int, platformAccounts: int}|null
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
            'platformAccounts' => PlatformAccount::query()->count(),
        ]);
    }
}
