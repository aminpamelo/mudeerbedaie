<?php

namespace App\Http\Controllers\Fighter;

use App\Http\Controllers\Controller;
use App\Models\Funnel;
use App\Models\FunnelAnalytics;
use App\Models\FunnelOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class PerformanceController extends Controller
{
    /**
     * Rolling window of months shown in the report.
     */
    private const MONTHS = 12;

    /**
     * Monthly performance report across the fighter's own funnels, broken down
     * by the system metrics (visitors, page views, conversions, conversion
     * rate, orders, revenue). Optionally scoped to a single funnel via ?funnel.
     */
    public function index(Request $request): Response
    {
        $userId = (int) $request->user()->id;

        $funnels = Funnel::query()
            ->forUser($userId)
            ->orderBy('name')
            ->get(['id', 'uuid', 'name']);

        $selectedUuid = $request->query('funnel');
        $selected = $selectedUuid ? $funnels->firstWhere('uuid', $selectedUuid) : null;

        $scopeIds = $selected
            ? collect([$selected->id])
            : $funnels->pluck('id');

        $rows = $this->monthlyRows($scopeIds);

        return Inertia::render('Performance', [
            'funnels' => $funnels->map(fn ($f): array => [
                'uuid' => $f->uuid,
                'name' => $f->name,
            ])->values(),
            'selectedFunnel' => $selected?->uuid,
            'rows' => $rows,
            'totals' => $this->totals($rows),
        ]);
    }

    /**
     * Build one aggregated row per calendar month in the rolling window,
     * newest first. Grouping happens in PHP so the query stays portable across
     * SQLite (dev) and MySQL (prod) without date-format functions.
     *
     * @param  Collection<int, int>  $funnelIds
     * @return array<int, array<string, mixed>>
     */
    private function monthlyRows(Collection $funnelIds): array
    {
        $since = now()->startOfMonth()->subMonths(self::MONTHS - 1);

        // Seed every month in the window so gaps still render as zero rows.
        $months = [];
        for ($i = self::MONTHS - 1; $i >= 0; $i--) {
            $month = now()->startOfMonth()->subMonths($i);
            $months[$month->format('Y-m')] = [
                'key' => $month->format('Y-m'),
                'label' => $month->format('M Y'),
                'visitors' => 0,
                'pageviews' => 0,
                'conversions' => 0,
                'orders' => 0,
                'revenue' => 0.0,
            ];
        }

        if ($funnelIds->isNotEmpty()) {
            // Funnel-level analytics rows only (funnel_step_id null) to avoid
            // double-counting the per-step rows.
            FunnelAnalytics::query()
                ->whereIn('funnel_id', $funnelIds)
                ->whereNull('funnel_step_id')
                ->where('date', '>=', $since->toDateString())
                ->get(['date', 'unique_visitors', 'pageviews', 'conversions'])
                ->each(function (FunnelAnalytics $a) use (&$months): void {
                    $key = $a->date->format('Y-m');
                    if (! isset($months[$key])) {
                        return;
                    }
                    $months[$key]['visitors'] += (int) $a->unique_visitors;
                    $months[$key]['pageviews'] += (int) $a->pageviews;
                    $months[$key]['conversions'] += (int) $a->conversions;
                });

            // Revenue = every funnel-order row (incl. upsells/bumps); orders =
            // main purchases only, so upsells don't inflate the purchase count.
            FunnelOrder::query()
                ->whereIn('funnel_id', $funnelIds)
                ->where('created_at', '>=', $since)
                ->get(['created_at', 'funnel_revenue', 'order_type'])
                ->each(function (FunnelOrder $o) use (&$months): void {
                    $key = $o->created_at->format('Y-m');
                    if (! isset($months[$key])) {
                        return;
                    }
                    $months[$key]['revenue'] += (float) $o->funnel_revenue;
                    if ($o->order_type === 'main') {
                        $months[$key]['orders']++;
                    }
                });
        }

        return collect($months)
            ->map(function (array $row): array {
                $row['conversion_rate'] = $row['visitors'] > 0
                    ? round(($row['conversions'] / $row['visitors']) * 100, 2)
                    : 0.0;

                return $row;
            })
            ->sortByDesc('key')
            ->values()
            ->all();
    }

    /**
     * Window totals for the summary header.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    private function totals(array $rows): array
    {
        $visitors = collect($rows)->sum('visitors');
        $conversions = collect($rows)->sum('conversions');

        return [
            'visitors' => $visitors,
            'pageviews' => collect($rows)->sum('pageviews'),
            'conversions' => $conversions,
            'orders' => collect($rows)->sum('orders'),
            'revenue' => round(collect($rows)->sum('revenue'), 2),
            'conversion_rate' => $visitors > 0 ? round(($conversions / $visitors) * 100, 2) : 0.0,
        ];
    }
}
