<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\TmsProject;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    public function index(Request $request): Response
    {
        $projects = TmsProject::query()
            ->with(['owner', 'department'])
            ->withCount(['tasks', 'members'])
            ->orderBy('sort_order')
            ->get();

        $departments = Department::orderBy('name')->get(['id', 'name']);

        return Inertia::render('Projects', [
            'projects' => $projects,
            'departments' => $departments,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'color' => 'nullable|string|max:7',
            'department_id' => 'nullable|exists:departments,id',
            'start_date' => 'nullable|date',
            'target_date' => 'nullable|date',
        ]);

        $project = TmsProject::create([
            ...$validated,
            'owner_id' => $request->user()->id,
        ]);

        $project->members()->attach($request->user()->id, ['role' => 'owner']);

        return response()->json([
            'data' => $project,
            'message' => 'Project created.',
        ], 201);
    }

    public function show(Request $request, TmsProject $project): Response
    {
        $project->load(['owner', 'department', 'members']);

        $tasks = $project->tasks()
            ->whereNull('parent_id')
            ->with(['assignees.user', 'checklists'])
            ->withCount(['subtasks', 'checklists', 'attachments', 'comments'])
            ->orderBy('sort_order')
            ->get();

        return Inertia::render('ProjectDetail', [
            'project' => $project,
            'tasks' => $tasks,
        ]);
    }

    public function update(Request $request, TmsProject $project): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'color' => 'nullable|string|max:7',
            'status' => 'sometimes|in:active,on_hold,completed,archived',
            'department_id' => 'nullable|exists:departments,id',
            'start_date' => 'nullable|date',
            'target_date' => 'nullable|date',
        ]);

        $project->update($validated);

        return response()->json([
            'data' => $project->fresh(),
            'message' => 'Project updated.',
        ]);
    }

    public function destroy(TmsProject $project): JsonResponse
    {
        $project->delete();

        return response()->json(['message' => 'Project deleted.']);
    }

    public function addMember(Request $request, TmsProject $project): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'role' => 'nullable|in:manager,member,viewer',
        ]);

        $project->members()->syncWithoutDetaching([
            $validated['user_id'] => ['role' => $validated['role'] ?? 'member'],
        ]);

        return response()->json(['message' => 'Member added.']);
    }

    public function removeMember(TmsProject $project, int $userId): JsonResponse
    {
        $project->members()->detach($userId);

        return response()->json(['message' => 'Member removed.']);
    }
}
