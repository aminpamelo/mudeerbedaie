<?php

namespace App\Http\Controllers\LiveHost;

use App\Http\Controllers\Controller;
use App\Http\Requests\LiveHost\StoreCreatorRequest;
use App\Http\Requests\LiveHost\UpdateCreatorRequest;
use App\Models\LiveHostPlatformAccount;
use App\Models\PlatformAccount;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Manages the live_host_platform_account pivot as a first-class "Creator"
 * record — (host × platform account) + TikTok creator identity. Populating
 * creator_platform_user_id here is what lets the TikTok import matcher
 * (LiveSessionMatcher) link report rows to live sessions.
 */
class CreatorController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->string('search')->toString();

        $creators = LiveHostPlatformAccount::query()
            ->with([
                'user:id,name,email',
                'platformAccount:id,name,platform_id',
                'platformAccount.platform:id,slug,name,display_name',
            ])
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($q) use ($search) {
                    $q->where('creator_handle', 'like', "%{$search}%")
                        ->orWhere('creator_platform_user_id', 'like', "%{$search}%")
                        ->orWhereHas('user', fn ($q) => $q->where('name', 'like', "%{$search}%"));
                });
            })
            ->orderBy('platform_account_id')
            ->orderByDesc('is_primary')
            ->orderBy('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (LiveHostPlatformAccount $c) => $this->mapCreator($c));

        return Inertia::render('creators/Index', [
            'creators' => $creators,
            'filters' => ['search' => $search],
            'hosts' => $this->hostOptions(),
            'platformAccounts' => $this->platformAccountOptions(),
        ]);
    }

    public function store(StoreCreatorRequest $request): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($data) {
            $creator = LiveHostPlatformAccount::create([
                'user_id' => $data['user_id'],
                'platform_account_id' => $data['platform_account_id'],
                'creator_handle' => $data['creator_handle'] ?? null,
                'creator_platform_user_id' => $data['creator_platform_user_id'],
                'is_primary' => (bool) ($data['is_primary'] ?? false),
            ]);

            $this->demoteOtherPrimaries($creator);
        });

        return redirect()
            ->route('livehost.creators.index')
            ->with('success', 'Creator added.');
    }

    public function update(UpdateCreatorRequest $request, LiveHostPlatformAccount $creator): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($creator, $data) {
            $creator->fill([
                'creator_handle' => $data['creator_handle'] ?? null,
                'creator_platform_user_id' => $data['creator_platform_user_id'],
                'is_primary' => (bool) ($data['is_primary'] ?? $creator->is_primary),
            ])->save();

            $this->demoteOtherPrimaries($creator);
        });

        return redirect()
            ->route('livehost.creators.index')
            ->with('success', 'Creator updated.');
    }

    public function destroy(LiveHostPlatformAccount $creator): RedirectResponse
    {
        $creator->delete();

        return redirect()
            ->route('livehost.creators.index')
            ->with('success', 'Creator removed.');
    }

    /**
     * @return array<string, mixed>
     */
    private function mapCreator(LiveHostPlatformAccount $creator): array
    {
        return [
            'id' => $creator->id,
            'user_id' => $creator->user_id,
            'platform_account_id' => $creator->platform_account_id,
            'creator_handle' => $creator->creator_handle,
            'creator_platform_user_id' => $creator->creator_platform_user_id,
            'is_primary' => (bool) $creator->is_primary,
            'host' => $creator->user ? [
                'id' => $creator->user->id,
                'name' => $creator->user->name,
                'email' => $creator->user->email,
            ] : null,
            'platform_account' => $creator->platformAccount ? [
                'id' => $creator->platformAccount->id,
                'name' => $creator->platformAccount->name,
                'platform' => $creator->platformAccount->platform?->display_name
                    ?? $creator->platformAccount->platform?->name,
                'platform_slug' => $creator->platformAccount->platform?->slug,
            ] : null,
        ];
    }

    /**
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function hostOptions(): \Illuminate\Support\Collection
    {
        return User::query()
            ->where('role', 'live_host')
            ->orderBy('name')
            ->get(['id', 'name', 'email'])
            ->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
            ]);
    }

    /**
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function platformAccountOptions(): \Illuminate\Support\Collection
    {
        return PlatformAccount::query()
            ->with('platform:id,slug,name,display_name')
            ->orderBy('name')
            ->get(['id', 'name', 'platform_id'])
            ->map(fn (PlatformAccount $a) => [
                'id' => $a->id,
                'name' => $a->name,
                'platform' => $a->platform?->display_name ?? $a->platform?->name,
                'platform_slug' => $a->platform?->slug,
            ]);
    }

    /**
     * Enforce "one primary per host" when the saved pivot is flagged primary.
     */
    private function demoteOtherPrimaries(LiveHostPlatformAccount $saved): void
    {
        if (! $saved->is_primary) {
            return;
        }

        LiveHostPlatformAccount::query()
            ->where('user_id', $saved->user_id)
            ->where('id', '!=', $saved->id)
            ->where('is_primary', true)
            ->update(['is_primary' => false]);
    }
}
