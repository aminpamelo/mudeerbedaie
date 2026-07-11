<?php

namespace App\Http\Controllers\LiveHost;

use App\Http\Controllers\Controller;
use App\Http\Requests\LiveHost\StoreCreatorRequest;
use App\Http\Requests\LiveHost\UpdateCreatorRequest;
use App\Models\LiveAccount;
use App\Models\LiveHostPlatformAccount;
use App\Models\PlatformAccount;
use App\Models\User;
use App\Services\LiveHost\LiveAccountResolver;
use App\Services\LiveHost\SuggestedSlotFinder;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Manages the live_host_platform_account pivot as a first-class "Creator"
 * record — (host × platform account) + TikTok creator identity. Populating
 * creator_platform_user_id here is what lets the TikTok import matcher
 * (LiveSessionMatcher) link report rows to live sessions.
 *
 * Registering a creator here also marks its canonical LiveAccount as `linked`
 * (a linked TikTok Shop account) — the signal the timetable filters on so only
 * the shop's own creators appear, and affiliate lives stay hidden.
 */
class CreatorController extends Controller
{
    /**
     * How many days back the "belum diklasifikasi" nudge scans for creators
     * who went live but aren't linked yet.
     */
    private const UNCLASSIFIED_WINDOW_DAYS = 14;

    public function __construct(
        private LiveAccountResolver $accounts,
        private SuggestedSlotFinder $suggestions,
    ) {}

    public function index(Request $request): Response
    {
        $search = $request->string('search')->toString();
        $platformAccount = $request->string('platform_account')->toString();
        $shopId = $platformAccount !== '' ? (int) $platformAccount : null;

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
            ->when($shopId !== null, fn ($q) => $q->where('platform_account_id', $shopId))
            ->orderByRaw('creator_handle IS NULL')
            ->orderBy('creator_handle')
            ->orderByDesc('is_primary')
            ->orderBy('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (LiveHostPlatformAccount $c) => $this->mapCreator($c));

        $unclassified = $this->suggestions->unclassifiedCreators(
            CarbonImmutable::now()->subDays(self::UNCLASSIFIED_WINDOW_DAYS),
            CarbonImmutable::now(),
            $shopId,
        );

        return Inertia::render('creators/Index', [
            'creators' => $creators,
            'filters' => ['search' => $search, 'platform_account' => $platformAccount],
            'hosts' => $this->hostOptions(),
            'platformAccounts' => $this->platformAccountOptions(),
            'unclassified' => $unclassified,
            'unclassifiedWindowDays' => self::UNCLASSIFIED_WINDOW_DAYS,
        ]);
    }

    public function store(StoreCreatorRequest $request): RedirectResponse
    {
        abort_if($request->user()?->isLiveHostAssistant() === true, 403);

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
            $this->markAccountLinked($creator);
        });

        return back()->with('success', 'Creator added.');
    }

    public function update(UpdateCreatorRequest $request, LiveHostPlatformAccount $creator): RedirectResponse
    {
        abort_if($request->user()?->isLiveHostAssistant() === true, 403);

        $data = $request->validated();

        DB::transaction(function () use ($creator, $data) {
            $creator->fill([
                'creator_handle' => $data['creator_handle'] ?? null,
                'creator_platform_user_id' => $data['creator_platform_user_id'],
                'is_primary' => (bool) ($data['is_primary'] ?? $creator->is_primary),
            ])->save();

            $this->demoteOtherPrimaries($creator);
            $this->markAccountLinked($creator);
        });

        return redirect()
            ->route('livehost.creators.index')
            ->with('success', 'Creator updated.');
    }

    public function destroy(Request $request, LiveHostPlatformAccount $creator): RedirectResponse
    {
        abort_if($request->user()?->isLiveHostAssistant() === true, 403);

        $creator->delete();

        return redirect()
            ->route('livehost.creators.index')
            ->with('success', 'Creator removed.');
    }

    /**
     * Mark the creator's canonical LiveAccount as a linked TikTok Shop account.
     * Resolves an existing account by Creator ID (then handle); if none exists
     * yet, creates one so the timetable can show this creator's lives right away.
     * Back-fills the Creator ID onto a handle-only account we now have it for.
     */
    private function markAccountLinked(LiveHostPlatformAccount $creator): void
    {
        $creatorId = $creator->creator_platform_user_id !== null
            ? trim((string) $creator->creator_platform_user_id)
            : null;

        $account = $this->accounts->fromCreatorId($creatorId)
            ?? $this->accounts->fromHandle($creator->creator_handle);

        if ($account === null) {
            $account = new LiveAccount([
                'creator_user_id' => $creatorId ?: null,
                'nickname' => $creator->creator_handle,
                'display_name' => $creator->creator_handle,
                'normalized_handle' => LiveAccount::normalizeHandle($creator->creator_handle),
                'is_active' => true,
                'needs_review' => false,
            ]);
        } elseif ($account->creator_user_id === null && $creatorId !== null && $creatorId !== '') {
            $account->creator_user_id = $creatorId;
        }

        $account->account_type = LiveAccount::TYPE_LINKED;
        $account->save();
    }

    /**
     * @return array<string, mixed>
     */
    private function mapCreator(LiveHostPlatformAccount $creator): array
    {
        $account = $this->accounts->fromCreatorId($creator->creator_platform_user_id)
            ?? $this->accounts->fromHandle($creator->creator_handle);

        return [
            'id' => $creator->id,
            'user_id' => $creator->user_id,
            'platform_account_id' => $creator->platform_account_id,
            'creator_handle' => $creator->creator_handle,
            'creator_platform_user_id' => $creator->creator_platform_user_id,
            'is_primary' => (bool) $creator->is_primary,
            'live_account' => $account ? [
                'id' => $account->id,
                'account_type' => $account->account_type,
                'is_linked' => $account->isLinked(),
            ] : null,
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
     * @return Collection<int, array<string, mixed>>
     */
    private function hostOptions(): Collection
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
     * @return Collection<int, array<string, mixed>>
     */
    private function platformAccountOptions(): Collection
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
