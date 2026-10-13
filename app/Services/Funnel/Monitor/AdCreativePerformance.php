<?php

namespace App\Services\Funnel\Monitor;

use App\Models\FacebookAdCreative;
use App\Models\FacebookAdLevelInsight;
use Carbon\CarbonInterface;

/**
 * Creative scoreboard for the Monitor Iklan: ads grouped by the asset they show (one video
 * or image reused across campaigns is one creative), with hook/hold for video, CTR, real
 * funnel sales matched back to the ad, where it runs (cold / retarget / awareness), fatigue
 * and a plain verdict with the next thing to fix. Ported from Dana Ummah.
 *
 * Sales and visits are matched to an ad by utm_content = ad id (Studio ads and the
 * recommended URL parameters tag it), then utm_content = ad name or its angle id,
 * narrowed by utm_term (ad set id); ties go to the ad that spent most.
 */
class AdCreativePerformance
{
    public const HOOK_OK = 30;

    public const HOOK_WARNING = 20;

    public const HOLD_OK = 30;

    public const HOLD_WARNING = 15;

    public const WINNER_ROAS = 2.0;

    public const KILL_SPEND = 100;

    public const FATIGUE_DROP = 30;

    private const MIN_VIDEO_VIEWS = 300;

    public function __construct(protected AdSales $sales) {}

    /**
     * @return array<int, array{items: list<array<string, mixed>>, unmatched_count: int, unmatched_value: float, has_creative_data: bool, roas_basis: string}>
     */
    public function report(CarbonInterface $from, CarbonInterface $to): array
    {
        $ads = [];
        FacebookAdLevelInsight::query()
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('facebook_ad_account_id, ad_id, max(ad_name) as ad_name, max(campaign_name) as campaign_name, max(objective) as objective, max(adset_id) as adset_id, max(adset_name) as adset_name, sum(spend) as spend, sum(impressions) as impressions, sum(link_clicks) as link_clicks, sum(landing_page_views) as lpv, sum(video_views) as video_views, sum(thruplays) as thruplays, sum(purchases) as purchases, sum(purchase_value) as purchase_value')
            ->groupBy('facebook_ad_account_id', 'ad_id')->toBase()->get()
            ->each(function (object $r) use (&$ads): void {
                $objective = is_string($r->objective) ? $r->objective : null;
                $ads[(int) $r->facebook_ad_account_id][(string) $r->ad_id] = [
                    'ad_id' => (string) $r->ad_id,
                    'ad_name' => (string) $r->ad_name,
                    'campaign_name' => (string) $r->campaign_name,
                    'adset_id' => (string) $r->adset_id,
                    'adset_name' => (string) $r->adset_name,
                    ...CampaignLayer::classify($objective, (string) $r->campaign_name),
                    'spend' => (float) $r->spend,
                    'impressions' => (int) $r->impressions,
                    'link_clicks' => (int) $r->link_clicks,
                    'lpv' => (int) $r->lpv,
                    'video_views' => (int) $r->video_views,
                    'thruplays' => (int) $r->thruplays,
                    'meta_purchases' => (int) $r->purchases,
                    'meta_value' => (float) $r->purchase_value,
                ];
            });

        if ($ads === []) {
            return [];
        }

        // matched[account][ad] = [sales, value, unique visitors]
        $matched = [];
        $unmatched = [];
        $this->sales->orders($from, $to)
            ->filter(fn ($o) => $o->account_id !== null && $o->is_sale)
            ->each(function ($o) use ($ads, &$matched, &$unmatched): void {
                $account = (int) $o->account_id;
                $ad = $this->match($ads[$account] ?? [], $o->utm_content, $o->utm_term);
                if ($ad === null) {
                    $unmatched[$account] = [($unmatched[$account][0] ?? 0) + 1, ($unmatched[$account][1] ?? 0.0) + (float) $o->funnel_revenue];

                    return;
                }
                $m = $matched[$account][$ad] ?? [0, 0.0, []];
                $matched[$account][$ad] = [$m[0] + 1, $m[1] + (float) $o->funnel_revenue, $m[2]];
            });

        $this->sales->sessions($from, $to)
            ->filter(fn ($s) => filled($s->utm_content))
            ->each(function ($s) use ($ads, &$matched): void {
                $account = (int) $s->account_id;
                $ad = $this->match($ads[$account] ?? [], $s->utm_content, $s->utm_term);
                if ($ad !== null) {
                    $m = $matched[$account][$ad] ?? [0, 0.0, []];
                    $m[2][$s->visitor_id] = true;
                    $matched[$account][$ad] = $m;
                }
            });

        // CTR of the last 3 days against the 7 days before them, per ad, for fatigue.
        $trend = [];
        $recentFrom = $to->copy()->subDays(2)->toDateString();
        FacebookAdLevelInsight::query()
            ->whereBetween('date', [$to->copy()->subDays(9)->toDateString(), $to->toDateString()])
            ->toBase()
            ->get(['facebook_ad_account_id', 'ad_id', 'date', 'impressions', 'link_clicks'])
            ->each(function ($r) use (&$trend, $recentFrom): void {
                $slot = substr((string) $r->date, 0, 10) >= $recentFrom ? 'recent' : 'before';
                $key = $r->facebook_ad_account_id.'|'.$r->ad_id;
                $trend[$key][$slot.'_impressions'] = ($trend[$key][$slot.'_impressions'] ?? 0) + (int) $r->impressions;
                $trend[$key][$slot.'_clicks'] = ($trend[$key][$slot.'_clicks'] ?? 0) + (int) $r->link_clicks;
            });

        $creatives = FacebookAdCreative::query()
            ->whereIn('ad_id', array_merge(...array_map(fn (array $a) => array_map('strval', array_keys($a)), array_values($ads))))
            ->get()
            ->keyBy('ad_id');

        $out = [];
        foreach ($ads as $account => $list) {
            $total = array_sum(array_column($list, 'spend'));
            $groups = [];
            foreach ($list as $adId => $ad) {
                $creative = $creatives[(string) $adId] ?? null;
                $key = $creative->asset_key ?? 'ad:'.$adId;
                [$count, $value, $visitors] = $matched[$account][$adId] ?? [0, 0.0, []];
                $ad += ['actual_count' => $count, 'actual_value' => $value, 'visitors' => count($visitors)];
                $groups[$key][] = ['ad' => $ad, 'creative' => $creative, 'trend' => $trend[$account.'|'.$adId] ?? []];
            }

            // Judge on real sales only when most of what Meta claims really came through
            // the funnels; an account selling elsewhere is judged on Meta's purchases.
            $actualTotal = array_sum(array_map(fn (array $m) => $m[1], $matched[$account] ?? [])) + (float) ($unmatched[$account][1] ?? 0);
            $metaTotal = array_sum(array_column($list, 'meta_value'));
            $useActual = $actualTotal > 0 && $actualTotal >= 0.5 * $metaTotal;

            $items = [];
            foreach ($groups as $key => $members) {
                $item = $this->creative((string) $key, $members, $total, $useActual);
                if ($item !== null) {
                    $items[] = $item;
                }
            }
            usort($items, fn (array $a, array $b) => [$b['spend'], $b['impressions']] <=> [$a['spend'], $a['impressions']]);

            $out[$account] = [
                'items' => $items,
                'unmatched_count' => (int) ($unmatched[$account][0] ?? 0),
                'unmatched_value' => round((float) ($unmatched[$account][1] ?? 0), 2),
                'roas_basis' => $useActual ? 'actual' : 'meta',
                'has_creative_data' => count(array_filter(array_keys($groups), fn ($k) => ! str_starts_with((string) $k, 'ad:'))) > 0,
            ];
        }

        return $out;
    }

