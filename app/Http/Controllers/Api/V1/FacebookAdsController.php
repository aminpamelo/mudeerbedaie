<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\FacebookAdAccount;
use App\Models\FacebookAdConnection;
use App\Models\FacebookAdInsight;
use App\Services\Funnel\FacebookAdConnectionManager;
use App\Services\Funnel\FacebookAdsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Facebook Ads connections for the Funnel Studio. Admin/employee manage the
 * shared company Business Managers; fighters link their own BMs from the
 * Fighter portal (Fighter\BusinessManagerController).
 */
class FacebookAdsController extends Controller
{
    public function __construct(
        protected FacebookAdsService $adsService
    ) {}

    protected function authorizeManage(Request $request): void
    {
        $user = $request->user();
        if (! $user || ! in_array($user->role, ['admin', 'employee'], true)) {
            abort(403, 'Only admin can manage Facebook Ads connections.');
        }
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorizeManage($request);

        $from = now()->subDays(30)->startOfDay();

        $spendByAccount = FacebookAdInsight::query()
            ->where('date', '>=', $from)
            ->selectRaw('facebook_ad_account_id, SUM(spend) as spend, SUM(impressions) as impressions, SUM(clicks) as clicks')
            ->groupBy('facebook_ad_account_id')
            ->get()
            ->keyBy('facebook_ad_account_id');

        $connections = FacebookAdConnection::query()
            ->with('adAccounts')
            ->latest()
            ->get()
            ->map(fn (FacebookAdConnection $connection) => [
                'id' => $connection->id,
                'name' => $connection->name,
                'business_manager_id' => $connection->business_manager_id,
                'status' => $connection->status,
                'status_message' => $connection->status_message,
                'last_synced_at' => $connection->last_synced_at?->toIso8601String(),
                'accounts' => $connection->adAccounts->map(fn (FacebookAdAccount $account) => [
                    'id' => $account->id,
                    'account_id' => $account->account_id,
                    'name' => $account->name,
                    'currency' => $account->currency,
                    'account_status' => $account->account_status,
                    'spend_30d' => (float) ($spendByAccount[$account->id]->spend ?? 0),
                    'impressions_30d' => (int) ($spendByAccount[$account->id]->impressions ?? 0),
                    'clicks_30d' => (int) ($spendByAccount[$account->id]->clicks ?? 0),
                    'linked_funnels_count' => $account->linkedFunnelsCount(),
                ]),
            ]);

        return response()->json(['data' => $connections]);
    }

    public function store(Request $request, FacebookAdConnectionManager $manager): JsonResponse
    {
        $this->authorizeManage($request);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'business_manager_id' => ['required', 'string', 'max:50'],
            'access_token' => ['required', 'string'],
        ]);

        $result = $manager->connect($validated);

        return response()->json([
            'success' => $result['success'],
            'message' => $result['message'],
            'connection_id' => $result['connection']?->id,
            'accounts_count' => $result['accounts_count'],
        ], $result['success'] ? 201 : 422);
    }

    /**
     * Edit a connection's name, Business Manager ID, and/or access token.
     * See FacebookAdConnectionManager::update() for the verify/rollback rules.
     */
    public function update(Request $request, FacebookAdConnection $connection, FacebookAdConnectionManager $manager): JsonResponse
    {
        $this->authorizeManage($request);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'business_manager_id' => ['required', 'string', 'max:50'],
            'access_token' => ['nullable', 'string'],
        ]);

        $result = $manager->update($connection, $validated);

        if (! $result['success']) {
            return response()->json(['success' => false, 'message' => $result['message']], 422);
        }

        return response()->json($result);
    }

    public function destroy(Request $request, FacebookAdConnection $connection): JsonResponse
    {
        $this->authorizeManage($request);

        $connection->delete();

        return response()->json(['success' => true]);
    }

    /**
     * Full sync of one connection (verify + accounts + 30 days of insights).
     */
    public function sync(Request $request, FacebookAdConnection $connection): JsonResponse
    {
        $this->authorizeManage($request);

        $result = $this->adsService->syncConnection($connection, (int) $request->input('days', 30));

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    /**
     * Flat list of ad accounts for the funnel "which ads app feeds this
     * funnel" selector. Fighters only see accounts under their own BMs.
     */
    public function accounts(Request $request): JsonResponse
    {
        $user = $request->user();

        $accounts = FacebookAdAccount::query()
            ->when($user?->isFighter(), fn ($q) => $q->ownedBy($user->id))
            ->with('connection:id,name')
            ->orderBy('name')
            ->get()
            ->map(fn (FacebookAdAccount $account) => [
                'id' => $account->id,
                'account_id' => $account->account_id,
                'name' => $account->name,
                'connection_name' => $account->connection?->name,
            ]);

        return response()->json(['data' => $accounts]);
    }
}
