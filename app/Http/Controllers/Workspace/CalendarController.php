<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CalendarController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Calendar');
    }

    public function events(Request $request): JsonResponse
    {
        $request->validate([
            'start' => 'required|date',
            'end' => 'required|date|after_or_equal:start',
        ]);

        $tasks = Task::query()
            ->whereNull('parent_id')
            ->where(function ($q) use ($request) {
                $q->whereBetween('deadline', [$request->input('start'), $request->input('end')])
                    ->orWhereBetween('start_date', [$request->input('start'), $request->input('end')]);
            })
            ->with(['assignees.user', 'project'])
            ->get()
            ->map(fn (Task $t) => [
                'id' => $t->id,
                'title' => $t->title,
                'start' => $t->start_date?->toDateString() ?? $t->deadline?->toDateString(),
                'end' => $t->deadline?->toDateString(),
                'priority' => $t->priority,
                'status' => $t->status,
                'project' => $t->project?->name,
                'projectColor' => $t->project?->color,
                'assignees' => $t->assignees->map(fn ($a) => $a->user?->name)->filter()->values(),
            ]);

        return response()->json(['data' => $tasks]);
    }
}
