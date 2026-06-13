<?php

namespace App\Services\Ceo\Reports;

use App\Models\Task;
use App\Services\Ceo\CeoPeriod;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Builds the CEO "Task Monitoring" page: how staff are performing on the tasks
 * assigned to them. Workload + overdue figures are live snapshots; throughput
 * (completed / on-time) is scoped to the selected period by completion date.
 *
 * Tasks are assigned to employees (tasks.assigned_to -> employees.id) and are
 * currently created as meeting action items, but this report covers every Task
 * regardless of source.
 */
class TaskMonitoringReport
{
    private const OPEN_STATUSES = ['pending', 'in_progress'];

    private const STATUS_TONE = [
        'pending' => 'info',
        'in_progress' => 'warning',
        'completed' => 'positive',
        'cancelled' => 'muted',
    ];

    private const PRIORITY_TONE = [
        'urgent' => 'negative',
        'high' => 'warning',
        'medium' => 'info',
        'low' => 'muted',
    ];

    /**
     * @return array<string, mixed>
     */
    public function build(CeoPeriod $period): array
    {
        $today = CarbonImmutable::now()->startOfDay();
        $todayDate = $today->toDateString();
        $fromDate = $period->from->toDateString();
        $toDate = $period->to->toDateString();

        $open = Task::query()->whereIn('status', self::OPEN_STATUSES)->count();
        $overdue = Task::query()->whereIn('status', self::OPEN_STATUSES)->whereDate('deadline', '<', $todayDate)->count();
        $dueSoon = Task::query()->whereIn('status', self::OPEN_STATUSES)
            ->whereBetween('deadline', [$todayDate, $today->addDays(7)->toDateString()])->count();

        $completedPeriod = Task::query()->where('status', 'completed')
            ->whereBetween('completed_at', [$period->from, $period->to])->count();
        $onTimePeriod = Task::query()->where('status', 'completed')
            ->whereBetween('completed_at', [$period->from, $period->to])
            ->whereRaw('date(completed_at) <= deadline')->count();
        $onTimeRate = $completedPeriod > 0 ? (int) round($onTimePeriod / $completedPeriod * 100) : null;

        $dueInPeriod = Task::query()->where('status', '!=', 'cancelled')
            ->whereBetween('deadline', [$fromDate, $toDate])->count();
        $completedOfDue = Task::query()->where('status', 'completed')
            ->whereBetween('deadline', [$fromDate, $toDate])->count();
        $completionRate = $dueInPeriod > 0 ? (int) round($completedOfDue / $dueInPeriod * 100) : null;

        $periodLabel = mb_strtolower($period->label());
        $status = $this->status($onTimeRate, $completionRate, $overdue);

        return [
            'status' => $status,
            'moduleHref' => '/hr/meetings',
            'moduleLabel' => __('ceo.tasks.open_module'),
            'gauge' => [
                'label' => __('ceo.tasks.on_time'),
                'value' => $onTimeRate ?? 0,
                'target' => 85,
                'suffix' => '%',
                'tone' => $this->rateTone($onTimeRate),
            ],
            'kpis' => [
                ['label' => __('ceo.tasks.open'), 'value' => (string) $open, 'tone' => $open > 0 ? 'info' : 'muted'],
                ['label' => __('ceo.tasks.overdue'), 'value' => (string) $overdue, 'tone' => $overdue > 0 ? 'negative' : 'muted'],
                ['label' => __('ceo.tasks.due_soon'), 'value' => (string) $dueSoon, 'tone' => $dueSoon > 0 ? 'warning' : 'muted'],
                ['label' => __('ceo.tasks.completed'), 'value' => (string) $completedPeriod, 'hint' => $periodLabel, 'tone' => 'positive'],
                ['label' => __('ceo.tasks.completion_rate'), 'value' => $completionRate === null ? '—' : $completionRate.'%', 'hint' => $periodLabel],
                ['label' => __('ceo.tasks.on_time_rate'), 'value' => $onTimeRate === null ? '—' : $onTimeRate.'%', 'hint' => $periodLabel, 'tone' => $this->rateTone($onTimeRate)],
            ],
            'breakdowns' => [
                [
                    'title' => __('ceo.tasks.by_status'),
                    'subtitle' => $periodLabel,
                    'segments' => $this->statusSegments($fromDate, $toDate),
                ],
                [
                    'title' => __('ceo.tasks.by_priority'),
                    'segments' => $this->prioritySegments(),
                ],
            ],
            'trend' => [
                'title' => __('ceo.tasks.completed_per_day'),
                'subtitle' => $periodLabel,
                'data' => $this->completedTrend($period),
            ],
            'staff' => [
                'title' => __('ceo.tasks.staff_performance'),
                'columns' => [
                    ['key' => 'name', 'label' => __('ceo.tasks.col_staff')],
                    ['key' => 'open', 'label' => __('ceo.tasks.col_open'), 'align' => 'right'],
                    ['key' => 'overdue', 'label' => __('ceo.tasks.col_overdue'), 'align' => 'right'],
                    ['key' => 'completed', 'label' => __('ceo.tasks.col_completed'), 'align' => 'right'],
                    ['key' => 'onTime', 'label' => __('ceo.tasks.col_ontime'), 'align' => 'right'],
                ],
                'rows' => $this->staffLeaderboard($todayDate, $period),
            ],
            'overdueList' => [
                'title' => __('ceo.tasks.overdue_tasks'),
                'columns' => [
                    ['key' => 'task', 'label' => __('ceo.tasks.col_task')],
                    ['key' => 'staff', 'label' => __('ceo.tasks.col_staff')],
                    ['key' => 'priority', 'label' => __('ceo.tasks.col_priority')],
                    ['key' => 'late', 'label' => __('ceo.tasks.col_days_late'), 'align' => 'right'],
                ],
                'rows' => $this->overdueTasks($today),
            ],
            'alerts' => $this->alerts($overdue, $dueSoon, $todayDate),
        ];
    }

