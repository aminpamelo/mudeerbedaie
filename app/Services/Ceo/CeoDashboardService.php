<?php

namespace App\Services\Ceo;

use App\Models\ProductOrder;
use App\Services\Ceo\Reports\EcommerceHealthReport;
use App\Services\Ceo\Reports\EducationHealthReport;
use App\Services\Ceo\Reports\HrHealthReport;
use App\Services\Ceo\Reports\LiveHostHealthReport;
use Illuminate\Support\Facades\Cache;

/**
 * Orchestrates the CEO overview: runs each department's health report, then
 * composes the company-wide pulse strip and the cross-department "Needs
 * attention" feed from their results.
 *
 * Each report is cached briefly so the page — which touches four modules — stays
 * cheap under refreshes. The cache key is namespaced by period only; an
 * executive overview tolerates up to a minute of staleness.
 */
class CeoDashboardService
{
    private const CACHE_TTL = 60;

    public function __construct(
        private readonly EducationHealthReport $education,
        private readonly LiveHostHealthReport $liveHost,
        private readonly EcommerceHealthReport $ecommerce,
        private readonly HrHealthReport $hr,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(CeoPeriod $period): array
    {
        $departments = [
            'livehost' => $this->cached('livehost', $period, fn () => $this->liveHost->run($period)),
            'education' => $this->cached('education', $period, fn () => $this->education->run($period)),
            'ecommerce' => $this->cached('ecommerce', $period, fn () => $this->ecommerce->run($period)),
            'hr' => $this->cached('hr', $period, fn () => $this->hr->run($period)),
        ];

        return [
            'period' => [
                'key' => $period->key,
                'label' => $period->label(),
                'options' => $this->periodOptions(),
            ],
            'pulse' => $this->pulse($departments, $period),
            'departments' => array_values(array_map(fn (DepartmentHealth $d) => $d->toArray(), $departments)),
            'attention' => $this->attention($departments),
        ];
    }

    private function cached(string $key, CeoPeriod $period, \Closure $callback): DepartmentHealth
    {
        return Cache::remember("ceo:health:{$key}:{$period->key}", self::CACHE_TTL, $callback);
    }

    /**
     * Company-wide operational counters shown across the top of the dashboard.
     * Composed from the department reports' raw `extra` values so the strip adds
     * no extra queries beyond the revenue-delta lookup.
     *
     * @param  array<string, DepartmentHealth>  $d
     * @return array<int, array<string, mixed>>
     */
    private function pulse(array $d, CeoPeriod $period): array
    {
        $liveNow = (int) $d['livehost']->extra['liveNow'];
        $sessionsDone = (int) $d['education']->extra['sessionsCompletedToday'] + (int) $d['livehost']->extra['sessionsDoneToday'];
        $sessionsPlanned = (int) $d['education']->extra['sessionsScheduledToday'] + (int) $d['livehost']->extra['sessionsScheduledToday'];
        $attendance = (int) $d['hr']->extra['attendanceRateToday'];
        $headcount = (int) $d['hr']->extra['headcount'];
        $attentionCount = collect($d)->sum(fn (DepartmentHealth $h) => count($h->alerts));

        $revenue = (float) $d['ecommerce']->extra['revenuePeriod'];

        return [
            [
                'key' => 'liveNow',
                'label' => 'Live now',
                'value' => (string) $liveNow,
                'tone' => $liveNow > 0 ? 'positive' : 'muted',
                'live' => $liveNow > 0,
            ],
            [
                'key' => 'sessionsToday',
                'label' => 'Sessions today',
                'value' => $sessionsDone.' / '.$sessionsPlanned,
                'hint' => 'done / scheduled',
            ],
            [
                'key' => 'attendance',
                'label' => 'Staff attendance',
                'value' => $attendance > 0 ? $attendance.'%' : '—',
                'hint' => 'today',
                'tone' => $this->rateTone($attendance),
            ],
            [
                'key' => 'headcount',
                'label' => 'Active staff',
                'value' => (string) $headcount,
            ],
            [
                'key' => 'attention',
                'label' => 'Needs attention',
                'value' => (string) $attentionCount,
                'tone' => $attentionCount > 0 ? 'warning' : 'positive',
            ],
            [
                'key' => 'revenue',
                'label' => 'Revenue',
                'value' => 'RM '.number_format($revenue),
                'hint' => strtolower($period->label()),
                'delta' => $this->revenueDelta($period, $revenue),
            ],
        ];
    }

    /**
     * Paid e-commerce revenue this period versus the preceding window.
     *
     * @return array{direction: string, text: string}|null
     */
    private function revenueDelta(CeoPeriod $period, float $current): ?array
    {
        $prior = $period->priorPeriod();

        $priorRevenue = (float) ProductOrder::query()
            ->where('payment_status', 'paid')
            ->whereBetween('created_at', [$prior->from, $prior->to])
            ->sum('total_amount');

        if ($priorRevenue <= 0.0) {
            return null;
        }

        $change = ($current - $priorRevenue) / $priorRevenue * 100;
        $direction = match (true) {
            $change > 1 => 'up',
            $change < -1 => 'down',
            default => 'flat',
        };

        return [
            'direction' => $direction,
            'text' => sprintf('%+d%%', (int) round($change)),
        ];
    }

    /**
     * Flatten every department's alerts into one severity-sorted feed.
     *
     * @param  array<string, DepartmentHealth>  $departments
     * @return array<int, array<string, mixed>>
     */
    private function attention(array $departments): array
    {
        $order = ['critical' => 0, 'warning' => 1, 'info' => 2];

        $items = [];
        foreach ($departments as $health) {
            foreach ($health->alerts as $alert) {
                $items[] = [
                    'department' => $health->label,
                    'departmentKey' => $health->key,
                    'accent' => $health->accent,
                    'severity' => $alert['severity'],
                    'message' => $alert['message'],
                    'href' => $alert['href'] ?? $health->href,
                ];
            }
        }

        usort($items, fn ($a, $b) => ($order[$a['severity']] ?? 9) <=> ($order[$b['severity']] ?? 9));

        return $items;
    }

    private function rateTone(int $rate): string
    {
        return match (true) {
            $rate === 0 => 'muted',
            $rate >= 90 => 'positive',
            $rate >= 70 => 'warning',
            default => 'negative',
        };
    }

    /**
     * @return array<int, array{key: string, label: string}>
     */
    private function periodOptions(): array
    {
        return [
            ['key' => 'today', 'label' => 'Today'],
            ['key' => '7d', 'label' => 'Last 7 days'],
            ['key' => '30d', 'label' => 'Last 30 days'],
        ];
    }
}
