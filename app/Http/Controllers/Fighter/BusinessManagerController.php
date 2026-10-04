<?php

namespace App\Http\Controllers\Fighter;

use App\Http\Controllers\Controller;
use App\Http\Requests\Fighter\AdConnectionRequest;
use App\Http\Requests\Fighter\LinkFunnelAdAccountRequest;
use App\Models\FacebookAdAccount;
use App\Models\FacebookAdConnection;
use App\Models\FacebookAdInsight;
use App\Models\Funnel;
use App\Services\Funnel\FacebookAdConnectionManager;
use App\Services\Funnel\FacebookAdsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The fighter's own Facebook Business Managers: link a BM with a read-only
 * System User token, see its ad accounts and 30-day spend, sync on demand,
 * and point each of their funnels at the ad account that feeds it. The spend
 * then flows into the fighter's Daily Reporting page.
 */
class BusinessManagerController extends Controller
{
    public function index(Request $request): Response
    {
        $userId = (int) $request->user()->id;

        $connections = FacebookAdConnection::query()
            ->where('user_id', $userId)
            ->with(['adAccounts' => fn ($q) => $q->orderBy('name')])
            ->latest()
            ->get();

        $accountIds = $connections->flatMap->adAccounts->pluck('id');

        $spendByAccount = FacebookAdInsight::query()
            ->whereIn('facebook_ad_account_id', $accountIds)
            ->where('date', '>=', now()->subDays(29)->toDateString())
            ->selectRaw('facebook_ad_account_id, SUM(spend) as spend, SUM(impressions) as impressions, SUM(clicks) as clicks')
            ->groupBy('facebook_ad_account_id')
            ->get()
            ->keyBy('facebook_ad_account_id');

        $funnels = Funnel::query()
            ->forUser($userId)
            ->orderBy('name')
            ->get(['id', 'uuid', 'name', 'status', 'settings']);

        $linkedCount = $funnels
            ->map(fn (Funnel $f) => data_get($f->settings, 'ads.facebook_ad_account_id'))
            ->filter()
            ->countBy();

        return Inertia::render('BusinessManager', [
            'connections' => $connections->map(fn (FacebookAdConnection $connection): array => [
                'id' => $connection->id,
                'name' => $connection->name,
                'business_manager_id' => $connection->business_manager_id,
                'status' => $connection->status,
                'status_message' => $connection->status_message,
                'last_synced_at' => $connection->last_synced_at?->toIso8601String(),
                'accounts' => $connection->adAccounts->map(fn (FacebookAdAccount $account): array => [
                    'id' => $account->id,
                    'account_id' => $account->account_id,
                    'name' => $account->name,
                    'currency' => $account->currency,
                    'account_status' => $account->account_status,
                    'spend_30d' => round((float) ($spendByAccount[$account->id]->spend ?? 0), 2),
                    'impressions_30d' => (int) ($spendByAccount[$account->id]->impressions ?? 0),
                    'clicks_30d' => (int) ($spendByAccount[$account->id]->clicks ?? 0),
                    'linked_funnels_count' => (int) ($linkedCount[$account->id] ?? 0),
                ])->values(),
            ])->values(),
            'funnels' => $funnels->map(fn (Funnel $f): array => [
                'uuid' => $f->uuid,
                'name' => $f->name,
                'status' => $f->status,
                'facebook_ad_account_id' => data_get($f->settings, 'ads.facebook_ad_account_id'),
            ])->values(),
        ]);
    }

    public function store(AdConnectionRequest $request, FacebookAdConnectionManager $manager, FacebookAdsService $adsService): JsonResponse
    {
        $result = $manager->connect($request->validated(), (int) $request->user()->id);

        if (! $result['success']) {
            return response()->json(['success' => false, 'message' => $result['message']], 422);
        }

        // Pull the last 30 days straight away so the reports aren't empty.
        $sync = $adsService->syncConnection($result['connection'], 30);

        return response()->json([
            'success' => true,
            'message' => $sync['message'] ?? $result['message'],
            'accounts_count' => $result['accounts_count'],
        ], 201);
    }

    public function update(AdConnectionRequest $request, FacebookAdConnection $connection, FacebookAdConnectionManager $manager): JsonResponse
    {
        $this->assertOwned($request, $connection);

        $result = $manager->update($connection, $request->validated());

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    public function destroy(Request $request, FacebookAdConnection $connection): JsonResponse
    {
        $this->assertOwned($request, $connection);

        $accountIds = $connection->adAccounts()->pluck('id')->all();
        $connection->delete();

        // Funnels pointing at accounts that no longer exist would show phantom
        // links, so clear their Ads Source.
        if ($accountIds !== []) {
            Funnel::query()
                ->forUser((int) $request->user()->id)
                ->get(['id', 'settings'])
                ->filter(fn (Funnel $f) => in_array((int) data_get($f->settings, 'ads.facebook_ad_account_id'), $accountIds, true))
                ->each(function (Funnel $f): void {
                    $settings = $f->settings ?? [];
                    data_set($settings, 'ads.facebook_ad_account_id', null);
                    $f->update(['settings' => $settings]);
                });
        }

        return response()->json(['success' => true]);
    }

    public function sync(Request $request, FacebookAdConnection $connection, FacebookAdsService $adsService): JsonResponse
    {
        $this->assertOwned($request, $connection);

        $result = $adsService->syncConnection($connection, 30);

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    public function linkFunnel(LinkFunnelAdAccountRequest $request, Funnel $funnel): JsonResponse
    {
        abort_unless((int) $funnel->user_id === (int) $request->user()->id, 404);

        $settings = $funnel->settings ?? [];
        data_set($settings, 'ads.facebook_ad_account_id', $request->validated('facebook_ad_account_id'));
        $funnel->update(['settings' => $settings]);

        return response()->json(['success' => true]);
    }

    /**
     * A fighter may only touch their own connections; anything else is a 404
     * so other fighters' or company BMs aren't even confirmed to exist.
     */
    private function assertOwned(Request $request, FacebookAdConnection $connection): void
    {
        abort_unless((int) $connection->user_id === (int) $request->user()->id, 404);
    }
}
