<?php

namespace App\Http\Controllers\LiveHost;

use App\Http\Controllers\Controller;
use App\Http\Requests\LiveHost\StoreLiveAccountRequest;
use App\Http\Requests\LiveHost\UpdateLiveAccountRequest;
use App\Models\LiveAccount;
use App\Models\PlatformAccount;
use App\Models\User;
use App\Services\LiveHost\LiveAccountResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
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
    public function __construct(private LiveAccountResolver $resolver) {}

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
                // Creating an account by hand (incl. the register-creator prompt)
                // is a deliberate "this is our creator" — default it to linked.
                'account_type' => $data['account_type'] ?? LiveAccount::TYPE_LINKED,
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
                'account_type' => $data['account_type'] ?? $liveAccount->account_type,
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
     * One-click classify a creator as linked / affiliate / unknown from the
     * Creators "belum diklasifikasi" list. Works by handle alone — the TikTok
     * shop_lives API returns no Creator ID, so requiring one here would block
     * every API-discovered creator. Resolves an existing account (by id, then
     * Creator ID, then handle); creates a handle-only account if none exists.
     */
    public function classify(Request $request): RedirectResponse
    {
        abort_if($request->user()?->isLiveHostAssistant() === true, 403);

        $validated = $request->validate([
            'live_account_id' => ['nullable', 'integer', 'exists:live_accounts,id'],
            'creator_handle' => ['nullable', 'string', 'max:255'],
            'creator_user_id' => ['nullable', 'string', 'max:255'],
            'account_type' => ['required', Rule::in(LiveAccount::ACCOUNT_TYPES)],
            'shop_ids' => ['array'],
            'shop_ids.*' => ['integer', 'exists:platform_accounts,id'],
        ], [], ['account_type' => 'account type']);

        $handle = $validated['creator_handle'] ?? null;
        $creatorId = $validated['creator_user_id'] ?? null;

        DB::transaction(function () use ($validated, $handle, $creatorId): void {
            $account = isset($validated['live_account_id'])
                ? LiveAccount::find($validated['live_account_id'])
                : ($this->resolver->fromCreatorId($creatorId) ?? $this->resolver->fromHandle($handle));

            if ($account === null) {
                abort_if($handle === null && ($creatorId === null || $creatorId === ''), 422, 'A handle or Creator ID is required.');

                $account = new LiveAccount([
                    'creator_user_id' => $creatorId ?: null,
                    'nickname' => $handle,
                    'display_name' => $handle,
                    'normalized_handle' => LiveAccount::normalizeHandle($handle),
                    'is_active' => true,
                    'needs_review' => false,
                ]);
            }

            $account->account_type = $validated['account_type'];
            $account->save();

            // Record which TikTok Shop(s) a linked account belongs to — the
            // shops it actually went live on. First shop is primary if the
            // account has none yet.
            $shopIds = array_values(array_unique(array_map('intval', $validated['shop_ids'] ?? [])));
            if ($validated['account_type'] === LiveAccount::TYPE_LINKED && $shopIds !== []) {
                $hasPrimary = $account->shops()->wherePivot('is_primary', true)->exists();
                $payload = [];
                foreach ($shopIds as $i => $id) {
                    $payload[$id] = ['is_primary' => ! $hasPrimary && $i === 0];
                }
                $account->shops()->syncWithoutDetaching($payload);
            }
        });

        $label = $validated['account_type'] === LiveAccount::TYPE_LINKED
            ? 'linked TikTok Shop account'
            : $validated['account_type'];

        return back()->with('success', "Creator marked as {$label}.");
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
            'account_type' => $account->account_type,
            'is_linked' => $account->isLinked(),
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
     * @return Collection<int, array{id: int, name: string}>
     */
    private function shopOptions(): Collection
    {
        return PlatformAccount::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (PlatformAccount $a) => ['id' => $a->id, 'name' => $a->name]);
    }

    /**
     * @return Collection<int, array{id: int, name: string}>
     */
    private function hostOptions(): Collection
    {
        return User::query()
            ->where('role', 'live_host')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name]);
    }
}
