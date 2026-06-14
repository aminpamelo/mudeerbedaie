<?php

namespace App\Services\Ceo;

use App\Models\Task;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Builds the month-grid "Calendar" view on the CEO Task Monitoring page.
 *
 * Tasks are plotted on a calendar grid keyed either by their deadline (when work
 * is due — surfaces overdue backlog + upcoming crunch) or by their completion
 * date (throughput history). The CEO toggles between the two bases. Like the
 * editable board, this is a live snapshot of the work itself, navigated month by
 * month independently of the dashboard period.
 *
 * Each day cell carries four overlaid signals: priority colour dots, a status
 * mini-bar (done / in-progress / pending, or on-time / late), an overdue/late
 * alert flag, and a workload-heat background.
 */
class CeoTaskCalendar
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

    private const PRIORITY_WEIGHT = ['urgent' => 0, 'high' => 1, 'medium' => 2, 'low' => 3];

    /**
     * @return array<string, mixed>
     */
    public function build(Request $request): array
    {
        $locale = app()->getLocale();
        $today = CarbonImmutable::now()->startOfDay();
        $todayDate = $today->toDateString();

        $basis = $request->query('basis') === 'completed' ? 'completed' : 'deadline';
        $monthStart = ($this->parseMonth($request->query('month')) ?? $today)->startOfMonth();
        $monthEnd = $monthStart->endOfMonth();

        // Pad the month out to whole weeks (Monday–Sunday) so the grid is rectangular.
        $gridStart = $monthStart->startOfWeek(CarbonImmutable::MONDAY);
        $gridEnd = $monthEnd->endOfWeek(CarbonImmutable::SUNDAY);

        $grouped = $this->tasksByDay($basis, $gridStart, $gridEnd, $todayDate);

        // First pass: build every cell so we know the busiest in-month day, which
        // the workload-heat intensity is normalised against.
        $cells = [];
        $maxInMonth = 0;
        for ($date = $gridStart; $date->lessThanOrEqualTo($gridEnd); $date = $date->addDay()) {
            $key = $date->toDateString();
            $tasks = $grouped->get($key, collect());
            $inMonth = $date->betweenIncluded($monthStart, $monthEnd);

            if ($inMonth) {
                $maxInMonth = max($maxInMonth, $tasks->count());
            }

            $cells[] = [
                'date' => $key,
                'dateLabel' => ucfirst($date->locale($locale)->isoFormat('ddd, D MMM')),
                'day' => $date->day,
                'inMonth' => $inMonth,
                'isToday' => $key === $todayDate,
                'isWeekend' => $date->isWeekend(),
                'tasks' => $tasks->all(),
            ];
        }

        $weeks = collect($cells)
            ->map(fn (array $cell) => $this->decorateCell($cell, $basis, $maxInMonth))
            ->chunk(7)
            ->map->values()
            ->values()
            ->all();

        return [
            'basis' => $basis,
            'month' => [
                'key' => $monthStart->format('Y-m'),
                'label' => ucfirst($monthStart->locale($locale)->isoFormat('MMMM YYYY')),
                'prev' => $monthStart->subMonthNoOverflow()->format('Y-m'),
                'next' => $monthStart->addMonthNoOverflow()->format('Y-m'),
                'today' => $todayDate,
                'todayKey' => $today->format('Y-m'),
                'isCurrent' => $monthStart->isSameMonth($today),
            ],
            'weekdays' => $this->weekdayLabels($gridStart, $locale),
            'weeks' => $weeks,
            'summary' => $this->summary($basis, $grouped, $monthStart, $monthEnd, $today, $locale),
            'legend' => $this->legend($basis),
        ];
    }

    /**
     * Tasks falling inside the padded grid window, grouped by their day key
     * (deadline date or completion date) and shaped into lean summaries.
     *
     * @return Collection<string, Collection<int, array<string, mixed>>>
     */
    private function tasksByDay(string $basis, CarbonImmutable $gridStart, CarbonImmutable $gridEnd, string $todayDate): Collection
    {
        $query = Task::query()->with(['assignees:id,full_name', 'category:id,name,color']);

        if ($basis === 'completed') {
            $query->where('status', 'completed')
                ->whereNotNull('completed_at')
                ->whereBetween('completed_at', [$gridStart->startOfDay(), $gridEnd->endOfDay()]);
        } else {
            $query->where('status', '!=', 'cancelled')
                ->whereNotNull('deadline')
                ->whereBetween('deadline', [$gridStart->toDateString(), $gridEnd->toDateString()]);
        }

        return $query->get()
            ->map(fn (Task $task) => $this->taskSummary($task, $todayDate))
            ->groupBy($basis === 'completed' ? 'completedAt' : 'deadline')
            ->map(fn (Collection $group) => $group
                ->sortBy([
                    fn (array $t) => self::PRIORITY_WEIGHT[$t['priority']] ?? 99,
                    fn (array $t) => $t['title'],
                ])
                ->values());
    }

    /**
     * @return array<string, mixed>
     */
    private function taskSummary(Task $task, string $todayDate): array
    {
        $overdue = in_array($task->status, self::OPEN_STATUSES, true)
            && $task->deadline
            && $task->deadline->toDateString() < $todayDate;

        $late = $task->status === 'completed'
            && $task->completed_at
            && $task->deadline
            && $task->completed_at->toDateString() > $task->deadline->toDateString();

        return [
            'id' => $task->id,
            'title' => $task->title,
            'priority' => $task->priority,
            'priorityLabel' => __('ceo.tasks.priority_'.$task->priority),
            'priorityTone' => self::PRIORITY_TONE[$task->priority] ?? 'muted',
            'status' => $task->status,
            'statusLabel' => __('ceo.tasks.status_'.$task->status),
            'statusTone' => self::STATUS_TONE[$task->status] ?? 'muted',
            'deadline' => $task->deadline?->toDateString(),
            'completedAt' => $task->completed_at?->toDateString(),
            'assignees' => $task->assignees->pluck('full_name')->filter()->values()->all(),
            'overdue' => $overdue,
            'late' => $late,
            'category' => $task->category ? [
                'name' => $task->category->name,
                'color' => $task->category->color,
            ] : null,
        ];
    }

    /**
     * Fold a built cell's task list into the per-day signals the grid renders.
     *
     * @param  array<string, mixed>  $cell
     * @return array<string, mixed>
     */
    private function decorateCell(array $cell, string $basis, int $maxInMonth): array
    {
        $tasks = collect($cell['tasks']);
        $total = $tasks->count();

        $alert = $basis === 'completed'
            ? $tasks->where('late', true)->count()
            : $tasks->where('overdue', true)->count();

        $priority = collect(['urgent', 'high', 'medium', 'low'])
            ->mapWithKeys(fn (string $p) => [$p => $tasks->where('priority', $p)->count()])
            ->all();

        $segments = $basis === 'completed'
            ? [
                ['key' => 'onTime', 'value' => $tasks->where('late', false)->count(), 'tone' => 'positive'],
                ['key' => 'late', 'value' => $tasks->where('late', true)->count(), 'tone' => 'negative'],
            ]
            : [
                ['key' => 'completed', 'value' => $tasks->where('status', 'completed')->count(), 'tone' => 'positive'],
                ['key' => 'in_progress', 'value' => $tasks->where('status', 'in_progress')->count(), 'tone' => 'warning'],
                ['key' => 'pending', 'value' => $tasks->where('status', 'pending')->count(), 'tone' => 'info'],
            ];

        $heat = $cell['inMonth'] && $maxInMonth > 0
            ? round($total / $maxInMonth, 3)
            : 0.0;

        return [
            ...$cell,
            'total' => $total,
            'alert' => $alert,
            'priority' => $priority,
            'segments' => $segments,
            'heat' => $heat,
        ];
    }

    /**
     * Month-scoped headline stats (in-month days only) plus the busiest day.
     *
     * @param  Collection<string, Collection<int, array<string, mixed>>>  $grouped
     * @return array<string, mixed>
     */
    private function summary(string $basis, Collection $grouped, CarbonImmutable $monthStart, CarbonImmutable $monthEnd, CarbonImmutable $today, string $locale): array
    {
        $inMonth = $grouped
            ->filter(fn (Collection $tasks, string $date) => CarbonImmutable::parse($date)->betweenIncluded($monthStart, $monthEnd))
            ->flatMap(fn (Collection $tasks) => $tasks);

        $busiest = $grouped
            ->filter(fn (Collection $tasks, string $date) => CarbonImmutable::parse($date)->betweenIncluded($monthStart, $monthEnd))
            ->map->count()
            ->sortDesc()
            ->take(1);

        $busiestDay = $busiest->isNotEmpty() ? [
            'date' => $busiest->keys()->first(),
            'label' => ucfirst(CarbonImmutable::parse($busiest->keys()->first())->locale($locale)->isoFormat('ddd, D MMM')),
            'count' => $busiest->first(),
        ] : null;

        if ($basis === 'completed') {
            $completed = $inMonth->count();
            $late = $inMonth->where('late', true)->count();
            $onTime = $completed - $late;
            $onTimeRate = $completed > 0 ? (int) round($onTime / $completed * 100) : null;

            $stats = [
                $this->stat(__('ceo.tasks.completed'), (string) $completed, 'positive'),
                $this->stat(__('ceo.ui.cal_on_time'), (string) $onTime, 'positive'),
                $this->stat(__('ceo.ui.cal_late'), (string) $late, $late > 0 ? 'negative' : 'muted'),
                $this->stat(__('ceo.tasks.on_time_rate'), $onTimeRate === null ? '—' : $onTimeRate.'%', $this->rateTone($onTimeRate)),
            ];
        } else {
            $due = $inMonth->count();
            $completed = $inMonth->where('status', 'completed')->count();
            $overdue = $inMonth->where('overdue', true)->count();
            $completionRate = $due > 0 ? (int) round($completed / $due * 100) : null;

            $stats = [
                $this->stat(__('ceo.ui.cal_due'), (string) $due, $due > 0 ? 'info' : 'muted'),
                $this->stat(__('ceo.tasks.completed'), (string) $completed, 'positive'),
                $this->stat(__('ceo.tasks.overdue'), (string) $overdue, $overdue > 0 ? 'negative' : 'muted'),
                $this->stat(__('ceo.tasks.completion_rate'), $completionRate === null ? '—' : $completionRate.'%', $this->rateTone($completionRate)),
            ];
        }

        return [
            'stats' => $stats,
            'busiest' => $busiestDay,
        ];
    }

    /**
     * @return array{label: string, value: string, tone: string}
     */
    private function stat(string $label, string $value, string $tone): array
    {
        return ['label' => $label, 'value' => $value, 'tone' => $tone];
    }

    /**
     * Colour key for the grid — the priority dots plus the basis-aware status flow.
     *
     * @return array<string, array<int, array{label: string, tone: string}>>
     */
    private function legend(string $basis): array
    {
        $priority = collect(['urgent', 'high', 'medium', 'low'])
            ->map(fn (string $p) => ['label' => __('ceo.tasks.priority_'.$p), 'tone' => self::PRIORITY_TONE[$p]])
            ->all();

        $flow = $basis === 'completed'
            ? [
                ['label' => __('ceo.ui.cal_on_time'), 'tone' => 'positive'],
                ['label' => __('ceo.ui.cal_late'), 'tone' => 'negative'],
            ]
            : [
                ['label' => __('ceo.tasks.status_completed'), 'tone' => 'positive'],
                ['label' => __('ceo.tasks.status_in_progress'), 'tone' => 'warning'],
                ['label' => __('ceo.tasks.status_pending'), 'tone' => 'info'],
            ];

        return ['priority' => $priority, 'flow' => $flow];
    }

    /**
     * Localised short weekday names in the grid's column order (Monday first).
     *
     * @return array<int, string>
     */
    private function weekdayLabels(CarbonImmutable $gridStart, string $locale): array
    {
        $labels = [];
        for ($i = 0; $i < 7; $i++) {
            $labels[] = ucfirst($gridStart->addDays($i)->locale($locale)->isoFormat('ddd'));
        }

        return $labels;
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

    private function parseMonth(?string $ref): ?CarbonImmutable
    {
        if (! $ref || ! preg_match('/^\d{4}-\d{2}$/', $ref)) {
            return null;
        }

        try {
            return CarbonImmutable::createFromFormat('Y-m-d', $ref.'-01')->startOfMonth();
        } catch (\Throwable) {
            return null;
        }
    }
}
