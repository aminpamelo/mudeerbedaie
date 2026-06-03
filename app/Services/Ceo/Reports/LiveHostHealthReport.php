<?php

namespace App\Services\Ceo\Reports;

use App\Models\LiveScheduleAssignment;
use App\Models\LiveSession;
use App\Models\SessionReplacementRequest;
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
            label: 'Live Host',
            accent: 'emerald',
            status: $status,
            href: '/livehost',
            metrics: [
                ['label' => 'Live now', 'value' => (string) $liveNow, 'tone' => $liveNow > 0 ? 'positive' : 'muted'],
                ['label' => 'Sessions today', 'value' => $sessionsDoneToday.' / '.$sessionsScheduledToday, 'hint' => 'done / scheduled'],
                ['label' => 'Roster coverage', 'value' => $coverage['percent'].'%', 'hint' => 'this week', 'tone' => $this->coverageTone($coverage['percent'])],
                ['label' => 'GMV', 'value' => 'RM '.number_format($gmv), 'hint' => strtolower($period->label())],
            ],
            trend: $this->dailyTrend($period),
            alerts: $alerts,
            extra: [
                'liveNow' => $liveNow,
                'sessionsDoneToday' => $sessionsDoneToday,
                'sessionsScheduledToday' => $sessionsScheduledToday,
                'gmvPeriod' => $gmv,
            ],
        );
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
                'message' => $uncoveredToday.' live '.($uncoveredToday === 1 ? 'slot' : 'slots').' uncovered today',
                'href' => '/livehost',
            ];
        }

        if ($pendingReplacements > 0) {
            $alerts[] = [
                'severity' => 'warning',
                'message' => $pendingReplacements.' replacement '.($pendingReplacements === 1 ? 'request' : 'requests').' pending',
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
