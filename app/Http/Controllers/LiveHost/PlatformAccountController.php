<?php

namespace App\Http\Controllers\LiveHost;

use App\Http\Controllers\Controller;
use App\Http\Requests\LiveHost\StorePlatformAccountRequest;
use App\Http\Requests\LiveHost\UpdatePlatformAccountRequest;
use App\Models\LiveSchedule;
use App\Models\LiveScheduleAssignment;
use App\Models\LiveSession;
use App\Models\Platform;
use App\Models\PlatformAccount;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PlatformAccountController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->string('search')->toString();
        $platformId = $request->string('platform_id')->toString();
        $userId = $request->string('user_id')->toString();
        $isActive = $request->string('is_active')->toString();

        $accounts = PlatformAccount::query()
            ->with(['platform:id,name,display_name,slug', 'user:id,name,email'])
            ->withCount([
                'liveSchedules as schedules_count',
                'liveSessions as sessions_count',
            ])
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('account_id', 'like', "%{$search}%");
                });
            })
            ->when($platformId !== '', fn ($q) => $q->where('platform_id', (int) $platformId))
            ->when($userId !== '', fn ($q) => $q->where('user_id', (int) $userId))
            ->when($isActive !== '', fn ($q) => $q->where('is_active', $isActive === '1'))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (PlatformAccount $account) => $this->mapAccount($account));

        return Inertia::render('platform-accounts/Index', [
            'accounts' => $accounts,
            'filters' => [
                'search' => $search,
                'platform_id' => $platformId,
                'user_id' => $userId,
                'is_active' => $isActive,
            ],
            'platforms' => $this->platformOptions(),
            'users' => $this->userOptions(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('platform-accounts/Create', [
            'platforms' => $this->platformOptions(),
            'users' => $this->userOptions(),
        ]);
    }

    public function store(StorePlatformAccountRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['is_active'] = $data['is_active'] ?? true;

        $account = PlatformAccount::create($data);

        return redirect()
            ->route('livehost.platform-accounts.index')
            ->with('success', "Platform account {$account->name} created.");
    }

    public function show(PlatformAccount $platformAccount): Response
    {
        $platformAccount->load(['platform:id,name,display_name,slug', 'user:id,name,email']);
        $platformAccount->loadCount([
            'liveSchedules as schedules_count',
            'liveSessions as sessions_count',
        ]);

        $assignmentsCount = LiveScheduleAssignment::query()
            ->where('platform_account_id', $platformAccount->id)
            ->count();

        return Inertia::render('platform-accounts/Show', [
            'account' => array_merge(
                $this->mapAccount($platformAccount),
                ['assignments' => $assignmentsCount]
            ),
        ]);
    }

    public function edit(PlatformAccount $platformAccount): Response
    {
        return Inertia::render('platform-accounts/Edit', [
            'account' => [
                'id' => $platformAccount->id,
                'name' => $platformAccount->name,
                'platform_id' => $platformAccount->platform_id,
                'user_id' => $platformAccount->user_id,
                'account_id' => $platformAccount->account_id,
                'description' => $platformAccount->description,
                'country_code' => $platformAccount->country_code,
                'currency' => $platformAccount->currency,
                'is_active' => (bool) $platformAccount->is_active,
            ],
            'platforms' => $this->platformOptions(),
            'users' => $this->userOptions(),
        ]);
    }

    public function update(UpdatePlatformAccountRequest $request, PlatformAccount $platformAccount): RedirectResponse
    {
        $data = $request->validated();
        $data['is_active'] = $data['is_active'] ?? false;

        $platformAccount->update($data);

        return redirect()
            ->route('livehost.platform-accounts.show', $platformAccount)
            ->with('success', "Platform account {$platformAccount->name} updated.");
    }

    public function destroy(PlatformAccount $platformAccount): RedirectResponse
    {
        $hasSessions = LiveSession::query()
            ->where('platform_account_id', $platformAccount->id)
            ->exists();
        $hasSchedules = LiveSchedule::query()
            ->where('platform_account_id', $platformAccount->id)
            ->exists();
        $hasAssignments = LiveScheduleAssignment::query()
            ->where('platform_account_id', $platformAccount->id)
            ->exists();

        if ($hasSessions || $hasSchedules || $hasAssignments) {
            return back()->with(
                'error',
                "Cannot delete {$platformAccount->name}: it is still referenced by schedules, session slots, or live sessions. Mark it inactive instead."
            );
        }

        $name = $platformAccount->name;
        $platformAccount->delete();

        return redirect()
            ->route('livehost.platform-accounts.index')
            ->with('success', "Platform account {$name} deleted.");
    }

    /**
     * @return array{
     *     id: int,
     *     name: string,
     *     accountId: ?string,
     *     description: ?string,
     *     countryCode: ?string,
     *     currency: ?string,
     *     isActive: bool,
     *     platform: ?array{id: int, name: string, slug: ?string, displayName: ?string},
     *     user: ?array{id: int, name: string, email: ?string},
     *     schedules: int,
     *     sessions: int,
     *     createdAt: ?string,
     *     updatedAt: ?string
     * }
     */
    private function mapAccount(PlatformAccount $account): array
    {
        return [
            'id' => $account->id,
            'name' => $account->name,
            'accountId' => $account->account_id,
            'description' => $account->description,
            'countryCode' => $account->country_code,
            'currency' => $account->currency,
            'isActive' => (bool) $account->is_active,
            'platform' => $account->platform ? [
                'id' => $account->platform->id,
                'name' => $account->platform->name,
                'slug' => $account->platform->slug,
                'displayName' => $account->platform->display_name,
            ] : null,
            'user' => $account->user ? [
                'id' => $account->user->id,
                'name' => $account->user->name,
                'email' => $account->user->email,
            ] : null,
            'schedules' => (int) ($account->schedules_count ?? 0),
            'sessions' => (int) ($account->sessions_count ?? 0),
            'createdAt' => $account->created_at?->toIso8601String(),
            'updatedAt' => $account->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return \Illuminate\Support\Collection<int, array{id: int, name: string, slug: ?string}>
     */
    private function platformOptions(): \Illuminate\Support\Collection
    {
        return Platform::query()
            ->orderBy('name')
            ->get(['id', 'name', 'display_name', 'slug'])
            ->map(fn (Platform $p) => [
                'id' => $p->id,
                'name' => $p->display_name ?? $p->name,
                'slug' => $p->slug,
            ]);
    }

    /**
     * @return \Illuminate\Support\Collection<int, array{id: int, name: string, email: ?string}>
     */
    private function userOptions(): \Illuminate\Support\Collection
    {
        return User::query()
            ->whereIn('role', ['admin', 'admin_livehost', 'live_host', 'employee'])
            ->orderBy('name')
            ->get(['id', 'name', 'email'])
            ->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name, 'email' => $u->email]);
    }
}
