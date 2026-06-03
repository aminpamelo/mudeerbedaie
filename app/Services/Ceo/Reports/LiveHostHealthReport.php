<?php

namespace App\Services\Ceo\Reports;

use App\Models\LiveScheduleAssignment;
use App\Models\LiveSession;
use App\Models\SessionReplacementRequest;
use App\Models\User;
use App\Services\Ceo\CeoPeriod;
use App\Services\Ceo\DepartmentHealth;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Operational health of the Live Host operation: are sessions going live as
 * scheduled, is the roster covered, and is anyone waiting on a replacement.
 * GMV is included only as secondary financial context.
 */
class LiveHostHealthReport
{
    private const ENDED_STATUSES = ['ended', 'completed'];

    public function run(CeoPeriod $period): DepartmentHealth
    {
        $today = CarbonImmutable::now()->startOfDay();

        $liveNow = LiveSession::query()->where('status', 'live')->count();

        $sessionsScheduledToday = LiveSession::query()
            ->whereDate('scheduled_start_at', $today)
            ->count();
        $sessionsDoneToday = LiveSession::query()
            ->whereDate('scheduled_start_at', $today)
            ->whereIn('status', self::ENDED_STATUSES)
            ->count();

        $coverage = $this->weeklyCoverage();
        $pendingReplacements = SessionReplacementRequest::query()
            ->where('status', SessionReplacementRequest::STATUS_PENDING)
            ->count();

        $gmv = (float) LiveSession::query()
            ->whereBetween('scheduled_start_at', [$period->from, $period->to])
            ->whereIn('status', self::ENDED_STATUSES)
            ->sum(DB::raw('gmv_amount + COALESCE(gmv_adjustment, 0)'));

        $status = $this->status($coverage['percent'], $coverage['uncoveredToday'], $pendingReplacements);
        $alerts = $this->alerts($coverage['uncoveredToday'], $pendingReplacements);

        return new DepartmentHealth(
            key: 'livehost',
            label: __('ceo.departments.livehost'),
            accent: 'emerald',
            status: $status,
            href: '/livehost',
            metrics: [
                ['label' => __('ceo.metrics.live_now'), 'value' => (string) $liveNow, 'tone' => $liveNow > 0 ? 'positive' : 'muted'],
                ['label' => __('ceo.metrics.sessions_today'), 'value' => $sessionsDoneToday.' / '.$sessionsScheduledToday, 'hint' => __('ceo.hints.done_scheduled')],
                ['label' => __('ceo.metrics.roster_coverage'), 'value' => $coverage['percent'].'%', 'hint' => __('ceo.hints.this_week'), 'tone' => $this->coverageTone($coverage['percent'])],
                ['label' => __('ceo.metrics.gmv'), 'value' => 'RM '.number_format($gmv), 'hint' => mb_strtolower($period->label())],
            ],
            trend: $this->dailyTrend($period),
            alerts: $alerts,
            extra: [
                'liveNow' => $liveNow,
                'sessionsDoneToday' => $sessionsDoneToday,
                'sessionsScheduledToday' => $sessionsScheduledToday,
                'gmvPeriod' => $gmv,
            ],
            gauges: [
                ['label' => __('ceo.metrics.roster_coverage'), 'value' => $coverage['percent'], 'target' => 90, 'suffix' => '%', 'tone' => $this->coverageTone($coverage['percent'])],
            ],
            bars: [
                ['label' => __('ceo.metrics.sessions_today'), 'value' => $sessionsDoneToday, 'max' => max($sessionsScheduledToday, 1), 'valueLabel' => $sessionsDoneToday.' / '.$sessionsScheduledToday, 'tone' => 'positive'],
            ],
        );
    }

