<?php

namespace App\Services\Funnel\Monitor;

use App\Models\FacebookAdAccount;
use Carbon\CarbonInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Reads daily, ad-level insights for one Facebook ad account (token from its Business
 * Manager connection) from the Marketing API, following cursor pagination, and normalises
 * each row to the `facebook_ad_level_insights` columns. Ported from Dana Ummah.
 *
 * The access token is only ever sent as a query parameter to Meta; it is never included in
 * exception messages (which end up in the database and logs).
 *
 * @see https://developers.facebook.com/docs/marketing-api/insights
 */
class MetaAdLevelApi
{
    /**
     * Purchase action types in order of preference. Meta reports the same conversions under
     * several of these at once, so only the first one present is used — never their sum.
     */
    private const PURCHASE_ACTION_TYPES = ['omni_purchase', 'purchase', 'offsite_conversion.fb_pixel_purchase'];

    private const LANDING_PAGE_VIEW_ACTION_TYPE = 'landing_page_view';

    /** Safety stop for runaway pagination. */
    private const MAX_PAGES = 50;

    /**
     * @return list<array{date: string, campaign_id: string, campaign_name: string, objective: string|null, adset_id: string, adset_name: string, ad_id: string, ad_name: string, spend: float, impressions: int, reach: int, clicks: int, link_clicks: int, landing_page_views: int, video_views: int, thruplays: int, purchases: int, purchase_value: float}>
     *
     * @throws RuntimeException when the account is not configured or Meta rejects the request.
     */
    public function fetch(FacebookAdAccount $account, CarbonInterface $since, CarbonInterface $until): array
    {
        $this->ensureToken($account);

        $version = 'v21.0';
        $accountId = rawurlencode((string) $account->account_id);

        $url = "https://graph.facebook.com/{$version}/act_{$accountId}/insights";
        /** @var array<string, mixed>|null $query */
        $query = [
            'access_token' => $this->token($account),
            'level' => 'ad',
            'time_increment' => 1,
            'time_range' => json_encode(['since' => $since->toDateString(), 'until' => $until->toDateString()]),
            'fields' => 'campaign_id,campaign_name,objective,adset_id,adset_name,ad_id,ad_name,spend,impressions,reach,clicks,inline_link_clicks,actions,action_values,video_thruplay_watched_actions',
            'limit' => 500,
        ];

        $rows = [];

        for ($page = 0; $page < self::MAX_PAGES && $url !== null; $page++) {
            $response = $this->get($url, $query, $this->token($account));

            /** @var array<int, array<string, mixed>> $data */
            $data = (array) $response->json('data', []);
            foreach ($data as $row) {
                $rows[] = $this->normalise($row);
            }

            // `paging.next` is a fully-formed URL (token and cursor included), so it is
            // requested as-is — passing a query array would replace its query string.
            $next = $response->json('paging.next');
            $url = is_string($next) && $next !== '' ? $next : null;
            $query = null;
        }

        return $rows;
    }

    /**
     * Budgets currently running: daily budgets of active campaigns (CBO) plus daily budgets of
     * active ad sets in campaigns without their own budget (ABO). Meta returns budgets in the
     * account currency's minor unit (sen). Lifetime budgets are only counted, not converted.
     *
     * @return array{daily: float, lifetime: int, campaigns: array<string, float>}
     *
     * @throws RuntimeException
     */
    public function budgets(FacebookAdAccount $account): array
    {
        $this->ensureToken($account);

        $version = 'v21.0';
        $act = 'act_'.rawurlencode((string) $account->account_id);
        $token = $this->token($account);
        $active = json_encode(['ACTIVE']);
        $money = fn (mixed $v): float => is_numeric($v) ? round((float) $v / 100, 2) : 0.0;
        // Meta keeps campaigns/ad sets "ACTIVE" after their schedule ends; those spend nothing.
        $ended = fn (mixed $end): bool => is_string($end) && $end !== '' && strtotime($end) !== false && strtotime($end) <= time();

        $daily = [];
        $lifetime = 0;
        $withBudget = [];
        foreach ($this->all("https://graph.facebook.com/{$version}/{$act}/campaigns", ['access_token' => $token, 'fields' => 'id,daily_budget,lifetime_budget,stop_time', 'effective_status' => $active, 'limit' => 500], $token) as $c) {
            $id = (string) ($c['id'] ?? '');
            if ($ended($c['stop_time'] ?? null)) {
                $withBudget[$id] = true; // skip its ad sets too

                continue;
            }
            if ($money($c['daily_budget'] ?? null) > 0) {
                $daily[$id] = $money($c['daily_budget']);
                $withBudget[$id] = true;
            } elseif ($money($c['lifetime_budget'] ?? null) > 0) {
                $lifetime++;
                $withBudget[$id] = true;
            }
        }

        foreach ($this->all("https://graph.facebook.com/{$version}/{$act}/adsets", ['access_token' => $token, 'fields' => 'id,campaign_id,daily_budget,lifetime_budget,end_time', 'effective_status' => $active, 'limit' => 500], $token) as $a) {
            $campaign = (string) ($a['campaign_id'] ?? '');
            if (isset($withBudget[$campaign]) || $ended($a['end_time'] ?? null)) {
                continue;
            }
            if ($money($a['daily_budget'] ?? null) > 0) {
                $daily[$campaign] = ($daily[$campaign] ?? 0) + $money($a['daily_budget']);
            } elseif ($money($a['lifetime_budget'] ?? null) > 0) {
                $lifetime++;
            }
        }

        return ['daily' => round(array_sum($daily), 2), 'lifetime' => $lifetime, 'campaigns' => $daily];
    }

