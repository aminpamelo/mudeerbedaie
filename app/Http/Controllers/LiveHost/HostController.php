<?php

namespace App\Http\Controllers\LiveHost;

use App\Http\Controllers\Controller;
use App\Http\Requests\LiveHost\StoreCommissionTierScheduleRequest;
use App\Http\Requests\LiveHost\StoreHostRequest;
use App\Http\Requests\LiveHost\UpdateCommissionTierRequest;
use App\Http\Requests\LiveHost\UpdateHostRequest;
use App\Models\LiveHostCommissionProfile;
use App\Models\LiveHostPlatformCommissionRate;
use App\Models\LiveHostPlatformCommissionTier;
use App\Models\LiveSession;
use App\Models\Platform;
use App\Models\SessionReplacementRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class HostController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->string('search')->toString();
        $status = $request->string('status')->toString();
        $hasUpline = $request->string('has_upline')->toString();

        $hosts = User::query()
            ->where('role', 'live_host')
            ->with([
                'commissionProfile.upline:id,name',
                'platformCommissionRates.platform:id,slug,name',
            ])
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->when($status !== '', fn ($q) => $q->where('status', $status))
            ->when($hasUpline === 'has_upline', function ($q) {
                $q->whereHas('commissionProfile', fn ($cp) => $cp->whereNotNull('upline_user_id'));
            })
            ->when($hasUpline === 'no_plan', function ($q) {
                $q->whereDoesntHave('commissionProfile');
            })
            ->when($hasUpline === 'is_upline_only', function ($q) {
                $q->whereIn('id', LiveHostCommissionProfile::query()
                    ->where('is_active', true)
                    ->whereNotNull('upline_user_id')
                    ->pluck('upline_user_id')
                );
            })
            ->withCount(['platformAccounts'])
            ->selectSub(
                LiveSession::query()
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('live_host_id', 'users.id'),
                'hosted_sessions_count'
            )
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'phone' => $u->phone,
                'status' => $u->status,
                'accounts' => (int) ($u->platform_accounts_count ?? 0),
                'sessions' => (int) ($u->hosted_sessions_count ?? 0),
                'createdAt' => $u->created_at?->toIso8601String(),
                'initials' => $this->initials($u->name),
                'commission_plan' => $this->formatCommissionPlan($u),
                'has_upline' => (bool) optional($u->commissionProfile)->upline_user_id,
            ]);

        return Inertia::render('hosts/Index', [
            'hosts' => $hosts,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'has_upline' => $hasUpline,
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        abort_if($request->user()?->isLiveHostAssistant() === true, 403);

        $prefilledUser = null;
        if ($request->integer('user_id')) {
            $prefilledUser = User::query()
                ->where('id', $request->integer('user_id'))
                ->where('role', 'live_host')
                ->first()
                ?->only(['id', 'name', 'email', 'phone']);
        }

        return Inertia::render('hosts/Create', [
            'prefilledUser' => $prefilledUser,
        ]);
    }

    public function show(Request $request, User $host): Response
    {
        abort_unless($host->role === 'live_host', 404);

        $viewer = $request->user();
        $canSeeFinancials = $viewer && in_array($viewer->role, ['admin', 'admin_livehost'], true);
        $canSeeSessions = $canSeeFinancials;

        $host->load([
            'platformAccounts.platform',
            'commissionProfile.upline:id,name',
            'platformCommissionRates.platform:id,slug,name',
        ]);

        $recentSessions = $canSeeSessions
            ? LiveSession::query()
                ->with(['platformAccount.platform'])
                ->where('live_host_id', $host->id)
                ->latest('actual_start_at')
                ->take(10)
                ->get()
                ->map(fn (LiveSession $s) => [
                    'id' => $s->id,
                    'sessionId' => 'LS-'.str_pad((string) $s->id, 5, '0', STR_PAD_LEFT),
                    'status' => $s->status,
                    'platformAccount' => $s->platformAccount?->name,
                    'platformType' => $s->platformAccount?->platform?->slug,
                    'scheduledStart' => $s->scheduled_start_at?->toIso8601String(),
                    'actualStart' => $s->actual_start_at?->toIso8601String(),
                    'actualEnd' => $s->actual_end_at?->toIso8601String(),
                ])
            : collect();

        $totalSessions = $canSeeSessions
            ? LiveSession::query()
                ->where('live_host_id', $host->id)
                ->count()
            : 0;

        $completedSessions = $canSeeSessions
            ? LiveSession::query()
                ->where('live_host_id', $host->id)
                ->where('status', 'ended')
                ->count()
            : 0;

        $replacementsLast90Days = SessionReplacementRequest::query()
            ->where('original_host_id', $host->id)
            ->where('requested_at', '>=', now()->subDays(90))
            ->count();

        $commissionProfile = $canSeeFinancials && $host->commissionProfile
            ? $this->mapCommissionProfile($host->commissionProfile)
            : null;

        $commissionProfiles = $canSeeFinancials
            ? LiveHostCommissionProfile::query()
                ->with('upline:id,name')
                ->where('user_id', $host->id)
                ->orderByDesc('effective_from')
                ->get()
                ->map(fn (LiveHostCommissionProfile $p) => $this->mapCommissionProfile($p))
                ->values()
            : collect();

        $platformCommissionRates = $canSeeFinancials
            ? $host->platformCommissionRates
                ->map(fn (LiveHostPlatformCommissionRate $r) => [
                    'id' => $r->id,
                    'platform_id' => $r->platform_id,
                    'platform_slug' => $r->platform?->slug,
                    'platform_name' => $r->platform?->name ?? $r->platform?->display_name,
                    'commission_rate_percent' => (float) $r->commission_rate_percent,
                    'effective_from' => $r->effective_from?->toIso8601String(),
                    'effective_to' => $r->effective_to?->toIso8601String(),
                    'is_active' => (bool) $r->is_active,
                ])
                ->values()
            : collect();

        $platforms = Platform::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'slug', 'name'])
            ->map(fn (Platform $p) => [
                'id' => $p->id,
                'slug' => $p->slug,
                'name' => $p->name,
            ])
            ->values();

        $uplineCandidates = User::query()
            ->where('role', 'live_host')
            ->where('id', '!=', $host->id)
            ->orderBy('name')
            ->get(['id', 'name', 'email'])
            ->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
            ])
            ->values();

        $commissionTiers = $canSeeFinancials
            ? LiveHostPlatformCommissionTier::query()
                ->where('user_id', $host->id)
                ->where('is_active', true)
                ->with('platform')
                ->orderBy('platform_id')
                ->orderBy('effective_from')
                ->orderBy('tier_number')
                ->get()
                ->groupBy(fn (LiveHostPlatformCommissionTier $t) => $t->platform_id.'|'.$t->effective_from->toDateString())
                ->map(fn ($group) => [
                    'platform_id' => $group->first()->platform_id,
                    'platform' => $group->first()->platform,
                    'effective_from' => $group->first()->effective_from->toDateString(),
                    'tiers' => $group->values()->all(),
                ])
                ->values()
            : collect();

        return Inertia::render('hosts/Show', [
            'host' => [
                'id' => $host->id,
                'name' => $host->name,
                'email' => $host->email,
                'phone' => $host->phone,
                'status' => $host->status,
                'createdAt' => $host->created_at?->toIso8601String(),
                'initials' => $this->initials($host->name),
            ],
            'platformAccounts' => $host->platformAccounts->map(fn ($pa) => [
                'id' => $pa->id,
                'name' => $pa->name,
                'platform' => $pa->platform?->slug,
                'platformName' => $pa->platform?->name ?? $pa->platform?->display_name,
            ]),
            'recentSessions' => $recentSessions,
            'stats' => [
                'totalSessions' => $totalSessions,
                'completedSessions' => $completedSessions,
                'platformAccounts' => $host->platformAccounts->count(),
                'replacementsLast90Days' => $replacementsLast90Days,
            ],
            'commissionProfile' => $commissionProfile,
            'commissionProfiles' => $commissionProfiles,
            'platformCommissionRates' => $platformCommissionRates,
            'platforms' => $platforms,
            'uplineCandidates' => $uplineCandidates,
            'commissionTiers' => $commissionTiers,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function mapCommissionProfile(LiveHostCommissionProfile $profile): array
    {
        return [
            'id' => $profile->id,
            'base_salary_myr' => (float) $profile->base_salary_myr,
            'per_live_rate_myr' => (float) $profile->per_live_rate_myr,
            'upline_user_id' => $profile->upline_user_id,
            'upline_name' => $profile->upline?->name,
            'override_rate_l1_percent' => (float) $profile->override_rate_l1_percent,
            'override_rate_l2_percent' => (float) $profile->override_rate_l2_percent,
            'notes' => $profile->notes,
            'effective_from' => $profile->effective_from?->toIso8601String(),
            'effective_to' => $profile->effective_to?->toIso8601String(),
            'is_active' => (bool) $profile->is_active,
        ];
    }

    private function formatCommissionPlan(User $u): string
    {
        $profile = $u->commissionProfile;
        if (! $profile) {
            return '—';
        }

        $rates = $u->platformCommissionRates;
        $primary = $rates->first(fn ($r) => $r->platform?->slug === 'tiktok-shop')
            ?? $rates->first();

        $base = number_format((float) $profile->base_salary_myr, 0);
        $perLive = number_format((float) $profile->per_live_rate_myr, 0);
        $ratePct = $primary
            ? rtrim(rtrim(number_format((float) $primary->commission_rate_percent, 2), '0'), '.').'%'
            : '—';

        return "RM {$base} + {$ratePct} + RM {$perLive}";
    }

    public function store(StoreHostRequest $request): RedirectResponse
    {
        abort_if($request->user()?->isLiveHostAssistant() === true, 403);

        $existingUserId = $request->resolveExistingUserId();

        if ($existingUserId) {
            $host = User::findOrFail($existingUserId);
            $host->update([
                'name' => $request->string('name')->toString(),
                'email' => $request->string('email')->toString(),
                'phone' => $request->string('phone')->toString(),
                'status' => $request->string('status')->toString(),
            ]);
            $message = "Live host {$host->name} profile created.";
        } else {
            $host = User::create([
                'name' => $request->string('name')->toString(),
                'email' => $request->string('email')->toString(),
                'phone' => $request->string('phone')->toString(),
                'status' => $request->string('status')->toString(),
                'role' => 'live_host',
                'password' => Hash::make(Str::random(40)),
            ]);
            $message = "Live host {$host->name} created.";
        }

        return redirect()
            ->route('livehost.hosts.index')
            ->with('success', $message);
    }

    public function edit(Request $request, User $host): Response
    {
        abort_if($request->user()?->isLiveHostAssistant() === true, 403);
        abort_unless($host->role === 'live_host', 404);

        return Inertia::render('hosts/Edit', [
            'host' => [
                'id' => $host->id,
                'name' => $host->name,
                'email' => $host->email,
                'phone' => $host->phone,
                'status' => $host->status,
            ],
        ]);
    }

    public function update(UpdateHostRequest $request, User $host): RedirectResponse
    {
        abort_if($request->user()?->isLiveHostAssistant() === true, 403);
        abort_unless($host->role === 'live_host', 404);

        $host->update($request->validated());

        return redirect()
            ->route('livehost.hosts.show', $host)
            ->with('success', "Live host {$host->name} updated.");
    }

    public function destroy(Request $request, User $host): RedirectResponse
    {
        abort_if($request->user()?->isLiveHostAssistant() === true, 403);
        abort_unless($host->role === 'live_host', 404);

        if (! $request->user()?->can('livehost.delete', $host)) {
            abort(403);
        }

        if ($host->platformAccounts()->exists()) {
            return back()->with(
                'error',
                "Cannot delete {$host->name}: they still have platform accounts linked. Detach the platforms first."
            );
        }

        $name = $host->name;
        $host->delete();

        return redirect()
            ->route('livehost.hosts.index')
            ->with('success', "Live host {$name} deleted.");
    }

    public function storeTierSchedule(
        StoreCommissionTierScheduleRequest $request,
        User $host,
        Platform $platform,
    ): RedirectResponse {
        abort_if($request->user()?->isLiveHostAssistant() === true, 403);
        abort_unless($host->role === 'live_host', 404);

        $data = $request->validated();
        $newEffectiveFrom = Carbon::parse($data['effective_from'])->toDateString();

        DB::transaction(function () use ($host, $platform, $data, $newEffectiveFrom): void {
            $archiveCutoff = Carbon::parse($newEffectiveFrom)->subDay()->toDateString();

            LiveHostPlatformCommissionTier::query()
                ->where('user_id', $host->id)
                ->where('platform_id', $platform->id)
                ->where('is_active', true)
                ->where('effective_from', '<=', $newEffectiveFrom)
                ->get()
                ->each(function (LiveHostPlatformCommissionTier $row) use ($archiveCutoff): void {
                    $row->is_active = false;
                    $row->effective_to = $archiveCutoff;
                    $row->save();
                });

            foreach ($data['tiers'] as $tier) {
                LiveHostPlatformCommissionTier::create([
                    'user_id' => $host->id,
                    'platform_id' => $platform->id,
                    'tier_number' => (int) $tier['tier_number'],
                    'min_gmv_myr' => $tier['min_gmv_myr'],
                    'max_gmv_myr' => $tier['max_gmv_myr'] ?? null,
                    'internal_percent' => $tier['internal_percent'],
                    'l1_percent' => $tier['l1_percent'],
                    'l2_percent' => $tier['l2_percent'],
                    'effective_from' => $newEffectiveFrom,
                    'effective_to' => null,
                    'is_active' => true,
                ]);
            }
        });

        return back()->with('success', 'Tier schedule saved.');
    }

    public function updateTier(
        UpdateCommissionTierRequest $request,
        User $host,
        LiveHostPlatformCommissionTier $tier,
    ): RedirectResponse {
        abort_if($request->user()?->isLiveHostAssistant() === true, 403);
        abort_unless($tier->user_id === $host->id, 404);

        $tier->update($request->validated());

        return back()->with('success', 'Tier updated.');
    }

    public function destroyTier(Request $request, User $host, LiveHostPlatformCommissionTier $tier): RedirectResponse
    {
        abort_if($request->user()?->isLiveHostAssistant() === true, 403);
        abort_unless($tier->user_id === $host->id, 404);

        $highestTierNumber = LiveHostPlatformCommissionTier::query()
            ->where('user_id', $host->id)
            ->where('platform_id', $tier->platform_id)
            ->where('is_active', true)
            ->where('effective_from', $tier->effective_from)
            ->max('tier_number');

        if ((int) $highestTierNumber !== (int) $tier->tier_number) {
            return back()->with(
                'error',
                'Only the highest tier in the active schedule can be removed to preserve contiguous tier numbers.',
            );
        }

        $tier->is_active = false;
        $tier->save();

        return back()->with('success', 'Tier removed.');
    }

    private function initials(?string $name): string
    {
        if (! $name) {
            return '??';
        }

        $parts = preg_split('/\s+/', trim($name)) ?: [];

        return mb_strtoupper(mb_substr(($parts[0] ?? '').($parts[1] ?? ''), 0, 2, 'UTF-8'), 'UTF-8');
    }
}
