<?php

declare(strict_types=1);

namespace App\Services\TikTok;

use App\Models\Content;
use App\Models\ContentStat;
use App\Models\PlatformAccount;
use App\Models\TiktokProductPerformance;
use App\Models\TiktokShopPerformanceSnapshot;
use Illuminate\Support\Facades\Log;

class TikTokAnalyticsSyncService
{
    public function __construct(
        private TikTokClientFactory $clientFactory,
        private TikTokAuthService $authService
    ) {}

    /**
     * Sync shop-level performance snapshot.
     */
    public function syncShopPerformance(PlatformAccount $account): TiktokShopPerformanceSnapshot
    {
        $client = $this->getClient($account);

        $response = $client->Analytics->getShopPerformance();

        return TiktokShopPerformanceSnapshot::create([
            'platform_account_id' => $account->id,
            'total_orders' => $response['total_orders'] ?? 0,
            'total_gmv' => $response['total_gmv'] ?? 0,
            'total_buyers' => $response['total_buyers'] ?? 0,
            'total_video_views' => $response['total_video_views'] ?? 0,
            'total_product_impressions' => $response['total_product_impressions'] ?? 0,
            'conversion_rate' => $response['conversion_rate'] ?? 0,
            'raw_response' => $response,
            'fetched_at' => now(),
        ]);
    }

    /**
     * Sync video performance list and create ContentStat rows for matched Content.
     */
    public function syncVideoPerformanceList(PlatformAccount $account): int
    {
        $client = $this->getClient($account);

        $response = $client->Analytics->getShopVideoPerformanceList(['page_size' => 100]);

        $videos = $response['videos'] ?? $response['data'] ?? [];
        $count = 0;

        foreach ($videos as $video) {
            $videoId = $video['video_id'] ?? $video['id'] ?? null;

            if (! $videoId) {
                continue;
            }

            $content = Content::where('tiktok_post_id', $videoId)
                ->where('platform_account_id', $account->id)
                ->first();

            if ($content) {
                ContentStat::create([
                    'content_id' => $content->id,
                    'views' => $video['views'] ?? 0,
                    'likes' => $video['likes'] ?? 0,
                    'comments' => $video['comments'] ?? 0,
                    'shares' => $video['shares'] ?? 0,
                    'source' => 'tiktok_api_bulk',
                    'raw_response' => $video,
                    'fetched_at' => now(),
                ]);
            }

            $count++;
        }

        return $count;
    }

    /**
     * Sync product performance list.
     */
    public function syncProductPerformance(PlatformAccount $account): int
    {
        $client = $this->getClient($account);

        $response = $client->Analytics->getShopProductPerformanceList(['page_size' => 100]);

        $products = $response['products'] ?? $response['data'] ?? [];
        $count = 0;

        foreach ($products as $product) {
            TiktokProductPerformance::create([
                'platform_account_id' => $account->id,
                'tiktok_product_id' => $product['product_id'] ?? $product['id'] ?? null,
                'impressions' => $product['impressions'] ?? 0,
                'clicks' => $product['clicks'] ?? 0,
                'orders' => $product['orders'] ?? 0,
                'gmv' => $product['gmv'] ?? 0,
                'buyers' => $product['buyers'] ?? 0,
                'conversion_rate' => $product['conversion_rate'] ?? 0,
                'raw_response' => $product,
                'fetched_at' => now(),
            ]);

            $count++;
        }

        return $count;
    }

    /**
     * Fetch video performance overview (raw response).
     *
     * @return array<string, mixed>
     */
    public function fetchVideoPerformanceOverview(PlatformAccount $account): array
    {
        $client = $this->getClient($account);

        return $client->Analytics->getShopVideoPerformanceOverview();
    }

    /**
     * Fetch video product performance for a specific video (raw response).
     *
     * @return array<string, mixed>
     */
    public function fetchVideoProductPerformance(PlatformAccount $account, string $videoId): array
    {
        $client = $this->getClient($account);

        return $client->Analytics->getShopVideoProductPerformanceList($videoId);
    }

    /**
     * Get an authenticated client, refreshing the token if needed.
     */
    protected function getClient(PlatformAccount $account): mixed
    {
        if ($this->authService->needsTokenRefresh($account)) {
            Log::info('[TikTok Analytics Sync] Refreshing token before sync', [
                'account_id' => $account->id,
            ]);

            $this->authService->refreshToken($account);
        }

        return $this->clientFactory->createClientForAccount($account);
    }
}
