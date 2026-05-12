<?php

declare(strict_types=1);

namespace App\Services\TikTok;

use App\Models\ActualLiveRecord;
use App\Models\LiveHostPlatformAccount;
use App\Models\PlatformAccount;
use App\Models\PlatformApp;
use App\Models\TiktokLiveReport;
use App\Services\LiveHost\Tiktok\LiveSessionMatcher;
use App\Services\TikTok\Sdk\AnalyticsExtended;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use ReflectionMethod;

class TikTokLiveSyncService
{
    protected const REQUIRED_CATEGORY = PlatformApp::CATEGORY_ANALYTICS_REPORTING;

    private const API_VERSION = '202508';

    public function __construct(
        private TikTokClientFactory $clientFactory,
        private TikTokAuthService $authService,
        private LiveSessionMatcher $matcher,
    ) {}

    /**
     * Sync per-LIVE rows from TikTok's Shop Lives Performance API and upsert
     * into tiktok_live_reports keyed on (platform_account_id, tiktok_live_id).
     * Also mirrors a paired ActualLiveRecord (source='api_sync') so commission/payroll
     * downstream sees this row the same way as a CSV import.
     *
     * @return array{synced: int, created: int, updated: int, matched: int, unmatched: int, pages: int}
     */
    public function syncLivePerformance(
        PlatformAccount $account,
        ?Carbon $from = null,
        ?Carbon $to = null,
    ): array {
        // Strict === false: an absent or null value (e.g. on a freshly connected
        // account, or after a manual clear via unset()) does NOT trigger the skip.
        // Only an explicit false-write from a prior failed call suppresses syncs.
        if (($account->metadata['live_api_supported'] ?? null) === false) {
            Log::info('[TikTokLiveSync] Skipping account flagged as live_api_supported=false', [
                'account_id' => $account->id,
            ]);

            return [
                'synced' => 0,
                'created' => 0,
                'updated' => 0,
                'matched' => 0,
                'unmatched' => 0,
                'pages' => 0,
            ];
        }

        $client = $this->getClient($account);

        $from ??= now()->subDays(30);
        $to ??= now();

        $synced = 0;
        $created = 0;
        $updated = 0;
        $matched = 0;
        $unmatched = 0;
        $pages = 0;
        $pageToken = null;

        /** @var array<string, ?\App\Models\LiveHostPlatformAccount> */
        $pivotCache = [];

        try {
            do {
                $params = [
                    'start_date_ge' => $from->format('Y-m-d'),
                    'end_date_lt' => $to->format('Y-m-d'),
                    'page_size' => 100,
                ];

                if ($pageToken) {
                    $params['page_token'] = $pageToken;
                }

                $response = $client->Analytics->getShopLivePerformanceList($params);
                $sessions = $response['live_stream_sessions'] ?? [];

                foreach ($sessions as $session) {
                    $tiktokLiveId = $session['id'] ?? null;

                    if (! $tiktokLiveId) {
                        continue;
                    }

                    $attrs = $this->normalize($session);

                    /** @var TiktokLiveReport $report */
                    $report = TiktokLiveReport::firstOrNew([
                        'platform_account_id' => $account->id,
                        'tiktok_live_id' => $tiktokLiveId,
                    ]);
                    $existed = $report->exists;

                    // Preserve matched_live_session_id and import_id on re-sync.
                    $report->fill($attrs);
                    $report->platform_account_id = $account->id;
                    $report->tiktok_live_id = $tiktokLiveId;
                    $report->source = 'api';
                    $report->synced_at = now();
                    $report->save();

                    // Resolve creator_platform_user_id from the username via the
                    // live_host_platform_account pivot, scoped to this shop so a
                    // creator id can't bleed across sibling accounts. The matcher
                    // requires tiktok_creator_id to be non-null, so we do this first.
                    if ($report->tiktok_creator_id === null && $report->creator_nickname !== null) {
                        $key = $report->creator_nickname;
                        if (! array_key_exists($key, $pivotCache)) {
                            $pivotCache[$key] = LiveHostPlatformAccount::query()
                                ->where('platform_account_id', $account->id)
                                ->where('creator_handle', $key)
                                ->first();
                        }
                        $pivot = $pivotCache[$key];

                        if ($pivot && $pivot->creator_platform_user_id !== null) {
                            $report->tiktok_creator_id = $pivot->creator_platform_user_id;
                            $report->save();
                        }
                    }

                    if ($report->matched_live_session_id === null) {
                        $matchedSession = $this->matcher->match($report, $account->id);
                        if ($matchedSession !== null) {
                            $report->matched_live_session_id = $matchedSession->id;
                            $report->save();
                        }
                    }

                    // Mirror the ActualLiveRecord that ProcessTiktokImportJob creates
                    // for CSV imports. Keyed on (platform_account_id, source, source_record_id)
                    // so re-syncs update in place without duplicating.
                    ActualLiveRecord::updateOrCreate(
                        [
                            'platform_account_id' => $account->id,
                            'source' => 'api_sync',
                            'source_record_id' => $tiktokLiveId,
                        ],
                        [
                            'creator_platform_user_id' => $report->tiktok_creator_id,
                            'creator_handle' => $report->creator_nickname,
                            'launched_time' => $report->launched_time,
                            'duration_seconds' => $report->duration_seconds,
                            'gmv_myr' => $report->gmv_myr ?? 0,
                            'live_attributed_gmv_myr' => $report->live_attributed_gmv_myr ?? 0,
                            'viewers' => $report->viewers,
                            'views' => $report->views,
                            'comments' => $report->comments,
                            'shares' => $report->shares,
                            'likes' => $report->likes,
                            'new_followers' => $report->new_followers,
                            'products_added' => $report->products_added,
                            'products_sold' => $report->products_sold,
                            'items_sold' => $report->items_sold,
                            'sku_orders' => $report->sku_orders,
                            'unique_customers' => $report->unique_customers,
                            'avg_price_myr' => $report->avg_price_myr,
                            'click_to_order_rate' => $report->click_to_order_rate,
                            'ctr' => $report->ctr,
                            'raw_json' => $report->raw_row_json,
                        ],
                    );

                    $synced++;
                    $existed ? $updated++ : $created++;
                    $report->matched_live_session_id !== null ? $matched++ : $unmatched++;
                }

                $pageToken = $response['next_page_token'] ?? null;
                $pages++;
            } while ($pageToken && $pages < 50);
        } catch (\EcomPHP\TiktokShop\Errors\ResponseException $e) {
            if ($this->isNotAuthorized($e)) {
                Log::warning('[TikTokLiveSync] LIVE API not enabled for account', [
                    'account_id' => $account->id,
                    'code' => $e->getCode(),
                    'message' => $e->getMessage(),
                ]);

                $meta = $account->metadata ?? [];
                $meta['live_api_supported'] = false;
                $account->update(['metadata' => $meta]);

                return [
                    'synced' => 0,
                    'created' => 0,
                    'updated' => 0,
                    'matched' => 0,
                    'unmatched' => 0,
                    'pages' => 0,
                ];
            }

            throw $e;
        }

        Log::info('[TikTokLiveSync] Completed', [
            'account_id' => $account->id,
            'synced' => $synced,
            'created' => $created,
            'updated' => $updated,
            'matched' => $matched,
            'unmatched' => $unmatched,
            'pages' => $pages,
        ]);

        return compact('synced', 'created', 'updated', 'matched', 'unmatched', 'pages');
    }

