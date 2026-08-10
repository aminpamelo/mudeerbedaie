<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\TmsProject;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GanttController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Task::query()
            ->whereNull('parent_id')
            ->whereNotNull('deadline')
            ->with(['project', 'assignees.user', 'dependencies'])
            ->orderBy('start_date')
            ->orderBy('deadline');

        if ($request->filled('project_id')) {
            $query->where('project_id', $request->input('project_id'));
        }

        $tasks = $query->get()->map(fn ($t) => [
            'id' => $t->id,
            'title' => $t->title,
            'start' => $t->start_date?->toDateString() ?? $t->created_at->toDateString(),
            'end' => $t->deadline->toDateString(),
            'status' => $t->status,
            'priority' => $t->priority,
            'progress' => $t->status === 'completed' ? 100 : ($t->status === 'in_progress' ? 50 : ($t->status === 'review' ? 75 : 0)),
            'project' => $t->project?->name,
            'projectColor' => $t->project?->color ?? '#6366f1',
            'assignees' => $t->assignees->map(fn ($a) => $a->user?->name)->filter()->values(),
            'dependencies' => $t->dependencies->pluck('depends_on_task_id')->values(),
        ]);

        $projects = TmsProject::active()->orderBy('name')->get(['id', 'name', 'color']);

        return Inertia::render('Gantt', ['tasks' => $tasks, 'projects' => $projects]);
    }
}
