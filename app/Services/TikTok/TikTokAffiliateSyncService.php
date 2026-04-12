<?php

declare(strict_types=1);

namespace App\Services\TikTok;

use App\Models\PlatformAccount;
use App\Models\TiktokAffiliateOrder;
use App\Models\TiktokCreator;
use App\Models\TiktokCreatorContent;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Log;

class TikTokAffiliateSyncService
{
    public function __construct(
        private TikTokClientFactory $clientFactory,
        private TikTokAuthService $authService
    ) {}

    /**
     * Sync creators from TikTok marketplace.
     */
    public function syncCreators(PlatformAccount $account): int
    {
        $client = $this->getClient($account);

        $response = $client->AffiliateSeller->searchCreatorOnMarketplace(['page_size' => 100], []);

        $creators = $response['creators'] ?? $response['data'] ?? [];
        $count = 0;

        foreach ($creators as $creator) {
            $creatorUserId = $creator['creator_user_id'] ?? $creator['id'] ?? null;

            if (! $creatorUserId) {
                continue;
            }

            TiktokCreator::updateOrCreate(
                [
                    'platform_account_id' => $account->id,
                    'creator_user_id' => $creatorUserId,
                ],
                [
                    'handle' => $creator['handle'] ?? null,
                    'display_name' => $creator['display_name'] ?? $creator['nickname'] ?? null,
                    'avatar_url' => $creator['avatar_url'] ?? $creator['avatar'] ?? null,
                    'country_code' => $creator['country_code'] ?? $creator['region'] ?? null,
                    'follower_count' => $creator['follower_count'] ?? 0,
                    'raw_response' => $creator,
                ]
            );

            $count++;
        }

        return $count;
    }

    /**
     * Sync performance data for a single creator.
     */
    public function syncCreatorPerformance(PlatformAccount $account, TiktokCreator $creator): TiktokCreator
    {
        $client = $this->getClient($account);

        $response = $client->AffiliateSeller->getMarketplaceCreatorPerformance($creator->creator_user_id);

        $creator->update([
            'total_gmv' => $response['total_gmv'] ?? $creator->total_gmv,
            'total_orders' => $response['total_orders'] ?? $creator->total_orders,
            'total_commission' => $response['total_commission'] ?? $creator->total_commission,
            'follower_count' => $response['follower_count'] ?? $creator->follower_count,
            'performance_fetched_at' => now(),
            'raw_response' => $response,
        ]);

        return $creator->refresh();
    }

    /**
     * Sync performance for all creators belonging to an account.
     */
    public function syncAllCreatorPerformance(PlatformAccount $account): int
    {
        $creators = TiktokCreator::where('platform_account_id', $account->id)->get();
        $count = 0;

        foreach ($creators as $creator) {
            try {
                $this->syncCreatorPerformance($account, $creator);
                $count++;
            } catch (Exception $e) {
                Log::warning('[TikTok Affiliate Sync] Failed to sync creator performance', [
                    'account_id' => $account->id,
                    'creator_id' => $creator->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $count;
    }

    /**
     * Sync affiliate orders.
     */
    public function syncAffiliateOrders(PlatformAccount $account): int
    {
        $client = $this->getClient($account);

        $response = $client->AffiliateSeller->searchSellerAffiliateOrders(['page_size' => 100]);

        $orders = $response['orders'] ?? $response['data'] ?? [];
        $count = 0;

        foreach ($orders as $order) {
            $tiktokOrderId = $order['order_id'] ?? $order['id'] ?? null;

            if (! $tiktokOrderId) {
                continue;
            }

            // Try to link to a creator
            $creatorUserId = $order['creator_user_id'] ?? null;
            $tiktokCreatorId = null;

            if ($creatorUserId) {
                $creator = TiktokCreator::where('platform_account_id', $account->id)
                    ->where('creator_user_id', $creatorUserId)
                    ->first();

                $tiktokCreatorId = $creator?->id;
            }

            // Parse order_created_at from unix timestamp
            $orderCreatedAt = isset($order['order_created_at'])
                ? Carbon::createFromTimestamp($order['order_created_at'])
                : null;

            TiktokAffiliateOrder::updateOrCreate(
                [
                    'platform_account_id' => $account->id,
                    'tiktok_order_id' => $tiktokOrderId,
                ],
                [
                    'tiktok_creator_id' => $tiktokCreatorId,
                    'creator_user_id' => $creatorUserId,
                    'tiktok_product_id' => $order['product_id'] ?? null,
                    'order_status' => $order['order_status'] ?? $order['status'] ?? null,
                    'order_amount' => $order['order_amount'] ?? 0,
                    'commission_amount' => $order['commission_amount'] ?? 0,
                    'commission_rate' => $order['commission_rate'] ?? 0,
                    'collaboration_type' => $order['collaboration_type'] ?? null,
                    'order_created_at' => $orderCreatedAt,
                    'raw_response' => $order,
                ]
            );

            $count++;
        }

        return $count;
    }

    /**
     * Sync creator collaboration content.
     */
    public function syncCreatorContent(PlatformAccount $account): int
    {
        $client = $this->getClient($account);

        $response = $client->AffiliateSeller->getOpenCollaborationCreatorContentDetail(['page_size' => 100]);

        $items = $response['contents'] ?? $response['data'] ?? [];
        $count = 0;

        foreach ($items as $item) {
            // Find or auto-create a creator stub
            $creatorUserId = $item['creator_user_id'] ?? $item['creator_id'] ?? null;
            $creator = null;

            if ($creatorUserId) {
                $creator = TiktokCreator::firstOrCreate(
                    [
                        'platform_account_id' => $account->id,
                        'creator_user_id' => $creatorUserId,
                    ],
                    [
                        'handle' => $item['creator_handle'] ?? null,
                        'display_name' => $item['creator_name'] ?? null,
                        'raw_response' => [],
                    ]
                );
            }

            TiktokCreatorContent::create([
                'tiktok_creator_id' => $creator?->id,
                'platform_account_id' => $account->id,
                'creator_video_id' => $item['video_id'] ?? $item['creator_video_id'] ?? null,
                'tiktok_product_id' => $item['product_id'] ?? $item['tiktok_product_id'] ?? null,
                'views' => $item['views'] ?? 0,
                'likes' => $item['likes'] ?? 0,
                'comments' => $item['comments'] ?? 0,
                'shares' => $item['shares'] ?? 0,
                'gmv' => $item['gmv'] ?? 0,
                'orders' => $item['orders'] ?? 0,
                'raw_response' => $item,
                'fetched_at' => now(),
            ]);

            $count++;
        }

        return $count;
    }

    /**
     * Get an authenticated client, refreshing the token if needed.
     */
    protected function getClient(PlatformAccount $account): mixed
    {
        if ($this->authService->needsTokenRefresh($account)) {
            Log::info('[TikTok Affiliate Sync] Refreshing token before sync', [
                'account_id' => $account->id,
            ]);

            $this->authService->refreshToken($account);
        }

        return $this->clientFactory->createClientForAccount($account);
    }
}
