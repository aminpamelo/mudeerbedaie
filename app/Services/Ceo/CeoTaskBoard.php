<?php

namespace App\Services\Ceo;

use App\Models\Meeting;
use App\Models\Task;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/**
 * Builds the editable task list ("board") shown on the CEO Task Monitoring page.
 * Unlike the read-only aggregates in TaskMonitoringReport, this is a filterable,
 * paginated list the CEO can act on directly (status, priority, deadline,
 * reassignment, create, delete). It is intentionally a live snapshot of the work
 * itself — not scoped to the dashboard period.
 */
class CeoTaskBoard
{
    private const OPEN_STATUSES = ['pending', 'in_progress'];

    private const STATUSES = ['pending', 'in_progress', 'completed', 'cancelled'];

    private const PRIORITIES = ['low', 'medium', 'high', 'urgent'];

    private const PER_PAGE = 12;

    /**
     * @return array<string, mixed>
     */
    public function build(Request $request): array
    {
        $today = CarbonImmutable::now()->startOfDay();
        $todayDate = $today->toDateString();

        $status = $this->normalizeStatus($request->query('status'));
        $priority = in_array($request->query('priority'), self::PRIORITIES, true) ? $request->query('priority') : null;
        $assignedTo = $request->filled('assigned_to') ? (int) $request->query('assigned_to') : null;
        $search = trim((string) $request->query('search', '')) ?: null;

        $query = Task::query()
            ->with(['assignee:id,full_name', 'assignees:id,full_name', 'category:id,name,color', 'taskable'])
            ->when($status === 'open', fn ($q) => $q->whereIn('status', self::OPEN_STATUSES))
            ->when(in_array($status, self::STATUSES, true), fn ($q) => $q->where('status', $status))
            ->when($priority, fn ($q) => $q->where('priority', $priority))
            ->when($assignedTo, fn ($q) => $q->whereHas('assignees', fn ($sub) => $sub->where('employees.id', $assignedTo)))
            ->when($search, fn ($q) => $q->where(function ($sub) use ($search) {
                $sub->where('title', 'like', "%{$search}%")->orWhere('description', 'like', "%{$search}%");
            }))
            // Overdue, still-open tasks float to the top; then earliest deadline.
            ->orderByRaw('CASE WHEN status IN (?, ?) AND deadline < ? THEN 0 ELSE 1 END', ['pending', 'in_progress', $todayDate])
            ->orderBy('deadline');

        $paginator = $query->paginate(self::PER_PAGE)->withQueryString();

        $rows = collect($paginator->items())->map(fn (Task $task) => $this->row($task, $todayDate))->all();

        return [
            'data' => $rows,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
            ],
            'filters' => [
                'status' => $status,
                'priority' => $priority,
                'assigned_to' => $assignedTo,
                'search' => $search,
            ],
            'statusFilters' => [
                ['value' => 'open', 'label' => __('ceo.tasks.filter_open')],
                ['value' => 'all', 'label' => __('ceo.tasks.filter_all')],
                ...array_map(fn (string $s) => ['value' => $s, 'label' => __('ceo.tasks.status_'.$s)], self::STATUSES),
            ],
            'statusOptions' => array_map(fn (string $s) => ['value' => $s, 'label' => __('ceo.tasks.status_'.$s)], self::STATUSES),
            'priorityOptions' => array_map(fn (string $p) => ['value' => $p, 'label' => __('ceo.tasks.priority_'.$p)], self::PRIORITIES),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function row(Task $task, string $todayDate): array
    {
        $meeting = $task->taskable instanceof Meeting ? $task->taskable : null;
        $overdue = in_array($task->status, self::OPEN_STATUSES, true)
            && $task->deadline
            && $task->deadline->toDateString() < $todayDate;

        return [
            'id' => $task->id,
            'title' => $task->title,
            'description' => $task->description,
            'status' => $task->status,
            'priority' => $task->priority,
            'deadline' => $task->deadline?->toDateString(),
            'assigned_to' => $task->assigned_to,
            'assignee_name' => $task->assignee?->full_name,
            'assignees' => $task->assignees
                ->map(fn ($e) => ['id' => $e->id, 'name' => $e->full_name])
                ->values()
                ->all(),
            'category_id' => $task->category_id,
            'category' => $task->category ? [
                'id' => $task->category->id,
                'name' => $task->category->name,
                'color' => $task->category->color,
            ] : null,
            'source' => $meeting?->title,
            'meeting_id' => $meeting?->id,
            'overdue' => $overdue,
        ];
    }

    private function normalizeStatus(?string $status): string
    {
        if ($status === 'all' || in_array($status, self::STATUSES, true)) {
            return $status;
        }

        return 'open';
    }
}
