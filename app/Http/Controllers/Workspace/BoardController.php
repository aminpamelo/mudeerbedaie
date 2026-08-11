<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\TmsProject;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BoardController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Task::query()
            ->whereNull('parent_id')
            ->with(['assignees.user', 'checklists', 'project', 'category'])
            ->withCount(['subtasks', 'checklists', 'attachments', 'comments']);

        if ($request->filled('project_id')) {
            $query->where('project_id', $request->input('project_id'));
        }
        if ($request->filled('priority')) {
            $query->where('priority', $request->input('priority'));
        }
        if ($request->filled('assigned_to')) {
            $query->where('assigned_to', $request->input('assigned_to'));
        }

        $tasks = $query->orderBy('sort_order')->get();

        $columns = [
            ['key' => 'pending', 'label' => 'To Do', 'color' => 'slate'],
            ['key' => 'in_progress', 'label' => 'In Progress', 'color' => 'blue'],
            ['key' => 'review', 'label' => 'Review', 'color' => 'purple'],
            ['key' => 'completed', 'label' => 'Completed', 'color' => 'emerald'],
        ];

        $grouped = [];
        foreach ($columns as $col) {
            $grouped[$col['key']] = $tasks->where('status', $col['key'])->values();
        }

        $projects = TmsProject::active()->orderBy('name')->get(['id', 'name', 'color']);

        return Inertia::render('Board', [
            'columns' => $columns,
            'tasks' => $grouped,
            'projects' => $projects,
        ]);
    }
}