    /**
     * Current delivery status of each ad (cheap; refreshed on every sync so a pause in Ads
     * Manager shows within minutes). Ads Meta no longer returns are left out.
     *
     * @param  list<string>  $adIds
     * @return array<string, string>
     *
     * @throws RuntimeException
     */
    public function adStatuses(FacebookAdAccount $account, array $adIds): array
    {
        $this->ensureToken($account);

        $version = 'v21.0';
        $token = $this->token($account);
        $out = [];

        foreach (array_chunk(array_values(array_unique($adIds)), 50) as $chunk) {
            $response = $this->get("https://graph.facebook.com/{$version}/", [
                'access_token' => $token,
                'ids' => implode(',', $chunk),
                'fields' => 'effective_status,adset{end_time},campaign{stop_time}',
            ], $token);

            foreach ($chunk as $adId) {
                $ad = $response->json($adId);
                $status = is_array($ad) ? $this->deliveryStatus($ad) : null;
                if ($status !== null) {
                    $out[$adId] = $status;
                }
            }
        }

        return $out;
    }

    /**
     * Meta's effective status, except that an "ACTIVE" ad whose ad set or campaign schedule
     * has ended is reported as COMPLETED — Meta leaves those ACTIVE although they spend nothing.
     *
     * @param  array<string, mixed>  $ad
     */
    private function deliveryStatus(array $ad): ?string
    {
        $status = is_string($ad['effective_status'] ?? null) ? $ad['effective_status'] : null;
        $ends = [
            is_array($ad['adset'] ?? null) ? ($ad['adset']['end_time'] ?? null) : null,
            is_array($ad['campaign'] ?? null) ? ($ad['campaign']['stop_time'] ?? null) : null,
        ];
        foreach ($ends as $end) {
            if ($status === 'ACTIVE' && is_string($end) && $end !== '' && strtotime($end) !== false && strtotime($end) <= time()) {
                return 'COMPLETED';
            }
        }

        return $status !== null ? mb_substr($status, 0, 30) : null;
    }

    /**
     * Every row of a paged Graph API list.
     *
     * @param  array<string, mixed>  $query
     * @return list<array<string, mixed>>
     *
     * @throws RuntimeException
     */
    private function all(string $url, array $query, string $token): array
    {
        $rows = [];
        $next = $url;
        for ($page = 0; $page < self::MAX_PAGES && $next !== null; $page++) {
            $response = $this->get($next, $page === 0 ? $query : null, $token);
            foreach ((array) $response->json('data', []) as $row) {
                if (is_array($row)) {
                    $rows[] = $row;
                }
            }
            $link = $response->json('paging.next');
            $next = is_string($link) && $link !== '' ? $link : null;
        }

        return $rows;
    }

