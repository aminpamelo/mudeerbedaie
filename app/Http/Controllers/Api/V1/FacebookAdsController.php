<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\FacebookAdAccount;
use App\Models\FacebookAdConnection;
use App\Models\FacebookAdInsight;
use App\Services\Funnel\FacebookAdsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Facebook Ads connections for the Funnel Studio. Admin/employee manage the
 * shared company Business Managers; fighter self-linking comes later.
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

    public function store(Request $request): JsonResponse
    {
        $this->authorizeManage($request);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'business_manager_id' => ['required', 'string', 'max:50'],
            'access_token' => ['required', 'string'],
        ]);

        $connection = FacebookAdConnection::create($validated);

        $verify = $this->adsService->verifyConnection($connection);
        $accountsCount = 0;
        if ($verify['success']) {
            $accounts = $this->adsService->syncAdAccounts($connection);
            $accountsCount = $accounts['count'] ?? 0;
        } else {
            // Don't keep a broken connection from a failed onboarding attempt —
            // the wizard lets the user fix the token and retry cleanly.
            $connection->delete();
        }

        return response()->json([
            'success' => $verify['success'],
            'message' => $verify['message'],
            'connection_id' => $verify['success'] ? $connection->id : null,
            'accounts_count' => $accountsCount,
        ], $verify['success'] ? 201 : 422);
    }

    /**
     * Edit a connection's name, Business Manager ID, and/or access token.
     * The name is applied as-is; changing the BM ID or pasting a new token
     * re-verifies with Facebook. A failed verify rolls the credentials back
     * so a previously-working connection is never broken by a bad edit.
     */
    public function update(Request $request, FacebookAdConnection $connection): JsonResponse
    {
        $this->authorizeManage($request);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'business_manager_id' => ['required', 'string', 'max:50'],
            'access_token' => ['nullable', 'string'],
        ]);

        $newBmId = preg_replace('/\s+/', '', $validated['business_manager_id']);
        $bmChanged = $newBmId !== $connection->business_manager_id;
        $tokenProvided = filled($validated['access_token'] ?? null);

        // Name is non-verifiable, so it is always applied.
        if (! $bmChanged && ! $tokenProvided) {
            $connection->update(['name' => $validated['name']]);

            return response()->json([
                'success' => true,
                'message' => 'Connection updated.',
                'accounts_count' => $connection->adAccounts()->count(),
            ]);
        }

        // Snapshot the last working credentials + status so a failed verify
        // can be rolled back cleanly.
        $previous = [
            'business_manager_id' => $connection->business_manager_id,
            'access_token' => $connection->access_token,
            'status' => $connection->status,
            'status_message' => $connection->status_message,
        ];

        $attributes = [
            'name' => $validated['name'],
            'business_manager_id' => $newBmId,
        ];
        if ($tokenProvided) {
            $attributes['access_token'] = trim($validated['access_token']);
        }
        $connection->update($attributes);

        $verify = $this->adsService->verifyConnection($connection);

        if (! $verify['success']) {
            // Restore the working credentials + status; keep the new name.
            $connection->update($previous + ['name' => $validated['name']]);

            return response()->json([
                'success' => false,
                'message' => $verify['message'],
            ], 422);
        }

        // A different BM means the old ad accounts (and their insights) belong
        // elsewhere — clear them before pulling the new BM's accounts.
        if ($bmChanged) {
            $oldAccountIds = $connection->adAccounts()->pluck('id');
            FacebookAdInsight::whereIn('facebook_ad_account_id', $oldAccountIds)->delete();
            $connection->adAccounts()->delete();
        }

        $accounts = $this->adsService->syncAdAccounts($connection);

        return response()->json([
            'success' => true,
            'message' => $verify['message'],
            'accounts_count' => $accounts['count'] ?? 0,
        ]);
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
     * funnel" selector.
     */
    public function accounts(Request $request): JsonResponse
    {
        $accounts = FacebookAdAccount::query()
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