    /**
     * @return array<int, array{label: string, value: int, tone: string}>
     */
    private function statusSegments(string $fromDate, string $toDate): array
    {
        $counts = Task::query()
            ->whereBetween('deadline', [$fromDate, $toDate])
            ->selectRaw('status, COUNT(*) as c')
            ->groupBy('status')
            ->pluck('c', 'status');

        return collect(['pending', 'in_progress', 'completed', 'cancelled'])
            ->map(fn (string $s) => [
                'label' => __('ceo.tasks.status_'.$s),
                'value' => (int) ($counts[$s] ?? 0),
                'tone' => self::STATUS_TONE[$s],
            ])
            ->all();
    }

    /**
     * @return array<int, array{label: string, value: int, tone: string}>
     */
    private function prioritySegments(): array
    {
        $counts = Task::query()
            ->whereIn('status', self::OPEN_STATUSES)
            ->selectRaw('priority, COUNT(*) as c')
            ->groupBy('priority')
            ->pluck('c', 'priority');

        return collect(['urgent', 'high', 'medium', 'low'])
            ->map(fn (string $p) => [
                'label' => __('ceo.tasks.priority_'.$p),
                'value' => (int) ($counts[$p] ?? 0),
                'tone' => self::PRIORITY_TONE[$p],
            ])
            ->all();
    }

    /**
     * @return array<int, int>
     */
    private function completedTrend(CeoPeriod $period): array
    {
        $rows = Task::query()
            ->where('status', 'completed')
            ->whereBetween('completed_at', [$period->from, $period->to])
            ->selectRaw('DATE(completed_at) as day, COUNT(*) as c')
            ->groupBy('day')
            ->pluck('c', 'day');

        return TrendFiller::daily($period, $rows);
    }