    /**
     * Map an API live_stream_session payload into TiktokLiveReport columns.
     *
     * @param  array<string, mixed>  $s
     * @return array<string, mixed>
     */
    private function normalize(array $s): array
    {
        // Carbon::createFromTimestamp returns UTC by default. Convert to the
        // app timezone so Eloquent's wall-clock write to the DB lines up with
        // other models (like LiveSession.actual_start_at) that originate from
        // now()-style calls. Without this, launched_time round-trips 8h off
        // for Asia/Kuala_Lumpur and the LiveSessionMatcher window misses.
        $appTz = config('app.timezone');
        $start = isset($s['start_time']) ? Carbon::createFromTimestamp((int) $s['start_time'])->setTimezone($appTz) : null;
        $end = isset($s['end_time']) ? Carbon::createFromTimestamp((int) $s['end_time'])->setTimezone($appTz) : null;
        // Carbon 3 returns a signed float and the sign depends on call ordering;
        // abs+cast normalizes it to a non-negative integer.
        $duration = ($start && $end) ? (int) abs($end->diffInSeconds($start)) : null;

        $myr = fn (?array $money) => (is_array($money) && ($money['currency'] ?? null) === 'MYR')
            ? (float) ($money['amount'] ?? 0)
            : null;

        return [
            'creator_nickname' => $s['username'] ?? null,
            'creator_display_name' => $s['username'] ?? null,
            'launched_time' => $start,
            'duration_seconds' => $duration,
            'gmv_myr' => $myr($s['gmv'] ?? null),
            'live_attributed_gmv_myr' => $myr($s['24h_live_gmv'] ?? null),
            'avg_price_myr' => $myr($s['avg_price'] ?? null),
            'products_added' => $s['products_added'] ?? null,
            'products_sold' => $s['different_products_sold'] ?? null,
            'sku_orders' => $s['sku_orders'] ?? null,
            'items_sold' => $s['unit_sold'] ?? null,
            'unique_customers' => $s['customers'] ?? null,
            'click_to_order_rate' => $s['click_to_order_rate'] ?? null,
            'viewers' => $s['viewers'] ?? null,
            'views' => $s['views'] ?? null,
            'avg_view_duration_sec' => isset($s['avg_viewing_duration']) ? (int) $s['avg_viewing_duration'] : null,
            'comments' => $s['comments'] ?? null,
            'shares' => $s['shares'] ?? null,
            'likes' => $s['likes'] ?? null,
            'new_followers' => $s['new_followers'] ?? null,
            'product_impressions' => $s['product_impressions'] ?? null,
            'product_clicks' => $s['product_clicks'] ?? null,
            'ctr' => $s['click_through_rate'] ?? null,
            'raw_row_json' => $s,
        ];
    }

