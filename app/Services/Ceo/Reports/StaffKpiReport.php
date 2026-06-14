<?php

namespace App\Services\Ceo\Reports;

use App\Models\Employee;
use App\Models\Meeting;
use App\Models\Task;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Builds the "Staff KPI" matrix: one row per active employee × Jan–Dec columns
 * for a year. Each cell shows tasks completed that month and their on-time rate
 * ("3 · 100%"); best/worst months are tinted by completed count. Each row also
 * carries the staff member's tasks for the row drill-down (title + status).
 *
 * A task counts toward every assignee it is shared with (task_assignee), so a
 * co-owned task credits each owner.
 */
class StaffKpiReport
{
    private const OPEN_STATUSES = ['pending', 'in_progress'];

    private const STATUS_TONE = [
        'pending' => 'info',
        'in_progress' => 'warning',
        'completed' => 'positive',
        'cancelled' => 'muted',
    ];

    /** Cap drill-down tasks shown per staff row. */
    private const TASKS_PER_STAFF = 60;

    /** Safety cap on the single task fetch backing every row's drill-down. */
    private const TASK_FETCH_CAP = 2000;

    /**
     * @return array<string, mixed>
     */
    public function build(int $year): array
    {
        $now = CarbonImmutable::now();
        $today = $now->startOfDay();
        $todayDate = $today->toDateString();
        $maxMonth = $year >= $now->year ? $now->month : 12;
        $locale = app()->getLocale();

        $employees = Employee::query()
            ->whereNull('deleted_at')
            ->orderBy('full_name')
            ->get(['id', 'full_name']);

        $monthly = $this->monthlyCompletions($year);
        $tasksByEmployee = $this->drillTasks($year, $todayDate);

        $rows = [];
        $heroTrend = array_fill(0, 12, 0);
        $totalCompleted = 0;
        $totalOnTime = 0;

        foreach ($employees as $employee) {
            $perMonth = $monthly[$employee->id] ?? [];
            $display = [];
            $trend = [];
            $values = []; // completed per month, or null for inactive/empty months
            $sumCompleted = 0;
            $sumOnTime = 0;

            for ($m = 1; $m <= 12; $m++) {
                $active = $m <= $maxMonth;
                $completed = $active ? (int) ($perMonth[$m]['completed'] ?? 0) : 0;
                $onTime = $active ? (int) ($perMonth[$m]['ontime'] ?? 0) : 0;

                $trend[] = $completed;
                $values[] = ($active && $completed > 0) ? $completed : null;
                $display[] = $completed > 0 ? $completed.' · '.(int) round($onTime / $completed * 100).'%' : '';

                $sumCompleted += $completed;
                $sumOnTime += $onTime;
                $heroTrend[$m - 1] += $completed;
            }

            $totalCompleted += $sumCompleted;
            $totalOnTime += $sumOnTime;

            $rows[] = [
                'key' => $employee->id,
                'label' => $employee->full_name,
                'display' => $display,
                'trend' => $trend,
                'ytdTotal' => number_format($sumCompleted),
                'ytdOnTime' => $sumCompleted > 0 ? (int) round($sumOnTime / $sumCompleted * 100).'%' : __('ceo.kpi.na'),
                'bestIndex' => $this->extremeIndex($values, true),
                'worstIndex' => $this->extremeIndex($values, false),
                'mom' => $this->momChange($values),
                'tasks' => $tasksByEmployee[$employee->id] ?? [],
            ];
        }

        $heroOnTime = $totalCompleted > 0 ? (int) round($totalOnTime / $totalCompleted * 100) : null;

        return [
            'year' => $year,
            'prevYear' => $year - 1,
            'nextYear' => $year < $now->year ? $year + 1 : null,
            'label' => (string) $year,
            'accent' => 'cyan',
            'backHref' => '/ceo/tasks',
            'moduleHref' => '/hr/meetings',
            'moduleLabel' => __('ceo.kpi.module'),
            'months' => $this->monthHeaders($locale),
            'columns' => [
                'staff' => __('ceo.kpi.col_staff'),
                'total' => __('ceo.kpi.col_total'),
                'onTime' => __('ceo.kpi.col_ontime'),
                'trend' => __('ceo.kpi.col_trend'),
            ],
            'rows' => $rows,
            'summary' => [
                'heroLabel' => __('ceo.kpi.hero_label', ['year' => $year]),
                'heroValue' => number_format($totalCompleted),
                'heroSub' => $heroOnTime === null ? null : __('ceo.kpi.hero_ontime', ['pct' => $heroOnTime]),
                'trend' => $heroTrend,
            ],
        ];
    }