    /**
     * The creative behind each ad: format, copy, thumbnail URL, CTA, link to the post and the
     * underlying asset (video / image / post) so ads that reuse one asset can be grouped.
     * Ads Meta no longer returns (deleted) are simply absent from the result.
     *
     * @param  list<string>  $adIds
     * @return array<string, array{creative_id: string|null, asset_key: string|null, format: string, status: string|null, title: string|null, body: string|null, cta: string|null, permalink: string|null, image_url: string|null}>
     *
     * @throws RuntimeException
     */
    public function creatives(FacebookAdAccount $account, array $adIds): array
    {
        $this->ensureToken($account);

        $version = 'v21.0';
        $token = $this->token($account);
        $out = [];

        // Meta accepts up to 50 ids per multi-id request.
        foreach (array_chunk(array_values(array_unique($adIds)), 50) as $chunk) {
            $response = $this->get("https://graph.facebook.com/{$version}/", [
                'access_token' => $token,
                'ids' => implode(',', $chunk),
                'fields' => 'effective_status,adset{end_time},campaign{stop_time},creative{id,object_type,video_id,image_hash,image_url,thumbnail_url,title,body,call_to_action_type,effective_object_story_id,instagram_permalink_url,object_story_spec,asset_feed_spec}',
                'thumbnail_width' => 480,
                'thumbnail_height' => 480,
            ], $token);

            foreach ($chunk as $adId) {
                $ad = $response->json($adId);
                if (is_array($ad) && is_array($ad['creative'] ?? null)) {
                    $out[$adId] = $this->normaliseCreative($ad['creative'], $this->deliveryStatus($ad));
                }
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $c
     * @return array{creative_id: string|null, asset_key: string|null, format: string, status: string|null, title: string|null, body: string|null, cta: string|null, permalink: string|null, image_url: string|null}
     */
    private function normaliseCreative(array $c, ?string $status): array
    {
        $str = fn (mixed $v): ?string => is_scalar($v) && (string) $v !== '' ? (string) $v : null;
        $spec = is_array($c['object_story_spec'] ?? null) ? $c['object_story_spec'] : [];
        $video = is_array($spec['video_data'] ?? null) ? $spec['video_data'] : [];
        $link = is_array($spec['link_data'] ?? null) ? $spec['link_data'] : [];
        $feed = is_array($c['asset_feed_spec'] ?? null) ? $c['asset_feed_spec'] : [];
        $feedVideos = is_array($feed['videos'] ?? null) ? $feed['videos'] : [];
        $feedImages = is_array($feed['images'] ?? null) ? $feed['images'] : [];

        $videoId = $str($c['video_id'] ?? null) ?? $str($video['video_id'] ?? null)
            ?? $str(is_array($feedVideos[0] ?? null) ? ($feedVideos[0]['video_id'] ?? null) : null);
        $imageHash = $str($c['image_hash'] ?? null) ?? $str($link['image_hash'] ?? null)
            ?? $str(is_array($feedImages[0] ?? null) ? ($feedImages[0]['hash'] ?? null) : null);
        $story = $str($c['effective_object_story_id'] ?? null);

        $format = match (true) {
            is_array($link['child_attachments'] ?? null) && $link['child_attachments'] !== [] => 'carousel',
            count($feedVideos) + count($feedImages) > 1 => 'dynamic',
            $videoId !== null || strtoupper((string) ($c['object_type'] ?? '')) === 'VIDEO' => 'video',
            default => 'image',
        };

        $assetKey = match (true) {
            $format === 'carousel' && $story !== null => 'p:'.$story,
            $videoId !== null => 'v:'.$videoId,
            $imageHash !== null => 'i:'.$imageHash,
            $story !== null => 'p:'.$story,
            default => $str($c['id'] ?? null) !== null ? 'c:'.$c['id'] : null,
        };

        $title = $str($c['title'] ?? null) ?? $str($video['title'] ?? null) ?? $str($link['name'] ?? null);
        $body = $str($c['body'] ?? null) ?? $str($video['message'] ?? null) ?? $str($link['message'] ?? null);
        $cta = $str($c['call_to_action_type'] ?? null);

        return [
            'creative_id' => $str($c['id'] ?? null),
            'asset_key' => $assetKey !== null ? mb_substr($assetKey, 0, 120) : null,
            'format' => $format,
            'status' => $status,
            'title' => $title !== null ? mb_substr($title, 0, 255) : null,
            'body' => $body !== null ? mb_substr($body, 0, 5000) : null,
            'cta' => $cta !== null ? mb_substr($cta, 0, 40) : null,
            'permalink' => $str($c['instagram_permalink_url'] ?? null)
                ?? ($story !== null ? 'https://www.facebook.com/'.$story : null),
            // Images: the full picture. Videos: Meta's poster frame.
            'image_url' => $format === 'image'
                ? ($str($c['image_url'] ?? null) ?? $str($c['thumbnail_url'] ?? null))
                : ($str($c['thumbnail_url'] ?? null) ?? $str($c['image_url'] ?? null)),
        ];
    }

    private function token(FacebookAdAccount $account): string
    {
        return (string) $account->getRelationValue('connection')?->access_token;
    }

    /**
     * @throws RuntimeException
     */
    private function ensureToken(FacebookAdAccount $account): void
    {
        if ($this->token($account) === '') {
            throw new RuntimeException('Akaun iklan ini tiada token Business Manager.');
        }
    }

    /**
     * @param  array<string, mixed>|null  $query
     *
     * @throws RuntimeException
     */
    private function get(string $url, ?array $query, string $token): Response
    {
        try {
            // Ad-level daily insights over a month can take Meta well over 30s to compute.
            $request = Http::acceptJson()->connectTimeout(15)->timeout(120)
                ->retry(2, 2000, fn ($e) => $e instanceof ConnectionException, throw: false);
            $response = $query === null ? $request->get($url) : $request->get($url, $query);
        } catch (ConnectionException) {
            throw new RuntimeException('Tidak dapat menghubungi Meta Graph API.');
        }

        if ($response->failed()) {
            $message = $response->json('error.message');
            $message = is_string($message) && $message !== '' ? $message : 'ralat tidak diketahui';

            throw new RuntimeException(
                // Belt and braces: never let the token leak through an echoed error.
                'Meta Ads API (HTTP '.$response->status().'): '.str_replace($token, '[token]', $message),
            );
        }

        return $response;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{date: string, campaign_id: string, campaign_name: string, objective: string|null, adset_id: string, adset_name: string, ad_id: string, ad_name: string, spend: float, impressions: int, reach: int, clicks: int, link_clicks: int, landing_page_views: int, video_views: int, thruplays: int, purchases: int, purchase_value: float}
     */
    private function normalise(array $row): array
    {
        $actions = $this->actionMap($row['actions'] ?? null);
        $values = $this->actionMap($row['action_values'] ?? null);

        return [
            'date' => (string) ($row['date_start'] ?? ''),
            'campaign_id' => (string) ($row['campaign_id'] ?? ''),
            'campaign_name' => (string) ($row['campaign_name'] ?? ''),
            'objective' => CampaignLayer::normaliseObjective($row['objective'] ?? null),
            'adset_id' => (string) ($row['adset_id'] ?? ''),
            'adset_name' => (string) ($row['adset_name'] ?? ''),
            'ad_id' => (string) ($row['ad_id'] ?? ''),
            'ad_name' => (string) ($row['ad_name'] ?? ''),
            'spend' => round((float) ($row['spend'] ?? 0), 2),
            'impressions' => (int) ($row['impressions'] ?? 0),
            'reach' => (int) ($row['reach'] ?? 0),
            'clicks' => (int) ($row['clicks'] ?? 0),
            'link_clicks' => (int) ($row['inline_link_clicks'] ?? 0),
            'landing_page_views' => (int) ($actions[self::LANDING_PAGE_VIEW_ACTION_TYPE] ?? 0),
            // `video_view` is Meta's 3-second play: the hook.
            'video_views' => (int) ($actions['video_view'] ?? 0),
            'thruplays' => (int) array_sum($this->actionMap($row['video_thruplay_watched_actions'] ?? null)),
            'purchases' => (int) $this->firstPurchase($actions),
            'purchase_value' => round($this->firstPurchase($values), 2),
        ];
    }

    /**
     * Index Meta's `[{action_type, value}, …]` list by action type.
     *
     * @return array<string, float>
     */
    private function actionMap(mixed $list): array
    {
        $map = [];

        foreach (is_array($list) ? $list : [] as $item) {
            if (is_array($item) && isset($item['action_type']) && is_string($item['action_type'])) {
                $map[$item['action_type']] = (float) ($item['value'] ?? 0);
            }
        }

        return $map;
    }

    /** @param  array<string, float>  $map */
    private function firstPurchase(array $map): float
    {
        foreach (self::PURCHASE_ACTION_TYPES as $type) {
            if (array_key_exists($type, $map)) {
                return $map[$type];
            }
        }

        return 0.0;
    }
}
