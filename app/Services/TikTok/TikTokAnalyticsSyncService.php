<?php

declare(strict_types=1);

namespace App\Services\TikTok;

use App\Models\Content;
use App\Models\ContentStat;
use App\Models\PlatformAccount;
use App\Models\PlatformApp;
use App\Models\TiktokProductPerformance;
use App\Models\TiktokShopPerformanceSnapshot;
use Illuminate\Support\Facades\Log;

class TikTokAnalyticsSyncService
{
    protected const REQUIRED_CATEGORY = PlatformApp::CATEGORY_ANALYTICS_REPORTING;

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

        $response = $client->Analytics->getShopPerformance([
            'start_date_ge' => now()->subDays(30)->format('Y-m-d'),
            'end_date_lt' => now()->format('Y-m-d'),
        ]);

        $interval = $response['performance']['intervals'][0] ?? [];

        $videoImpressions = collect($interval['product_impression_breakdowns'] ?? [])
            ->firstWhere('type', 'VIDEO')['amount'] ?? 0;

        $pageViews = (int) ($interval['product_page_views'] ?? 0);
        $buyers = (int) ($interval['buyers'] ?? 0);
        $conversionRate = $pageViews > 0 ? round(($buyers / $pageViews) * 100, 4) : 0;

        return TiktokShopPerformanceSnapshot::create([
            'platform_account_id' => $account->id,
            'total_orders' => (int) ($interval['orders'] ?? 0),
            'total_gmv' => (float) ($interval['gmv']['amount'] ?? 0),
            'total_buyers' => $buyers,
            'total_video_views' => (int) $videoImpressions,
            'total_product_impressions' => (int) ($interval['product_impressions'] ?? 0),
            'conversion_rate' => $conversionRate,
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

        $response = $client->Analytics->getShopVideoPerformanceList([
            'start_date_ge' => now()->subDays(30)->format('Y-m-d'),
            'end_date_lt' => now()->format('Y-m-d'),
            'page_size' => 100,
        ]);

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
     *
     * Iterates pages via TikTok's `next_page_token` cursor and upserts each
     * product keyed on (platform_account_id, tiktok_product_id) so re-syncs
     * refresh existing rows instead of duplicating them.
     */
    public function syncProductPerformance(PlatformAccount $account): int
    {
        $client = $this->getClient($account);

        $count = 0;
        $pageToken = null;
        $page = 0;
        $maxPages = 50;

        do {
            $params = [
                'start_date_ge' => now()->subDays(30)->format('Y-m-d'),
                'end_date_lt' => now()->format('Y-m-d'),
                'page_size' => 100,
            ];

            if ($pageToken) {
                $params['page_token'] = $pageToken;
            }

            $response = $client->Analytics->getShopProductPerformanceList($params);
            $products = $response['products'] ?? $response['data'] ?? [];

            foreach ($products as $product) {
                $productId = $product['product_id'] ?? $product['id'] ?? null;

                if (! $productId) {
                    continue;
                }

                // TikTok returns gmv as {amount, currency} and click_through_rate as a 0-1 decimal.
                $gmv = is_array($product['gmv'] ?? null) ? (float) ($product['gmv']['amount'] ?? 0) : (float) ($product['gmv'] ?? 0);
                $ctr = isset($product['click_through_rate']) ? round(((float) $product['click_through_rate']) * 100, 4) : 0;

                TiktokProductPerformance::updateOrCreate(
                    [
                        'platform_account_id' => $account->id,
                        'tiktok_product_id' => (string) $productId,
                    ],
                    [
                        'impressions' => (int) ($product['impressions'] ?? 0),
                        'clicks' => (int) ($product['clicks'] ?? 0),
                        'orders' => (int) ($product['orders'] ?? 0),
                        'gmv' => $gmv,
                        'buyers' => (int) ($product['buyers'] ?? 0),
                        'conversion_rate' => $product['conversion_rate'] ?? $ctr,
                        'raw_response' => $product,
                        'fetched_at' => now(),
                    ]
                );

                $count++;
            }

            $pageToken = $response['next_page_token'] ?? null;
            $page++;
        } while ($pageToken && $page < $maxPages);

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
        $app = $this->clientFactory->resolveApp($account, static::REQUIRED_CATEGORY);

        if ($this->authService->needsTokenRefresh($account, $app)) {
            Log::info('[TikTok Sync] Refreshing token before sync', [
                'account_id' => $account->id,
                'platform_app_id' => $app->id,
                'category' => static::REQUIRED_CATEGORY,
            ]);
            $this->authService->refreshToken($account, $app);
        }

        $client = $this->clientFactory->createClientForAccount($account, static::REQUIRED_CATEGORY);
        $client->useVersion($this->clientFactory->getApiVersion());

        return $client;
    }
}
