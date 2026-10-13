<?php

namespace App\Services\Funnel\Monitor;

use App\Models\FacebookAdAccount;
use App\Models\FacebookAdLevelInsight;
use App\Models\Funnel;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * "Monitor Iklan": every Facebook ad account's spend, real funnel sales, ROAS, funnel
 * conversion and total sales side by side, with alerts and an automatic diagnosis of the
 * main leak — so the team sees at a glance which account needs attention. Ported from Dana
 * Ummah's Monitor Klien (one client organisation there = one ad account here).
 *
 * Real sales come from funnel orders whose session carries Meta UTM tags (see AdSales).
 */
class AdMonitor
{
    public const CPM_OK = 60;

    public const CPM_WARNING = 100;

    public const CTR_OK = 1.0;

    public const CTR_WARNING = 0.7;

    public const LPV_RATE_OK = 70;

    public const LPV_RATE_WARNING = 50;

    public const CONVERSION_OK = 5;

    public const CONVERSION_WARNING = 2;

    public const PAYMENT_OK = 85;

    public const PAYMENT_WARNING = 70;

    public const ROAS_OK = 1.0;

    public const ROAS_WARNING = 0.6;

    public const AVERAGE_ORDER_MIN = 50;

    public const SUPPORT_SHARE_MAX = 30;

    public const MIN_IMPRESSIONS = 1000;

    public const MIN_LINK_CLICKS = 30;

    public const MIN_VISITORS = 30;

    public const MIN_CHECKOUTS = 5;

    public const MIN_SPEND = 50;

    public const MIN_SALES = 3;

    private const FUNNEL_ORDER = ['cpm', 'ctr', 'lpv_rate', 'conversion', 'payment', 'roas'];

    public function __construct(
        protected AdSales $sales,
        protected AdCreativePerformance $creatives,
    ) {}

    /**
     * @return array{clients: list<array<string, mixed>>, totals: array<string, mixed>}
     */
    public function report(CarbonInterface $from, CarbonInterface $to, CarbonInterface $previousFrom, CarbonInterface $previousTo): array
    {
        [$current, $campaigns] = $this->metrics($from, $to);
        [$previous] = $this->metrics($previousFrom, $previousTo);
        $funnels = $this->funnels($from, $to);
        $creatives = $this->creatives->report($from, $to);

        $accounts = FacebookAdAccount::query()->with('connection:id,name,status')->orderBy('name')->get();

        $clients = [];
        $needingAttention = 0;
        foreach ($accounts as $account) {
            $now = $current[$account->id] ?? $this->empty();
            $before = $previous[$account->id] ?? $this->empty();
            $accountFunnels = $funnels[$account->id] ?? [];
            $alerts = $this->alerts($account, $now, $accountFunnels);
            if (in_array('critical', array_column($alerts, 'level'), true)) {
                $needingAttention++;
            }
            $derived = $this->derive($now);

            $clients[] = [
                'id' => $account->id,
                'name' => $account->name,
                'account_id' => $account->account_id,
                'currency' => $account->currency ?: 'MYR',
                'connection' => $account->getRelationValue('connection')?->name,
                'active' => (string) $account->account_status === '1',
                'synced_at' => $account->monitor_synced_at?->toIso8601String(),
                'sync_failed' => filled($account->monitor_sync_error),
                'sync_error' => $account->monitor_sync_error,
                'daily_budget' => $account->daily_budget !== null ? (float) $account->daily_budget : null,
                'lifetime_budgets' => (int) $account->lifetime_budgets,
                'has_activity' => $now['spend'] > 0 || $now['raised_total'] > 0 || $now['unique_visitors'] > 0,
                ...$derived,
                'previous' => $this->previous($before),
                'campaigns' => $this->campaignRows($campaigns[$account->id] ?? [], (float) $now['spend'], is_array($account->campaign_budgets) ? $account->campaign_budgets : []),
                'creatives' => $creatives[$account->id] ?? ['items' => [], 'unmatched_count' => 0, 'unmatched_value' => 0.0, 'has_creative_data' => false, 'roas_basis' => 'meta'],
                'diagnosis' => $this->diagnose($derived),
                'funnels' => $accountFunnels,
                'alerts' => $alerts,
            ];
        }

        usort($clients, fn (array $a, array $b) => [$b['spend'], $b['raised_total']] <=> [$a['spend'], $a['raised_total']]);

        $keys = array_keys($this->empty());
        $sum = fn (string $key, array $set) => array_sum(array_map(fn (array $m) => $m[$key], $set));
        $totalsNow = array_combine($keys, array_map(fn ($k) => $sum($k, $current), $keys));
        $totalsBefore = array_combine($keys, array_map(fn ($k) => $sum($k, $previous), $keys));

        return [
            'clients' => $clients,
            'totals' => [
                ...$this->derive($totalsNow),
                'previous' => $this->previous($totalsBefore),
                'clients_needing_attention' => $needingAttention,
                'daily_budget' => round(array_sum(array_map(fn (array $c) => (float) ($c['daily_budget'] ?? 0), $clients)), 2),
                'period' => [
                    'from' => $from->toDateString(),
                    'to' => $to->toDateString(),
                    'previous_from' => $previousFrom->toDateString(),
                    'previous_to' => $previousTo->toDateString(),
                ],
            ],
        ];
    }