    /**
     * Rich drill-in payload for the dedicated Live Host detail page. Reuses
     * run() for the shared status/gauges/trend/alerts and adds the deeper
     * breakdowns and lists the overview card has no room for.
     *
     * @return array<string, mixed>
     */
    public function detail(CeoPeriod $period): array
    {
        $health = $this->run($period);
        $coverage = $this->weeklyCoverage();

        $activeHosts = User::query()->where('role', 'live_host')->where('status', 'active')->count();
        $pendingReplacements = SessionReplacementRequest::query()
            ->where('status', SessionReplacementRequest::STATUS_PENDING)
            ->count();

        $statusCounts = LiveSession::query()
            ->whereBetween('scheduled_start_at', [$period->from, $period->to])
            ->selectRaw('status, COUNT(*) as c')
            ->groupBy('status')
            ->pluck('c', 'status');

        $topHosts = LiveSession::query()
            ->join('users', 'users.id', '=', 'live_sessions.live_host_id')
            ->whereBetween('live_sessions.scheduled_start_at', [$period->from, $period->to])
            ->whereIn('live_sessions.status', self::ENDED_STATUSES)
            ->groupBy('live_sessions.live_host_id', 'users.name')
            ->selectRaw('users.name as name, COUNT(*) as sessions, COALESCE(SUM(live_sessions.gmv_amount + COALESCE(live_sessions.gmv_adjustment, 0)), 0) as gmv')
            ->orderByDesc('sessions')
            ->limit(6)
            ->get();

        return [
            'key' => $health->key,
            'label' => $health->label,
            'accent' => $health->accent,
            'status' => $health->status,
            'moduleHref' => '/livehost',
            'moduleLabel' => __('ceo.modules.livehost'),
            'gauges' => $health->gauges,
            'alerts' => $health->alerts,
            'kpis' => [
                ['label' => __('ceo.metrics.live_now'), 'value' => (string) $health->extra['liveNow'], 'tone' => $health->extra['liveNow'] > 0 ? 'positive' : 'muted'],
                ['label' => __('ceo.metrics.sessions_today'), 'value' => $health->extra['sessionsDoneToday'].' / '.$health->extra['sessionsScheduledToday'], 'hint' => __('ceo.hints.done_scheduled')],
                ['label' => __('ceo.metrics.roster_coverage'), 'value' => $coverage['percent'].'%', 'hint' => __('ceo.hints.this_week')],
                ['label' => __('ceo.metrics.gmv'), 'value' => 'RM '.number_format((float) $health->extra['gmvPeriod']), 'hint' => mb_strtolower($period->label())],
                ['label' => __('ceo.metrics.replacements'), 'value' => (string) $pendingReplacements, 'hint' => __('ceo.hints.pending'), 'tone' => $pendingReplacements > 0 ? 'warning' : 'muted'],
                ['label' => __('ceo.metrics.active_hosts'), 'value' => (string) $activeHosts],
            ],
            'sections' => [
                [
                    'type' => 'chart',
                    'title' => __('ceo.sections.completed_sessions'),
                    'subtitle' => mb_strtolower($period->label()),
                    'data' => $health->trend,
                ],
                [
                    'type' => 'breakdown',
                    'title' => __('ceo.sections.sessions_by_status'),
                    'segments' => [
                        ['label' => __('ceo.segments.scheduled'), 'value' => (int) ($statusCounts['scheduled'] ?? 0), 'tone' => 'info'],
                        ['label' => __('ceo.segments.live'), 'value' => (int) ($statusCounts['live'] ?? 0), 'tone' => 'positive'],
                        ['label' => __('ceo.segments.ended'), 'value' => (int) ($statusCounts['ended'] ?? 0) + (int) ($statusCounts['completed'] ?? 0), 'tone' => 'muted'],
                    ],
                ],
                [
                    'type' => 'list',
                    'title' => __('ceo.sections.top_hosts'),
                    'subtitle' => __('ceo.subtitles.by_completed_sessions'),
                    'columns' => [
                        ['key' => 'name', 'label' => __('ceo.columns.host')],
                        ['key' => 'sessions', 'label' => __('ceo.columns.sessions'), 'align' => 'right'],
                        ['key' => 'gmv', 'label' => __('ceo.columns.gmv'), 'align' => 'right'],
                    ],
                    'rows' => $topHosts->map(fn ($r) => [
                        'name' => (string) $r->name,
                        'sessions' => (int) $r->sessions,
                        'gmv' => 'RM '.number_format((float) $r->gmv),
                    ])->all(),
                ],
            ],
        ];
    }

    /**
     * Coverage of this week's concrete (non-template) roster slots: how many
     * have a host assigned, and how many of today's slots are still empty.
     *
     * @return array{percent: int, uncoveredToday: int}
     */
    private function weeklyCoverage(): array
    {
        $weekStart = CarbonImmutable::now()->startOfWeek(CarbonImmutable::SUNDAY);
        $weekEnd = $weekStart->endOfWeek(CarbonImmutable::SATURDAY);

        $slots = LiveScheduleAssignment::query()
            ->where('is_template', false)
            ->whereBetween('schedule_date', [$weekStart->toDateString(), $weekEnd->toDateString()])
            ->get(['schedule_date', 'live_host_id']);

        $total = $slots->count();
        $assigned = $slots->whereNotNull('live_host_id')->count();

        $todayString = CarbonImmutable::now()->toDateString();
        $uncoveredToday = $slots
            ->filter(fn ($s) => $s->schedule_date?->toDateString() === $todayString && $s->live_host_id === null)
            ->count();

        return [
            'percent' => $total === 0 ? 100 : (int) round($assigned / $total * 100),
            'uncoveredToday' => $uncoveredToday,
        ];
    }

    private function status(int $coverage, int $uncoveredToday, int $pendingReplacements): string
    {
        if ($coverage < 70 || ($uncoveredToday > 0 && $pendingReplacements > 0)) {
            return DepartmentHealth::RED;
        }

        if ($coverage < 85 || $pendingReplacements > 0 || $uncoveredToday > 0) {
            return DepartmentHealth::AMBER;
        }

        return DepartmentHealth::GREEN;
    }

    private function coverageTone(int $percent): string
    {
        return match (true) {
            $percent >= 85 => 'positive',
            $percent >= 70 => 'warning',
            default => 'negative',
        };
    }

    /**
     * @return array<int, array{severity: string, message: string, href?: string}>
     */
    private function alerts(int $uncoveredToday, int $pendingReplacements): array
    {
        $alerts = [];

        if ($uncoveredToday > 0) {
            $alerts[] = [
                'severity' => 'critical',
                'message' => trans_choice('ceo.alerts.uncovered_slots', $uncoveredToday, ['count' => $uncoveredToday]),
                'href' => '/livehost',
            ];
        }

        if ($pendingReplacements > 0) {
            $alerts[] = [
                'severity' => 'warning',
                'message' => trans_choice('ceo.alerts.replacements_pending', $pendingReplacements, ['count' => $pendingReplacements]),
                'href' => '/livehost/replacements',
            ];
        }

        return $alerts;
    }

    /**
     * Daily count of completed sessions across the period, for the sparkline.
     *
     * @return array<int, int>
     */
    private function dailyTrend(CeoPeriod $period): array
    {
        $rows = LiveSession::query()
            ->whereBetween('scheduled_start_at', [$period->from, $period->to])
            ->whereIn('status', self::ENDED_STATUSES)
            ->selectRaw('DATE(scheduled_start_at) as day, COUNT(*) as c')
            ->groupBy('day')
            ->pluck('c', 'day');

        return TrendFiller::daily($period, $rows);
    }
}