    /**
     * @param  array<string, array<string, mixed>>  $ads
     */
    private function match(array $ads, mixed $utmContent, mixed $utmTerm): ?string
    {
        $content = is_scalar($utmContent) ? trim((string) $utmContent) : '';
        $term = is_scalar($utmTerm) ? trim((string) $utmTerm) : '';

        if ($content === '') {
            return null;
        }
        if (isset($ads[$content])) {
            return $content;
        }

        $byName = array_filter($ads, fn (array $a) => mb_strtolower(trim($a['ad_name'])) === mb_strtolower($content));
        if ($byName === []) {
            $key = CampaignLayer::creativeKey($content);
            $byName = $key === '' ? [] : array_filter($ads, fn (array $a) => CampaignLayer::creativeKey($a['ad_name']) === $key);
        }
        if ($term !== '') {
            $inAdset = array_filter($byName, fn (array $a) => $a['adset_id'] === $term);
            $byName = $inAdset !== [] ? $inAdset : $byName;
        }
        if ($byName === []) {
            return null;
        }
        uasort($byName, fn (array $a, array $b) => $b['spend'] <=> $a['spend']);

        return (string) array_key_first($byName);
    }

    /**
     * @param  list<array{ad: array<string, mixed>, creative: FacebookAdCreative|null, trend: array<string, int>}>  $members
     * @return array<string, mixed>|null
     */
    private function creative(string $key, array $members, float $totalSpend, bool $useActual): ?array
    {
        usort($members, fn (array $a, array $b) => $b['ad']['spend'] <=> $a['ad']['spend']);
        $sum = fn (string $k) => array_sum(array_map(fn (array $m) => $m['ad'][$k], $members));
        $sumTrend = fn (string $k) => array_sum(array_map(fn (array $m) => $m['trend'][$k] ?? 0, $members));

        $spend = round((float) $sum('spend'), 2);
        $impressions = (int) $sum('impressions');
        if ($spend <= 0 && $impressions === 0) {
            return null;
        }

        $lead = $members[0];
        $info = $lead['creative'] ?? collect($members)->pluck('creative')->filter()->first();
        $format = $info?->format;
        $isVideo = in_array($format, ['video', 'dynamic'], true) || ($sum('video_views') > 0 && $format === null);

        $clicks = (int) $sum('link_clicks');
        $lpv = (int) $sum('lpv');
        $videoViews = (int) $sum('video_views');
        $thruplays = (int) $sum('thruplays');
        $visitors = (int) $sum('visitors');
        $actualCount = (int) $sum('actual_count');
        $actualValue = round((float) $sum('actual_value'), 2);
        $metaPurchases = (int) $sum('meta_purchases');
        $metaValue = round((float) $sum('meta_value'), 2);

        $ctr = $this->ratio($clicks * 100, $impressions);
        $cpm = $this->ratio($spend * 1000, $impressions);
        $hook = $isVideo ? $this->ratio($videoViews * 100, $impressions) : null;
        $hold = $isVideo ? $this->ratio($thruplays * 100, $videoViews) : null;
        $lpvRate = $this->ratio($lpv * 100, $clicks);
        $conversion = $this->ratio($actualCount * 100, $visitors);
        $actualRoas = $this->ratio($actualValue, $spend);
        $metaRoas = $this->ratio($metaValue, $spend);
        $results = $useActual ? $actualCount : $metaPurchases;
        $roas = $useActual ? $actualRoas : $metaRoas;

        $enough = $impressions >= AdMonitor::MIN_IMPRESSIONS;
        $levels = [
            'cpm' => $enough ? $this->lower($cpm, AdMonitor::CPM_OK, AdMonitor::CPM_WARNING) : null,
            'ctr' => $enough ? $this->higher($ctr, AdMonitor::CTR_OK, AdMonitor::CTR_WARNING) : null,
            'hook' => $isVideo && $enough ? $this->higher($hook, self::HOOK_OK, self::HOOK_WARNING) : null,
            'hold' => $isVideo && $videoViews >= self::MIN_VIDEO_VIEWS ? $this->higher($hold, self::HOLD_OK, self::HOLD_WARNING) : null,
            'lpv_rate' => $clicks >= AdMonitor::MIN_LINK_CLICKS ? $this->higher($lpvRate, AdMonitor::LPV_RATE_OK, AdMonitor::LPV_RATE_WARNING) : null,
            'conversion' => $visitors >= AdMonitor::MIN_VISITORS ? $this->higher($conversion, AdMonitor::CONVERSION_OK, AdMonitor::CONVERSION_WARNING) : null,
            'roas' => $spend >= AdMonitor::MIN_SPEND ? $this->higher($roas ?? 0.0, AdMonitor::ROAS_OK, AdMonitor::ROAS_WARNING) : null,
        ];

        $recentImp = $sumTrend('recent_impressions');
        $beforeImp = $sumTrend('before_impressions');
        $recentCtr = $this->ratio($sumTrend('recent_clicks') * 100, $recentImp);
        $beforeCtr = $this->ratio($sumTrend('before_clicks') * 100, $beforeImp);
        $fatigue = null;
        if ($recentImp >= AdMonitor::MIN_IMPRESSIONS && $beforeImp >= 2 * AdMonitor::MIN_IMPRESSIONS
            && $recentCtr !== null && $beforeCtr !== null && $beforeCtr > 0
            && $recentCtr < $beforeCtr * (1 - self::FATIGUE_DROP / 100)) {
            $fatigue = ['recent_ctr' => $recentCtr, 'before_ctr' => $beforeCtr, 'drop' => round((1 - $recentCtr / $beforeCtr) * 100, 1)];
        }

        $verdict = match (true) {
            $spend < AdMonitor::MIN_SPEND || ! $enough => ['key' => 'learning', 'label' => 'Belum cukup data'],
            $roas !== null && $roas >= self::WINNER_ROAS && $results >= 2 => ['key' => 'winner', 'label' => 'Pemenang — skala'],
            $roas !== null && $roas >= AdMonitor::ROAS_OK => ['key' => 'profitable', 'label' => 'Untung — kekalkan'],
            $results === 0 && $spend >= self::KILL_SPEND => ['key' => 'kill', 'label' => 'Pertimbang henti'],
            $roas === null || $roas < AdMonitor::ROAS_WARNING => ['key' => 'weak', 'label' => 'Lemah'],
            default => ['key' => 'watch', 'label' => 'Pantau'],
        };

        $actions = [
            'hook' => 'Hook lemah: 3 saat pertama tak menahan scroll. Tukar pembukaan (wajah, soalan, angka besar).',
            'hold' => 'Penonton lari selepas hook: pendekkan, masuk terus ke cerita dan offer.',
            'ctr' => 'Ramai tengok tapi tak klik: kuatkan CTA dan sebab untuk beli sekarang.',
            'lpv_rate' => 'Klik tak sampai ke LP: semak link dan kelajuan LP.',
            'conversion' => 'Pelawat dari creative ini jarang membeli: mesej creative tak selari dengan LP/offer.',
            'roas' => 'Jualan belum menampung kos creative ini.',
        ];
        $issue = null;
        foreach ($actions as $k => $text) {
            if (in_array($levels[$k], ['warning', 'critical'], true)) {
                $issue = ['key' => $k, 'level' => $levels[$k], 'action' => $text];
                break;
            }
        }

        $activeAds = count(array_filter($members, fn (array $m) => $m['creative']?->status === 'ACTIVE'));

        $placements = array_map(function (array $m) use ($useActual): array {
            $a = $m['ad'];
            $results = $useActual ? $a['actual_count'] : $a['meta_purchases'];
            $value = $useActual ? $a['actual_value'] : $a['meta_value'];

            return [
                'ad_id' => $a['ad_id'],
                'ad_name' => $a['ad_name'],
                'status' => $m['creative']?->status,
                'campaign' => $a['campaign_name'],
                'adset' => $a['adset_name'],
                'layer' => $a['layer'],
                'role' => $a['role'] ?? $a['label'],
                'spend' => round((float) $a['spend'], 2),
                'ctr' => $this->ratio($a['link_clicks'] * 100, $a['impressions']),
                'results' => (int) $results,
                'value' => round((float) $value, 2),
                'roas' => $this->ratio((float) $value, (float) $a['spend']),
            ];
        }, $members);

        $layers = [];
        foreach ($placements as $p) {
            $layers[$p['role']] = ($layers[$p['role']] ?? 0) + $p['spend'];
        }
        arsort($layers);

        return [
            'key' => $key,
            'name' => $lead['ad']['ad_name'],
            'angle' => CampaignLayer::creativeKey($lead['ad']['ad_name']) ?: null,
            'format' => $format ?? ($videoViews > 0 ? 'video' : null),
            'status' => $activeAds > 0 ? 'ACTIVE' : $info?->status,
            'active_ads' => $activeAds,
            'thumbnail_url' => collect($members)->map(fn (array $m) => $m['creative']?->thumbnail_url)->filter()->first(),
            'permalink' => $info?->permalink,
            'title' => $info?->title,
            'body' => $info?->body !== null ? mb_substr($info->body, 0, 400) : null,
            'ads' => count($members),
            'layers' => array_map(fn ($role, $s) => ['role' => (string) $role, 'spend' => round((float) $s, 2)], array_keys($layers), $layers),
            'placements' => $placements,
            'spend' => $spend,
            'share_of_spend' => $this->ratio($spend * 100, $totalSpend),
            'impressions' => $impressions,
            'cpm' => $cpm,
            'video_views' => $videoViews,
            'hook_rate' => $hook,
            'thruplays' => $thruplays,
            'hold_rate' => $hold,
            'link_clicks' => $clicks,
            'ctr' => $ctr,
            'lpv' => $lpv,
            'cost_per_lpv' => $this->ratio($spend, $lpv),
            'visitors' => $visitors,
            'conversion' => $conversion,
            'actual_count' => $actualCount,
            'actual_value' => $actualValue,
            'actual_roas' => $actualRoas,
            'meta_purchases' => $metaPurchases,
            'meta_value' => $metaValue,
            'meta_roas' => $metaRoas,
            'roas' => $roas,
            'roas_source' => $useActual ? 'actual' : 'meta',
            'results' => $results,
            'cost_per_result' => $this->ratio($spend, $results),
            'levels' => $levels,
            'fatigue' => $fatigue,
            'verdict' => $verdict,
            'issue' => $issue,
        ];
    }

    private function lower(?float $v, float $ok, float $warning): ?string
    {
        return $v === null ? null : ($v <= $ok ? 'ok' : ($v <= $warning ? 'warning' : 'critical'));
    }

    private function higher(?float $v, float $ok, float $warning): ?string
    {
        return $v === null ? null : ($v >= $ok ? 'ok' : ($v >= $warning ? 'warning' : 'critical'));
    }

    private function ratio(float|int $a, float|int $b): ?float
    {
        return (float) $b == 0.0 ? null : round($a / $b, 2);
    }
}