    /**
     * Raw counters per ad account for one window, plus each account's campaigns.
     *
     * @return array{0: array<int, array<string, float|int>>, 1: array<int, list<array<string, mixed>>>}
     */
    private function metrics(CarbonInterface $from, CarbonInterface $to): array
    {
        $out = [];
        $add = function (int $account, array $values) use (&$out): void {
            $out[$account] = array_merge($out[$account] ?? $this->empty(), $values);
        };

        $campaigns = [];
        FacebookAdLevelInsight::query()
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('facebook_ad_account_id, campaign_id, max(campaign_name) as campaign_name, max(objective) as objective, sum(spend) as spend, sum(impressions) as impressions, sum(clicks) as all_clicks, sum(link_clicks) as link_clicks, sum(landing_page_views) as lpv, sum(purchases) as purchases, sum(purchase_value) as purchase_value')
            ->groupBy('facebook_ad_account_id', 'campaign_id')->toBase()->get()
            ->each(function (object $r) use (&$campaigns): void {
                $campaigns[(int) $r->facebook_ad_account_id][] = [
                    'campaign_id' => (string) $r->campaign_id,
                    'name' => (string) $r->campaign_name,
                    'objective' => is_string($r->objective) ? $r->objective : null,
                    ...CampaignLayer::classify(is_string($r->objective) ? $r->objective : null, (string) $r->campaign_name),
                    'spend' => (float) $r->spend,
                    'impressions' => (int) $r->impressions,
                    'clicks' => (int) $r->all_clicks,
                    'link_clicks' => (int) $r->link_clicks,
                    'lpv' => (int) $r->lpv,
                    'meta_purchases' => (int) $r->purchases,
                    'meta_value' => (float) $r->purchase_value,
                ];
            });

        foreach ($campaigns as $account => $list) {
            $ads = array_fill_keys(['spend', 'impressions', 'clicks', 'link_clicks', 'lpv', 'meta_purchases', 'meta_value', 'sales_spend', 'sales_impressions', 'sales_link_clicks', 'sales_lpv', 'sales_meta_purchases', 'sales_meta_value', 'support_spend'], 0);
            foreach ($list as $c) {
                $isSales = $c['layer'] === CampaignLayer::SALES;
                foreach (['spend', 'impressions', 'clicks', 'link_clicks', 'lpv', 'meta_purchases', 'meta_value'] as $k) {
                    $ads[$k] += $c[$k];
                    if ($isSales && $k !== 'clicks') {
                        $ads['sales_'.$k] += $c[$k];
                    }
                }
                if (in_array($c['layer'], CampaignLayer::SUPPORT, true)) {
                    $ads['support_spend'] += $c['spend'];
                }
            }
            $add($account, $ads);
        }

        // Ad traffic: unique Meta visitors per account.
        $this->sales->sessions($from, $to)
            ->groupBy('account_id')
            ->each(fn (Collection $rows, $account) => $add((int) $account, [
                'views' => $rows->count(),
                'unique_visitors' => $rows->pluck('visitor_id')->unique()->count(),
            ]));

        // Ad orders: checkouts, sales and online payment success, per person.
        $orders = $this->sales->orders($from, $to);
        $orders->filter(fn ($o) => $o->account_id !== null)
            ->groupBy('account_id')
            ->each(function (Collection $rows, $account) use ($add): void {
                $sold = $rows->where('is_sale', true);
                $online = $rows->where('is_online', true);
                $add((int) $account, [
                    'actual_count' => $sold->count(),
                    'actual_value' => round((float) $sold->sum('funnel_revenue'), 2),
                    'checkouts' => $rows->count(),
                    'checkout_people' => $rows->pluck('person')->unique()->count(),
                    'completed_people' => $sold->pluck('person')->unique()->count(),
                    'online_people' => $online->pluck('person')->unique()->count(),
                    'online_paid_people' => $online->where('is_sale', true)->pluck('person')->unique()->count(),
                    'failed' => $rows->where('payment_status', 'failed')->count(),
                ]);
            });

        // Total sales (every source) of the funnels each account drives.
        $funnelAccount = $this->funnelAccounts($orders);
        $orders->where('is_sale', true)
            ->filter(fn ($o) => isset($funnelAccount[(int) $o->funnel_id]))
            ->groupBy(fn ($o) => $funnelAccount[(int) $o->funnel_id])
            ->each(fn (Collection $rows, $account) => $add((int) $account, [
                'raised_count' => $rows->count(),
                'raised_total' => round((float) $rows->sum('funnel_revenue'), 2),
            ]));

        return [$out, $campaigns];
    }