    /**
     * Get an authenticated client whose ->Analytics is an AnalyticsExtended
     * resource (so getShopLive* methods exist) pinned to API_VERSION.
     */
    protected function getClient(PlatformAccount $account): mixed
    {
        $app = $this->clientFactory->resolveApp($account, static::REQUIRED_CATEGORY);

        if ($this->authService->needsTokenRefresh($account, $app)) {
            Log::info('[TikTokLiveSync] Refreshing token before sync', [
                'account_id' => $account->id,
                'platform_app_id' => $app->id,
            ]);
            $this->authService->refreshToken($account, $app);
        }

        $client = $this->clientFactory->createClientForAccount($account, static::REQUIRED_CATEGORY);
        $client->useVersion(self::API_VERSION);

        // Reuse the SDK Client's already-configured Guzzle (signing + base URI)
        // so we don't have to replicate signing in our extended resource.
        // PHP 8.1+ makes setAccessible() a no-op for visibility; ReflectionMethod
        // can invoke a protected method directly.
        $analytics = new AnalyticsExtended;

        try {
            $reflection = new ReflectionMethod($client, 'httpClient');
            $httpClient = $reflection->invoke($client);
        } catch (\ReflectionException $e) {
            throw new \RuntimeException(
                'Cannot access SDK Client::httpClient(). The vendor package may have changed its internal API. '
                .'Update App\Services\TikTok\TikTokLiveSyncService::getClient() to match.',
                0,
                $e,
            );
        }

        $analytics->useHttpClient($httpClient);
        $analytics->useVersion(self::API_VERSION);

        return new class($analytics)
        {
            public function __construct(public object $Analytics) {}
        };
    }

    /**
     * Detects whether a TikTok ResponseException represents an "endpoint not
     * authorized for this shop" error vs a transient/recoverable one.
     *
     * Matched both by code (more reliable) and by message text (fallback for
     * codes TikTok may introduce later or vary by locale).
     */
    private function isNotAuthorized(\EcomPHP\TiktokShop\Errors\ResponseException $e): bool
    {
        // Known "shop not authorized for this feature" codes seen so far.
        // The token-related 105xxx codes are intentionally NOT here — those
        // get raised as TokenException by the SDK and trigger token refresh
        // upstream, not the silent-skip path.
        $notAuthorizedCodes = [12001, 12002, 13001, 13002];

        if (in_array((int) $e->getCode(), $notAuthorizedCodes, true)) {
            return true;
        }

        $message = $e->getMessage();
        foreach (['not_authorized', 'permission_denied', 'not authorized', 'permission denied', 'not enabled'] as $needle) {
            if (str_contains($message, $needle)) {
                return true;
            }
        }

        return false;
    }
}