    /**
     * Per-staff task performance, laggards first (most overdue, then most open).
     *
     * @return array<int, array<string, mixed>>
     */
    private function staffLeaderboard(string $todayDate, CeoPeriod $period): array
    {
        $rows = Task::query()
            ->join('task_assignee', 'task_assignee.task_id', '=', 'tasks.id')
            ->join('employees', 'employees.id', '=', 'task_assignee.employee_id')
            ->whereNull('employees.deleted_at')
            ->selectRaw(
                "employees.id as eid, employees.full_name as name,
                SUM(CASE WHEN tasks.status IN ('pending','in_progress') THEN 1 ELSE 0 END) as open_count,
                SUM(CASE WHEN tasks.status IN ('pending','in_progress') AND tasks.deadline < ? THEN 1 ELSE 0 END) as overdue_count,
                SUM(CASE WHEN tasks.status = 'completed' AND tasks.completed_at BETWEEN ? AND ? THEN 1 ELSE 0 END) as completed_count,
                SUM(CASE WHEN tasks.status = 'completed' AND tasks.completed_at BETWEEN ? AND ? AND date(tasks.completed_at) <= tasks.deadline THEN 1 ELSE 0 END) as ontime_count",
                [$todayDate, $period->from, $period->to, $period->from, $period->to]
            )
            ->groupBy('employees.id', 'employees.full_name')
            ->havingRaw('open_count > 0 OR overdue_count > 0 OR completed_count > 0')
            ->orderByRaw('overdue_count DESC, open_count DESC, completed_count DESC')
            ->limit(12)
            ->get();

        return $rows->map(function ($r) {
            $completed = (int) $r->completed_count;
            $onTime = $completed > 0 ? (int) round((int) $r->ontime_count / $completed * 100) : null;

            return [
                'name' => (string) $r->name,
                'open' => (int) $r->open_count,
                'overdue' => (int) $r->overdue_count,
                'completed' => $completed,
                'onTime' => $onTime,
            ];
        })->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function overdueTasks(CarbonImmutable $today): array
    {
        return Task::query()
            ->with(['assignees:id,full_name'])
            ->whereIn('status', self::OPEN_STATUSES)
            ->whereDate('deadline', '<', $today->toDateString())
            ->orderBy('deadline')
            ->limit(8)
            ->get(['id', 'title', 'assigned_to', 'deadline', 'priority'])
            ->map(function (Task $t) use ($today) {
                $daysLate = $t->deadline ? (int) $t->deadline->startOfDay()->diffInDays($today) : 0;

                return [
                    'task' => (string) $t->title,
                    'staff' => $t->assignees->pluck('full_name')->filter()->implode(', ') ?: '—',
                    'priority' => __('ceo.tasks.priority_'.$t->priority),
                    'late' => trans_choice('ceo.tasks.days_late', $daysLate, ['count' => $daysLate]),
                ];
            })
            ->all();
    }

    private function status(?int $onTimeRate, ?int $completionRate, int $overdue): string
    {
        if (($onTimeRate !== null && $onTimeRate < 60) || $overdue >= 10) {
            return 'red';
        }

        if ($overdue > 0 || ($onTimeRate !== null && $onTimeRate < 85) || ($completionRate !== null && $completionRate < 70)) {
            return 'amber';
        }

        return 'green';
    }

    private function rateTone(?int $rate): string
    {
        return match (true) {
            $rate === null => 'muted',
            $rate >= 85 => 'positive',
            $rate >= 60 => 'warning',
            default => 'negative',
        };
    }

    /**
     * @return array<int, array{severity: string, message: string, href?: string}>
     */
    private function alerts(int $overdue, int $dueSoon, string $todayDate): array
    {
        $alerts = [];

        if ($overdue > 0) {
            $alerts[] = [
                'severity' => $overdue >= 10 ? 'critical' : 'warning',
                'message' => trans_choice('ceo.alerts.tasks_overdue', $overdue, ['count' => $overdue]),
                'href' => '/hr/meetings',
            ];

            $staffOverdue = DB::table('task_assignee')
                ->join('tasks', 'tasks.id', '=', 'task_assignee.task_id')
                ->whereNull('tasks.deleted_at')
                ->whereIn('tasks.status', self::OPEN_STATUSES)
                ->whereDate('tasks.deadline', '<', $todayDate)
                ->distinct('task_assignee.employee_id')
                ->count('task_assignee.employee_id');

            if ($staffOverdue > 0) {
                $alerts[] = [
                    'severity' => 'warning',
                    'message' => trans_choice('ceo.alerts.staff_overdue', $staffOverdue, ['count' => $staffOverdue]),
                    'href' => '/hr/meetings',
                ];
            }
        }

        if ($dueSoon > 0) {
            $alerts[] = [
                'severity' => 'info',
                'message' => trans_choice('ceo.alerts.tasks_due_soon', $dueSoon, ['count' => $dueSoon]),
                'href' => '/hr/meetings',
            ];
        }

        return $alerts;
    }
}