    /**
     * Funnel id → the ad account that drives it: its linked account, else the account whose
     * ads brought most of its Meta orders in the window.
     *
     * @param  Collection<int, object>  $orders
     * @return array<int, int>
     */
    private function funnelAccounts(Collection $orders): array
    {
        $map = $this->sales->linkedFunnels();

        $orders->filter(fn ($o) => $o->account_id !== null && ! isset($map[(int) $o->funnel_id]))
            ->groupBy('funnel_id')
            ->each(function (Collection $rows, $funnelId) use (&$map) {
                $map[(int) $funnelId] = (int) $rows->countBy('account_id')->sortDesc()->keys()->first();
            });

        return $map;
    }

    /**
     * Per-funnel snapshot of the ad traffic each account sent.
     *
     * @return array<int, list<array<string, mixed>>>
     */
    private function funnels(CarbonInterface $from, CarbonInterface $to): array
    {
        $visitors = [];
        $this->sales->sessions($from, $to)->each(function ($s) use (&$visitors) {
            $visitors[$s->account_id][$s->funnel_id][$s->visitor_id] = true;
        });

        $orders = [];
        $this->sales->orders($from, $to)->filter(fn ($o) => $o->account_id !== null)->each(function ($o) use (&$orders) {
            $orders[$o->account_id][$o->funnel_id][] = $o;
        });

        $linked = $this->sales->linkedFunnels();
        $funnelIds = collect($visitors)->flatMap(fn ($f) => array_keys($f))
            ->merge(collect($orders)->flatMap(fn ($f) => array_keys($f)))
            ->merge(array_keys($linked))
            ->unique()->values();

        $names = Funnel::query()->whereIn('id', $funnelIds)->get(['id', 'uuid', 'name', 'slug', 'status'])->keyBy('id');

        $out = [];
        $accounts = collect(array_keys($visitors))->merge(array_keys($orders))->merge(array_values($linked))->unique();
        foreach ($accounts as $account) {
            $ids = collect(array_keys($visitors[$account] ?? []))
                ->merge(array_keys($orders[$account] ?? []))
                ->merge(array_keys(array_filter($linked, fn ($a) => $a === (int) $account)))
                ->unique();

            foreach ($ids as $funnelId) {
                $funnel = $names[$funnelId] ?? null;
                if (! $funnel) {
                    continue;
                }
                $rows = collect($orders[$account][$funnelId] ?? []);
                $sold = $rows->where('is_sale', true);
                $unique = count($visitors[$account][$funnelId] ?? []);

                $out[(int) $account][] = [
                    'uuid' => $funnel->uuid,
                    'name' => $funnel->name,
                    'slug' => $funnel->slug,
                    'status' => $funnel->status,
                    'linked' => ($linked[$funnelId] ?? null) === (int) $account,
                    'unique_visitors' => $unique,
                    'checkout_people' => $rows->pluck('person')->unique()->count(),
                    'completed' => $sold->count(),
                    'raised' => round((float) $sold->sum('funnel_revenue'), 2),
                    'conversion_rate' => $this->rate($sold->pluck('person')->unique()->count(), $unique),
                ];
            }
        }

        foreach ($out as &$list) {
            usort($list, fn (array $a, array $b) => [$b['raised'], $b['unique_visitors']] <=> [$a['raised'], $a['unique_visitors']]);
        }

        return $out;
    }

