<?php

namespace App\Http\Middleware;

use App\Models\LiveSchedule;
use App\Models\LiveSession;
use App\Models\PlatformAccount;
use App\Models\User;
use App\Services\SettingsService;
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
                'permissions' => fn () => $this->permissions($request->user()),
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
            'brand' => fn () => $this->brand(),
            'navCounts' => fn () => $this->navCounts($request),
        ];
    }

    /**
     * App branding (name + logo) pulled from the admin settings so the
     * Live Host Desk sidebar reflects whatever the operator has configured
     * under /admin/settings rather than a hardcoded label.
     *
     * @return array{name: string, logoUrl: string|null}
     */
    private function brand(): array
    {
        $settings = app(SettingsService::class);

        return [
            'name' => (string) $settings->get('site_name', config('app.name', 'Mudeer Bedaie')),
            'logoUrl' => $settings->getLogo(),
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
     * Role-derived capability flags consumed by the sidebar and action buttons.
     *
     * PIC roles (admin + admin_livehost) get full access to every flag. The
     * livehost_assistant role gets all `false`, so the frontend hides write
     * actions and admin-only nav items without needing to pattern-match on
     * role strings.
     *
     * @return array<string, bool>
     */
    private function permissions(?User $user): array
    {
        $isPic = $user && in_array($user->role, ['admin', 'admin_livehost'], true);

        return [
            'canManageHosts' => $isPic,
            'canManagePlatformAccounts' => $isPic,
            'canManageCreators' => $isPic,
            'canSeeSessions' => $isPic,
            'canSeeFinancials' => $isPic,
            'canSeePayroll' => $isPic,
            'canRecruit' => $isPic,
            'canSeeTiktokImports' => $isPic,
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

        if (! $user) {
            return null;
        }

        if ($user->role === 'livehost_assistant') {
            return Cache::remember('livehost.navCounts.assistant', 60, fn () => [
                'hosts' => User::query()->where('role', 'live_host')->count(),
                'platformAccounts' => PlatformAccount::query()->count(),
                'creators' => \App\Models\LiveHostPlatformAccount::query()->count(),
            ]);
        }

        if (! in_array($user->role, ['admin', 'admin_livehost'], true)) {
            return null;
        }

        return Cache::remember('livehost.navCounts', 60, fn () => [
            'hosts' => User::query()->where('role', 'live_host')->count(),
            'schedules' => LiveSchedule::query()->where('is_active', true)->count(),
            'sessions' => LiveSession::query()->count(),
            'platformAccounts' => PlatformAccount::query()->count(),
            'creators' => \App\Models\LiveHostPlatformAccount::query()->count(),
            'replacements' => \App\Models\SessionReplacementRequest::query()
                ->where('status', \App\Models\SessionReplacementRequest::STATUS_PENDING)
                ->count(),
        ]);
    }
}