    /**
     * Per-employee, per-month completed + on-time counts for the year.
     *
     * @return array<int, array<int, array{completed: int, ontime: int}>>
     */
    private function monthlyCompletions(int $year): array
    {
        $monthExpr = $this->monthExpr('tasks.completed_at');

        $rows = DB::table('task_assignee')
            ->join('tasks', 'tasks.id', '=', 'task_assignee.task_id')
            ->whereNull('tasks.deleted_at')
            ->where('tasks.status', 'completed')
            ->whereYear('tasks.completed_at', $year)
            ->selectRaw("task_assignee.employee_id as eid, $monthExpr as m,
                COUNT(*) as completed,
                SUM(CASE WHEN date(tasks.completed_at) <= tasks.deadline THEN 1 ELSE 0 END) as ontime")
            ->groupBy('task_assignee.employee_id', 'm')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row->eid][(int) $row->m] = [
                'completed' => (int) $row->completed,
                'ontime' => (int) $row->ontime,
            ];
        }

        return $out;
    }

    /**
     * The tasks each employee can drill into: anything completed or due in the
     * year, plus anything still open. Bucketed by assignee, overdue-first.
     *
     * @return array<int, array<int, array<string, mixed>>>
     */
    private function drillTasks(int $year, string $todayDate): array
    {
        $tasks = Task::query()
            ->with(['assignees:id', 'category:id,name,color', 'taskable'])
            ->where(function ($query) use ($year) {
                $query->whereYear('completed_at', $year)
                    ->orWhereYear('deadline', $year)
                    ->orWhereIn('status', self::OPEN_STATUSES);
            })
            ->orderByRaw('CASE WHEN status IN (?, ?) AND deadline < ? THEN 0 ELSE 1 END', ['pending', 'in_progress', $todayDate])
            ->orderBy('deadline')
            ->limit(self::TASK_FETCH_CAP)
            ->get();

        $byEmployee = [];
        $counts = [];

        foreach ($tasks as $task) {
            $payload = $this->taskPayload($task, $todayDate);
            foreach ($task->assignees as $assignee) {
                if (($counts[$assignee->id] ?? 0) >= self::TASKS_PER_STAFF) {
                    continue;
                }
                $byEmployee[$assignee->id][] = $payload;
                $counts[$assignee->id] = ($counts[$assignee->id] ?? 0) + 1;
            }
        }

        return $byEmployee;
    }

    /**
     * @return array<string, mixed>
     */
    private function taskPayload(Task $task, string $todayDate): array
    {
        $meeting = $task->taskable instanceof Meeting ? $task->taskable : null;
        $overdue = in_array($task->status, self::OPEN_STATUSES, true)
            && $task->deadline
            && $task->deadline->toDateString() < $todayDate;

        return [
            'id' => $task->id,
            'title' => $task->title,
            'status' => $task->status,
            'statusLabel' => __('ceo.tasks.status_'.$task->status),
            'tone' => self::STATUS_TONE[$task->status] ?? 'muted',
            'deadline' => $task->deadline?->isoFormat('D MMM YYYY'),
            'source' => $meeting?->title,
            'category' => $task->category ? ['name' => $task->category->name, 'color' => $task->category->color] : null,
            'overdue' => $overdue,
        ];
    }

    /**
     * Index (0-based) of the best/worst active month by completed count. Null
     * when there are fewer than two months to compare.
     *
     * @param  array<int, int|null>  $values
     */
    private function extremeIndex(array $values, bool $best): ?int
    {
        $candidates = [];
        foreach ($values as $i => $v) {
            if (is_numeric($v)) {
                $candidates[$i] = $v;
            }
        }
        if (count($candidates) < 2) {
            return null;
        }

        $target = $best ? max($candidates) : min($candidates);

        return array_search($target, $candidates, true);
    }

    /**
     * Month-over-month completed change between the two most recent active
     * months (more completed = better).
     *
     * @param  array<int, int|null>  $values
     * @return array{text: string, tone: string}|null
     */
    private function momChange(array $values): ?array
    {
        $idx = [];
        foreach ($values as $i => $v) {
            if (is_numeric($v)) {
                $idx[] = $i;
            }
        }
        if (count($idx) < 2) {
            return null;
        }

        $last = $values[$idx[count($idx) - 1]];
        $prev = $values[$idx[count($idx) - 2]];
        if ($prev == 0) {
            return null;
        }

        $change = ($last - $prev) / abs($prev) * 100;
        $tone = abs($change) < 0.5 ? 'muted' : ($change > 0 ? 'positive' : 'negative');

        return ['text' => sprintf('%+d%%', (int) round($change)), 'tone' => $tone];
    }

    private function monthExpr(string $column): string
    {
        return DB::getDriverName() === 'sqlite'
            ? "CAST(strftime('%m', $column) AS INTEGER)"
            : "MONTH($column)";
    }

    /**
     * Localized short month names (Jan..Dec).
     *
     * @return array<int, string>
     */
    private function monthHeaders(string $locale): array
    {
        $headers = [];
        for ($m = 1; $m <= 12; $m++) {
            $headers[] = ucfirst(CarbonImmutable::create(2000, $m, 1)->locale($locale)->isoFormat('MMM'));
        }

        return $headers;
    }
}