    /**
     * @param  array<string, float|int>  $m
     * @return array<string, mixed>
     */
    private function derive(array $m): array
    {
        return [
            'spend' => round((float) $m['spend'], 2),
            'impressions' => (int) $m['impressions'],
            'link_clicks' => (int) $m['link_clicks'],
            'lpv' => (int) $m['lpv'],
            'cpm' => $this->ratio($m['spend'] * 1000, $m['impressions']),
            'ctr' => $this->ratio($m['link_clicks'] * 100, $m['impressions']),
            'ctr_all' => $this->ratio(($m['clicks'] ?? 0) * 100, $m['impressions']),
            'cost_per_click' => $this->ratio($m['spend'], $m['link_clicks']),
            'cost_per_lpv' => $this->ratio($m['spend'], $m['lpv']),
            'lpv_rate' => $this->rate((int) $m['lpv'], (int) $m['link_clicks']),
            'meta_purchases' => (int) $m['meta_purchases'],
            'meta_value' => round((float) $m['meta_value'], 2),
            'meta_roas' => $this->ratio($m['meta_value'], $m['spend']),
            'actual_count' => (int) $m['actual_count'],
            'actual_value' => round((float) $m['actual_value'], 2),
            'actual_roas' => $this->ratio($m['actual_value'], $m['spend']),
            'cost_per_sale' => $this->ratio($m['spend'], $m['actual_count']),
            'raised_count' => (int) $m['raised_count'],
            'raised_total' => round((float) $m['raised_total'], 2),
            'views' => (int) $m['views'],
            'unique_visitors' => (int) $m['unique_visitors'],
            'checkouts' => (int) $m['checkouts'],
            'checkout_people' => (int) $m['checkout_people'],
            'completed_people' => (int) $m['completed_people'],
            'conversion_rate' => $this->rate((int) $m['completed_people'], (int) $m['unique_visitors']),
            'lpv_conversion' => $this->rate((int) $m['actual_count'], (int) $m['lpv']),
            'online_people' => (int) $m['online_people'],
            'payment_success_rate' => $this->rate((int) $m['online_paid_people'], (int) $m['online_people']),
            'failed' => (int) $m['failed'],
            'sales' => $this->adMetrics($m, 'sales_'),
            'blended' => $this->adMetrics($m, ''),
            'has_sales' => (float) $m['sales_spend'] > 0,
            'support_spend' => round((float) $m['support_spend'], 2),
            'support_share' => $this->rate((int) round((float) $m['support_spend'] * 100), (int) round((float) $m['spend'] * 100)),
            'average_order' => $this->ratio($m['actual_value'], $m['actual_count']),
        ];
    }

