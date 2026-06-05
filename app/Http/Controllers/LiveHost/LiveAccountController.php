<?php

namespace App\Http\Controllers\LiveHost;

use App\Http\Controllers\Controller;
use App\Http\Requests\LiveHost\StoreLiveAccountRequest;
use App\Http\Requests\LiveHost\UpdateLiveAccountRequest;
use App\Models\LiveAccount;
use App\Models\PlatformAccount;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Manages the canonical creator account ("nickname") — the governing reference
 * for the Live Host timetable. An account carries its stable TikTok Creator ID
 * and display labels, the shops it is affiliated with (many-to-many), and the
 * staff hosts eligible to operate it.
 */
class LiveAccountController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->string('search')->toString();
        $needsReview = $request->boolean('needs_review');

        $accounts = LiveAccount::query()
            ->with(['shops:id,name', 'hosts:id,name'])
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($q) use ($search) {
                    $q->where('nickname', 'like', "%{$search}%")
                        ->orWhere('display_name', 'like', "%{$search}%")
                        ->orWhere('creator_user_id', 'like', "%{$search}%");
                });
            })
            ->when($needsReview, fn ($q) => $q->where('needs_review', true))
            ->orderByDesc('needs_review')
            ->orderByRaw('COALESCE(nickname, display_name)')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (LiveAccount $a) => $this->mapAccount($a));

        return Inertia::render('live-accounts/Index', [
            'accounts' => $accounts,
            'filters' => ['search' => $search, 'needs_review' => $needsReview],
            'shops' => $this->shopOptions(),
            'hosts' => $this->hostOptions(),
        ]);
    }

    public function store(StoreLiveAccountRequest $request): RedirectResponse
    {
        abort_if($request->user()?->isLiveHostAssistant() === true, 403);

        $data = $request->validated();

        DB::transaction(function () use ($data) {
            $account = LiveAccount::create([
                'creator_user_id' => $data['creator_user_id'] ?? null,
                'nickname' => $data['nickname'] ?? null,
                'display_name' => $data['display_name'] ?? null,
                'normalized_handle' => LiveAccount::normalizeHandle($data['nickname'] ?? null),
                'is_active' => (bool) ($data['is_active'] ?? true),
                'needs_review' => (bool) ($data['needs_review'] ?? false),
            ]);

            $this->syncShops($account, $data);
            $this->syncHosts($account, $data);
        });

        return back()->with('success', 'Live account created.');
    }

    public function update(UpdateLiveAccountRequest $request, LiveAccount $liveAccount): RedirectResponse
    {
        abort_if($request->user()?->isLiveHostAssistant() === true, 403);

        $data = $request->validated();

        DB::transaction(function () use ($liveAccount, $data) {
            $liveAccount->fill([
                'creator_user_id' => $data['creator_user_id'] ?? null,
                'nickname' => $data['nickname'] ?? null,
                'display_name' => $data['display_name'] ?? null,
                'normalized_handle' => LiveAccount::normalizeHandle($data['nickname'] ?? null),
                'is_active' => (bool) ($data['is_active'] ?? $liveAccount->is_active),
                'needs_review' => (bool) ($data['needs_review'] ?? $liveAccount->needs_review),
            ])->save();

            if (array_key_exists('shop_ids', $data)) {
                $this->syncShops($liveAccount, $data);
            }
            if (array_key_exists('host_ids', $data)) {
                $this->syncHosts($liveAccount, $data);
            }
        });

        return back()->with('success', 'Live account updated.');
    }

    public function destroy(Request $request, LiveAccount $liveAccount): RedirectResponse
    {
        abort_if($request->user()?->isLiveHostAssistant() === true, 403);

        $liveAccount->delete();

        return back()->with('success', 'Live account removed.');
    }

    /**
     * Attach an existing live account to a host (from the host detail page).
     */
    public function attachHost(Request $request, User $host): RedirectResponse
    {
        abort_if($request->user()?->isLiveHostAssistant() === true, 403);

        $validated = $request->validate([
            'live_account_id' => ['required', 'integer', 'exists:live_accounts,id'],
        ]);

        LiveAccount::findOrFail($validated['live_account_id'])
            ->hosts()
            ->syncWithoutDetaching([$host->id => []]);

        return back()->with('success', 'Account linked to host.');
    }

    public function detachHost(Request $request, User $host, LiveAccount $liveAccount): RedirectResponse
    {
        abort_if($request->user()?->isLiveHostAssistant() === true, 403);

        $liveAccount->hosts()->detach($host->id);

        return back()->with('success', 'Account unlinked from host.');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function syncShops(LiveAccount $account, array $data): void
    {
        $shopIds = collect($data['shop_ids'] ?? [])->map(fn ($id) => (int) $id)->all();
        $primary = isset($data['primary_shop_id']) ? (int) $data['primary_shop_id'] : null;

        $payload = [];
        foreach ($shopIds as $id) {
            $payload[$id] = ['is_primary' => $id === $primary];
        }

        $account->shops()->sync($payload);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function syncHosts(LiveAccount $account, array $data): void
    {
        $hostIds = collect($data['host_ids'] ?? [])->map(fn ($id) => (int) $id)->all();
        $account->hosts()->sync($hostIds);
    }

    /**
     * @return array<string, mixed>
     */
    private function mapAccount(LiveAccount $account): array
    {
        return [
            'id' => $account->id,
            'creator_user_id' => $account->creator_user_id,
            'nickname' => $account->nickname,
            'display_name' => $account->display_name,
            'label' => $account->label,
            'is_active' => (bool) $account->is_active,
            'needs_review' => (bool) $account->needs_review,
            'shops' => $account->shops->map(fn (PlatformAccount $s) => [
                'id' => $s->id,
                'name' => $s->name,
                'is_primary' => (bool) $s->pivot->is_primary,
            ])->values()->all(),
            'hosts' => $account->hosts->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
            ])->values()->all(),
        ];
    }

    /**
     * @return \Illuminate\Support\Collection<int, array{id: int, name: string}>
     */
    private function shopOptions(): \Illuminate\Support\Collection
    {
        return PlatformAccount::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (PlatformAccount $a) => ['id' => $a->id, 'name' => $a->name]);
    }

    /**
     * @return \Illuminate\Support\Collection<int, array{id: int, name: string}>
     */
    private function hostOptions(): \Illuminate\Support\Collection
    {
        return User::query()
            ->where('role', 'live_host')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name]);
    }
}
