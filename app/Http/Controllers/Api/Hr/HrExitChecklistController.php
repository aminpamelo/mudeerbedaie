<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Models\ExitChecklist;
use App\Models\ExitChecklistItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HrExitChecklistController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $checklists = ExitChecklist::query()
            ->with(['employee:id,full_name,employee_id,department_id', 'employee.department:id,name'])
            ->withCount([
                'items as total_items',
                'items as completed_items' => fn ($q) => $q->where('status', 'completed'),
            ])
            ->orderByDesc('created_at')
            ->paginate($request->get('per_page', 15));

        return response()->json($checklists);
    }

    public function createForEmployee(int $employeeId): JsonResponse
    {
        $checklist = ExitChecklist::create([
            'employee_id' => $employeeId,
            'status' => 'in_progress',
        ]);

        $checklist->createDefaultItems();
        $checklist->addAssetReturnItems();

        return response()->json([
            'message' => 'Exit checklist created.',
            'data' => $checklist->load('items'),
        ], 201);
    }

    public function show(ExitChecklist $exitChecklist): JsonResponse
    {
        return response()->json([
            'data' => $exitChecklist->load([
                'employee:id,full_name,employee_id,department_id',
                'employee.department:id,name',
                'resignationRequest',
                'items.assignedEmployee:id,full_name',
                'items.completedByUser:id,name',
            ]),
        ]);
    }

    public function updateItem(Request $request, ExitChecklist $exitChecklist, ExitChecklistItem $item): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:pending,completed,not_applicable'],
            'notes' => ['nullable', 'string'],
        ]);

        $item->update(array_merge($validated, [
            'completed_at' => $validated['status'] === 'completed' ? now() : null,
            'completed_by' => $validated['status'] === 'completed' ? $request->user()->id : null,
        ]));

        $allCompleted = $exitChecklist->items()
            ->whereNotIn('status', ['completed', 'not_applicable'])
            ->count() === 0;

        if ($allCompleted) {
            $exitChecklist->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);
        }

        return response()->json([
            'message' => 'Checklist item updated.',
            'data' => $item,
            'checklist_completed' => $allCompleted,
        ]);
    }
}