    /**
     * @param  array<string, float|int>  $m
     * @return array<string, mixed>
     */
    private function adMetrics(array $m, string $prefix): array
    {
        $spend = round((float) $m[$prefix.'spend'], 2);
        $impressions = (int) $m[$prefix.'impressions'];
        $clicks = (int) $m[$prefix.'link_clicks'];
        $lpv = (int) $m[$prefix.'lpv'];
        $value = round((float) $m[$prefix.'meta_value'], 2);

        return [
            'spend' => $spend,
            'impressions' => $impressions,
            'link_clicks' => $clicks,
            'cpm' => $this->ratio($spend * 1000, $impressions),
            'ctr' => $this->ratio($clicks * 100, $impressions),
            'cost_per_click' => $this->ratio($spend, $clicks),
            'lpv' => $lpv,
            'cost_per_lpv' => $this->ratio($spend, $lpv),
            'lpv_rate' => $this->rate($lpv, $clicks),
            'meta_purchases' => (int) $m[$prefix.'meta_purchases'],
            'meta_value' => $value,
            'meta_roas' => $this->ratio($value, $spend),
        ];
    }

    /**
     * @param  array<string, float|int>  $m
     * @return array<string, float|int|null>
     */
    private function previous(array $m): array
    {
        $sales = $this->adMetrics($m, 'sales_');
        $blended = $this->adMetrics($m, '');

        return [
            'spend' => $m['spend'],
            'actual_value' => $m['actual_value'],
            'actual_roas' => $this->ratio($m['actual_value'], $m['spend']),
            'raised_total' => $m['raised_total'],
            'conversion_rate' => $this->rate((int) $m['completed_people'], (int) $m['unique_visitors']),
            'lpv_conversion' => $this->rate((int) $m['actual_count'], (int) $m['lpv']),
            'sales_cpm' => $sales['cpm'],
            'sales_ctr' => $sales['ctr'],
            'blended_cpm' => $blended['cpm'],
            'blended_ctr' => $blended['ctr'],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $campaigns
     * @param  array<string, mixed>  $budgets
     * @return list<array<string, mixed>>
     */
    private function campaignRows(array $campaigns, float $totalSpend, array $budgets): array
    {
        $rows = [];
        foreach ($campaigns as $c) {
            $spend = round((float) $c['spend'], 2);
            if ($spend <= 0 && (int) $c['impressions'] === 0) {
                continue;
            }
            $value = round((float) $c['meta_value'], 2);

            $rows[] = [
                'campaign_id' => $c['campaign_id'],
                'name' => $c['name'],
                'objective' => $c['objective'],
                'layer' => $c['layer'],
                'layer_label' => $c['label'],
                'role' => $c['role'],
                'spend' => $spend,
                'daily_budget' => is_numeric($budgets[$c['campaign_id']] ?? null) ? (float) $budgets[$c['campaign_id']] : null,
                'share_of_spend' => $this->rate((int) round($spend * 100), (int) round($totalSpend * 100)),
                'impressions' => (int) $c['impressions'],
                'cpm' => $this->ratio($spend * 1000, (int) $c['impressions']),
                'ctr' => $this->ratio((int) $c['link_clicks'] * 100, (int) $c['impressions']),
                'link_clicks' => (int) $c['link_clicks'],
                'lpv' => (int) $c['lpv'],
                'meta_purchases' => (int) $c['meta_purchases'],
                'meta_value' => $value,
                'meta_roas' => $this->ratio($value, $spend),
            ];
        }

        usort($rows, fn (array $a, array $b) => [$b['spend'], $b['impressions']] <=> [$a['spend'], $a['impressions']]);

        return $rows;
    }

    /**
     * The diagnosis thresholds, shown in the page's metric guide.
     *
     * @return list<array{key: string, label: string, ok: string, warning: string, critical: string, minimum: string}>
     */
    public static function rules(): array
    {
        return [
            ['key' => 'cpm', 'label' => 'CPM Sales', 'ok' => '≤ RM'.self::CPM_OK, 'warning' => '≤ RM'.self::CPM_WARNING, 'critical' => '> RM'.self::CPM_WARNING, 'minimum' => '≥ '.number_format(self::MIN_IMPRESSIONS).' impressions'],
            ['key' => 'ctr', 'label' => 'CTR link Sales', 'ok' => '≥ '.self::CTR_OK.'%', 'warning' => '≥ '.self::CTR_WARNING.'%', 'critical' => '< '.self::CTR_WARNING.'%', 'minimum' => '≥ '.number_format(self::MIN_IMPRESSIONS).' impressions'],
            ['key' => 'lpv_rate', 'label' => 'Kadar LPV (LPV ÷ klik)', 'ok' => '≥ '.self::LPV_RATE_OK.'%', 'warning' => '≥ '.self::LPV_RATE_WARNING.'%', 'critical' => '< '.self::LPV_RATE_WARNING.'%', 'minimum' => '≥ '.self::MIN_LINK_CLICKS.' klik'],
            ['key' => 'conversion', 'label' => 'Penukaran funnel', 'ok' => '≥ '.self::CONVERSION_OK.'%', 'warning' => '≥ '.self::CONVERSION_WARNING.'%', 'critical' => '< '.self::CONVERSION_WARNING.'%', 'minimum' => '≥ '.self::MIN_VISITORS.' pelawat'],
            ['key' => 'payment', 'label' => 'Bayaran online berjaya', 'ok' => '≥ '.self::PAYMENT_OK.'%', 'warning' => '≥ '.self::PAYMENT_WARNING.'%', 'critical' => '< '.self::PAYMENT_WARNING.'%', 'minimum' => '≥ '.self::MIN_CHECKOUTS.' checkout online'],
            ['key' => 'roas', 'label' => 'ROAS sebenar', 'ok' => '≥ '.self::ROAS_OK.'x', 'warning' => '≥ '.self::ROAS_WARNING.'x', 'critical' => '< '.self::ROAS_WARNING.'x', 'minimum' => '≥ RM'.self::MIN_SPEND.' belanja'],
            ['key' => 'average_order', 'label' => 'Purata jualan', 'ok' => '≥ RM'.self::AVERAGE_ORDER_MIN, 'warning' => '< RM'.self::AVERAGE_ORDER_MIN, 'critical' => '—', 'minimum' => '≥ '.self::MIN_SALES.' jualan'],
            ['key' => 'support_share', 'label' => 'Bajet bukan-Sales', 'ok' => '≤ '.self::SUPPORT_SHARE_MAX.'%', 'warning' => '> '.self::SUPPORT_SHARE_MAX.'%', 'critical' => '—', 'minimum' => '≥ RM'.self::MIN_SPEND.' belanja'],
        ];
    }

    /**
     * One finding per check in funnel order; the first that is not ok is the main leak.
     *
     * @param  array<string, mixed>  $d
     * @return array{basis: string, findings: list<array<string, mixed>>, bottleneck: array<string, mixed>|null}
     */
    private function diagnose(array $d): array
    {
        $isSales = (bool) $d['has_sales'];
        $ads = $isSales ? $d['sales'] : $d['blended'];
        $suffix = $isSales ? 'Sales' : '(gabungan)';
        $enoughDelivery = (int) $ads['impressions'] >= self::MIN_IMPRESSIONS;
        $spend = (float) $d['spend'];

        $findings = [
            $this->finding('cpm', "CPM {$suffix}", $ads['cpm'], 'rm', $enoughDelivery, $this->lowerIsBetter($ads['cpm'], self::CPM_OK, self::CPM_WARNING),
                'Sasaran ≤ RM'.self::CPM_OK, 'Kos paparan tinggi: luaskan audiens, kurangkan creative serentak, elak waktu persaingan tinggi.',
                'perlu ≥ '.number_format(self::MIN_IMPRESSIONS).' impressions'),
            $this->finding('ctr', "CTR link {$suffix}", $ads['ctr'], 'pct', $enoughDelivery, $this->higherIsBetter($ads['ctr'], self::CTR_OK, self::CTR_WARNING),
                'Sasaran ≥ '.self::CTR_OK.'%', 'Creative kurang ditekan: uji hook baharu / poster kurang teks / video pendek.',
                'perlu ≥ '.number_format(self::MIN_IMPRESSIONS).' impressions'),
            $this->finding('lpv_rate', 'Kadar LPV', $ads['lpv_rate'], 'pct', (int) $ads['link_clicks'] >= self::MIN_LINK_CLICKS, $this->higherIsBetter($ads['lpv_rate'], self::LPV_RATE_OK, self::LPV_RATE_WARNING),
                'Sasaran ≥ '.self::LPV_RATE_OK.'% klik sampai', 'Ramai klik tetapi LP lambat/gagal dimuat: semak kelajuan LP.',
                'perlu ≥ '.self::MIN_LINK_CLICKS.' klik'),
            $this->finding('conversion', 'Penukaran funnel', $d['conversion_rate'], 'pct', (int) $d['unique_visitors'] >= self::MIN_VISITORS, $this->higherIsBetter($d['conversion_rate'], self::CONVERSION_OK, self::CONVERSION_WARNING),
                'Sasaran ≥ '.self::CONVERSION_OK.'% pelawat membeli', 'LP/checkout kurang meyakinkan: semak hero, offer, harga, borang.',
                'perlu ≥ '.self::MIN_VISITORS.' pelawat'),
            $this->finding('payment', 'Bayaran online berjaya', $d['payment_success_rate'], 'pct', (int) $d['online_people'] >= self::MIN_CHECKOUTS, $this->higherIsBetter($d['payment_success_rate'], self::PAYMENT_OK, self::PAYMENT_WARNING),
                'Sasaran ≥ '.self::PAYMENT_OK.'% checkout online', 'Bayaran banyak gagal/tergantung: semak gateway, susuli order pending (WhatsApp/emel).',
                'perlu ≥ '.self::MIN_CHECKOUTS.' checkout online'),
            $this->finding('roas', 'ROAS sebenar', $d['actual_roas'], 'x', $spend >= self::MIN_SPEND, $this->higherIsBetter($d['actual_roas'], self::ROAS_OK, self::ROAS_WARNING),
                'Sasaran ≥ '.self::ROAS_OK.'x', 'Jualan belum menampung kos iklan.',
                'perlu ≥ RM'.self::MIN_SPEND.' belanja'),
            $this->finding('average_order', 'Purata jualan (iklan)', $d['average_order'], 'rm', (int) $d['actual_count'] >= self::MIN_SALES,
                $d['average_order'] !== null && $d['average_order'] < self::AVERAGE_ORDER_MIN ? 'warning' : 'ok',
                'Sasaran ≥ RM'.self::AVERAGE_ORDER_MIN, 'Nilai jualan rendah: tambah order bump / pakej / upsell.',
                'perlu ≥ '.self::MIN_SALES.' jualan'),
            $this->finding('support_share', 'Bajet bukan-Sales', $d['support_share'], 'pct', $spend >= self::MIN_SPEND,
                $d['support_share'] !== null && $d['support_share'] > self::SUPPORT_SHARE_MAX ? 'warning' : 'ok',
                'Sasaran ≤ '.self::SUPPORT_SHARE_MAX.'% bajet', 'Bajet bukan-Sales tinggi: pastikan ia menyokong (Post ID sama / isi kolam retarget).',
                'perlu ≥ RM'.self::MIN_SPEND.' belanja'),
        ];

        $bottleneck = null;
        foreach ($findings as $f) {
            if (in_array($f['key'], self::FUNNEL_ORDER, true) && in_array($f['level'], ['warning', 'critical'], true)) {
                $bottleneck = $f;
                break;
            }
        }

        return ['basis' => $isSales ? 'sales' : 'blended', 'findings' => $findings, 'bottleneck' => $bottleneck];
    }

    /**
     * @return array<string, mixed>
     */
    private function finding(string $key, string $label, mixed $value, string $format, bool $enough, string $level, string $target, string $action, string $minimum): array
    {
        $value = is_numeric($value) ? (float) $value : null;
        if (! $enough || $value === null) {
            return ['key' => $key, 'label' => $label, 'value' => $value, 'format' => $format, 'level' => 'insufficient', 'target' => $target, 'action' => "Data belum cukup ({$minimum})."];
        }

        return ['key' => $key, 'label' => $label, 'value' => $value, 'format' => $format, 'level' => $level, 'target' => $target, 'action' => $level === 'ok' ? null : $action];
    }

    private function lowerIsBetter(mixed $value, float $ok, float $warning): string
    {
        $v = (float) $value;

        return $v <= $ok ? 'ok' : ($v <= $warning ? 'warning' : 'critical');
    }

    private function higherIsBetter(mixed $value, float $ok, float $warning): string
    {
        $v = (float) $value;

        return $v >= $ok ? 'ok' : ($v >= $warning ? 'warning' : 'critical');
    }

    /**
     * @param  array<string, float|int>  $m
     * @param  list<array<string, mixed>>  $funnels
     * @return list<array{level: string, message: string}>
     */
    private function alerts(FacebookAdAccount $account, array $m, array $funnels): array
    {
        $alerts = [];
        $spend = (float) $m['spend'];
        $roas = $this->ratio($m['actual_value'], $spend);
        $success = $this->rate((int) $m['online_paid_people'], (int) $m['online_people']);

        if ((string) $account->account_status !== '1') {
            $alerts[] = ['level' => 'critical', 'message' => 'Akaun iklan tidak aktif di Meta (status '.($account->account_status ?? '?').').'];
        }
        if (filled($account->monitor_sync_error)) {
            $alerts[] = ['level' => 'critical', 'message' => 'Sync Meta gagal — data iklan tidak dikemas kini.'];
        }
        if ($spend >= self::MIN_SPEND && (int) $m['actual_count'] === 0) {
            $alerts[] = ['level' => 'critical', 'message' => 'Ada belanja iklan tetapi tiada jualan sebenar dari Meta.'];
        } elseif ($roas !== null && $roas < 1) {
            $alerts[] = ['level' => 'critical', 'message' => 'ROAS sebenar bawah 1x — jualan kurang daripada belanja.'];
        } elseif ($roas !== null && $roas < 2) {
            $alerts[] = ['level' => 'warning', 'message' => 'ROAS sebenar bawah 2x — pantau creative dan funnel.'];
        }
        if ($spend > 0 && (int) $m['lpv'] > 20 && (int) $m['unique_visitors'] === 0) {
            $alerts[] = ['level' => 'warning', 'message' => 'Iklan ada LPV tetapi funnel tiada pelawat dari iklan ini — semak URL parameter (utm_source=fb&utm_campaign={{campaign.id}}&utm_term={{adset.id}}&utm_content={{ad.id}}).'];
        }
        if ((int) $m['online_people'] >= self::MIN_CHECKOUTS && $success !== null && $success < 50) {
            $alerts[] = ['level' => 'warning', 'message' => "Hanya {$success}% checkout online berjaya bayar — susuli order gagal/pending."];
        }
        if ($funnels === []) {
            $alerts[] = ['level' => 'info', 'message' => 'Tiada funnel dikaitkan dengan akaun ini — pautkan di tab Tracking funnel (Ads Source).'];
        }

        return $alerts;
    }

    /** @return array<string, float|int> */
    private function empty(): array
    {
        return [
            'spend' => 0.0, 'impressions' => 0, 'clicks' => 0, 'link_clicks' => 0, 'lpv' => 0,
            'meta_purchases' => 0, 'meta_value' => 0.0,
            'actual_count' => 0, 'actual_value' => 0.0,
            'raised_count' => 0, 'raised_total' => 0.0,
            'views' => 0, 'unique_visitors' => 0,
            'checkouts' => 0, 'failed' => 0,
            'checkout_people' => 0, 'completed_people' => 0,
            'online_people' => 0, 'online_paid_people' => 0,
            'sales_spend' => 0.0, 'sales_impressions' => 0, 'sales_link_clicks' => 0, 'sales_lpv' => 0,
            'sales_meta_purchases' => 0, 'sales_meta_value' => 0.0, 'support_spend' => 0.0,
        ];
    }

    private function ratio(float|int $a, float|int $b): ?float
    {
        return $b > 0 ? round($a / $b, 2) : null;
    }

    private function rate(int $part, int $whole): ?float
    {
        return $whole > 0 ? min(100.0, round($part / $whole * 100, 1)) : null;
    }
}
