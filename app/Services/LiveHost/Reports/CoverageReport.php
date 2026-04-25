<?php

namespace App\Services\LiveHost\Reports;

use App\Models\LiveScheduleAssignment;
use App\Models\SessionReplacementRequest;
use App\Services\LiveHost\Reports\Filters\ReportFilters;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class CoverageReport
{
    /**
     * Classify every dated, non-template slot in the filter window into one of
     * four mutually-exclusive buckets (precedence: unassigned → replaced →
     * missed → assigned), then aggregate KPIs, weekly trend, and per-account
     * rows. Two queries total: one for slots+linked-session-status, one for
     * the assigned replacement requests we use to short-circuit "replaced".
     */
    public function run(ReportFilters $filters): CoverageResult
    {
        $dateFromStr = $filters->dateFrom->toDateString();
        $dateToStr = $filters->dateTo->toDateString();

        $slots = LiveScheduleAssignment::query()
            ->where('live_schedule_assignments.is_template', false)
            ->whereNotNull('live_schedule_assignments.schedule_date')
            ->whereBetween('live_schedule_assignments.schedule_date', [$dateFromStr, $dateToStr])
            ->when(
                $filters->hostIds,
                fn ($q, $ids) => $q->whereIn('live_schedule_assignments.live_host_id', $ids)
            )
            ->when(
                $filters->platformAccountIds,
                fn ($q, $ids) => $q->whereIn('live_schedule_assignments.platform_account_id', $ids)
            )
            ->leftJoin(
                'live_sessions',
                'live_sessions.live_schedule_assignment_id',
                '=',
                'live_schedule_assignments.id'
            )
            ->leftJoin(
                'platform_accounts',
                'platform_accounts.id',
                '=',
                'live_schedule_assignments.platform_account_id'
            )
            ->orderBy('live_schedule_assignments.schedule_date')
            ->get([
                'live_schedule_assignments.id as slot_id',
                'live_schedule_assignments.schedule_date as schedule_date',
                'live_schedule_assignments.live_host_id as live_host_id',
                'live_schedule_assignments.platform_account_id as platform_account_id',
                'platform_accounts.name as account_name',
                'live_sessions.status as session_status',
            ]);

        $replacementIndex = $this->buildReplacementIndex($filters);

        $totalSlots = $slots->count();
        $assignedCount = 0;
        $unassignedCount = 0;
        $replacedCount = 0;
        $missedCount = 0;

        $weekly = [];
        $accounts = [];

        foreach ($slots as $slot) {
            $bucket = $this->classify($slot, $replacementIndex);

            switch ($bucket) {
                case 'unassigned':
                    $unassignedCount++;
                    break;
                case 'replaced':
                    $replacedCount++;
                    break;
                case 'missed':
                    $missedCount++;
                    break;
                case 'assigned':
                default:
                    $assignedCount++;
                    break;
            }

            $weekStart = CarbonImmutable::parse($slot->schedule_date)
                ->startOfWeek(CarbonInterface::MONDAY)
                ->toDateString();

            if (! isset($weekly[$weekStart])) {
                $weekly[$weekStart] = [
                    'weekStart' => $weekStart,
                    'assigned' => 0,
                    'unassigned' => 0,
                    'replaced' => 0,
                    'missed' => 0,
                ];
            }
            $weekly[$weekStart][$bucket]++;

            $accountId = (int) $slot->platform_account_id;
            if (! isset($accounts[$accountId])) {
                $accounts[$accountId] = [
                    'accountId' => $accountId,
                    'name' => (string) ($slot->account_name ?? ''),
                    'totalSlots' => 0,
                    'assigned' => 0,
                    'unassigned' => 0,
                    'replaced' => 0,
                    'missed' => 0,
                ];
            }
            $accounts[$accountId]['totalSlots']++;
            $accounts[$accountId][$bucket]++;
        }

        $percentFilled = $totalSlots > 0
            ? round(($assignedCount + $replacedCount) / $totalSlots, 4)
            : 0.0;
        $noShowRate = $totalSlots > 0
            ? round($missedCount / $totalSlots, 4)
            : 0.0;

        ksort($weekly);
        $weeklyTrend = array_values($weekly);

        $accountRows = array_map(function (array $row): array {
            $row['coverageRate'] = $row['totalSlots'] > 0
                ? round(($row['assigned'] + $row['replaced']) / $row['totalSlots'], 4)
                : 0.0;

            return $row;
        }, $accounts);

        usort($accountRows, fn (array $a, array $b) => strcmp($a['name'], $b['name']));

        return new CoverageResult(
            kpis: [
                'percentFilled' => $percentFilled,
                'unassignedCount' => $unassignedCount,
                'replacedCount' => $replacedCount,
                'noShowRate' => $noShowRate,
                'totalSlots' => $totalSlots,
            ],
            weeklyTrend: $weeklyTrend,
            accountRows: $accountRows,
        );
    }

    /**
     * @param  array<string, true>  $replacementIndex
     */
    private function classify(object $slot, array $replacementIndex): string
    {
        if ($slot->live_host_id === null) {
            return 'unassigned';
        }

        $key = $slot->slot_id.'|'.CarbonImmutable::parse($slot->schedule_date)->toDateString();
        if (isset($replacementIndex[$key])) {
            return 'replaced';
        }

        if ($slot->session_status === 'missed') {
            return 'missed';
        }

        return 'assigned';
    }

    /**
     * Pull every "assigned"-status replacement request whose target_date lands
     * inside the filter window, keyed by "{assignment_id}|{Y-m-d}" for O(1)
     * lookup during the in-PHP slot classifier pass.
     *
     * @return array<string, true>
     */
    private function buildReplacementIndex(ReportFilters $filters): array
    {
        $rows = SessionReplacementRequest::query()
            ->where('status', SessionReplacementRequest::STATUS_ASSIGNED)
            ->whereNotNull('target_date')
            ->whereBetween('target_date', [
                $filters->dateFrom->toDateString(),
                $filters->dateTo->toDateString(),
            ])
            ->get(['live_schedule_assignment_id', 'target_date']);

        $index = [];
        foreach ($rows as $row) {
            $date = CarbonImmutable::parse($row->target_date)->toDateString();
            $index[$row->live_schedule_assignment_id.'|'.$date] = true;
        }

        return $index;
    }
}
