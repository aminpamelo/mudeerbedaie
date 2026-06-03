<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\StoreDisciplinaryActionRequest;
use App\Models\DisciplinaryAction;
use App\Models\Employee;
use App\Services\Hr\DisciplinaryLetterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class HrDisciplinaryActionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = DisciplinaryAction::query()
            ->with(['employee:id,full_name,employee_id,department_id', 'employee.department:id,name', 'issuer:id,full_name']);

        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('reference_number', 'like', "%{$search}%")
                    ->orWhereHas('employee', fn ($q) => $q->where('full_name', 'like', "%{$search}%"));
            });
        }

        if ($type = $request->get('type')) {
            $query->where('type', $type);
        }

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        if ($employeeId = $request->get('employee_id')) {
            $query->where('employee_id', $employeeId);
        }

        $actions = $query->orderByDesc('created_at')->paginate($request->get('per_page', 15));

        return response()->json($actions);
    }

    public function store(StoreDisciplinaryActionRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $issuer = Employee::where('user_id', $request->user()->id)->first();

        $action = DisciplinaryAction::create(array_merge($validated, [
            'reference_number' => DisciplinaryAction::generateReferenceNumber(),
            'issued_by' => $issuer?->id ?? $validated['employee_id'],
            'status' => 'draft',
            'response_required' => $validated['type'] === 'show_cause' ? true : ($validated['response_required'] ?? false),
        ]));

        return response()->json([
            'message' => 'Disciplinary action created.',
            'data' => $action->load(['employee:id,full_name,employee_id', 'issuer:id,full_name']),
        ], 201);
    }

    public function show(DisciplinaryAction $disciplinaryAction): JsonResponse
    {
        return response()->json([
            'data' => $disciplinaryAction->load([
                'employee:id,full_name,employee_id,department_id,position_id',
                'employee.department:id,name',
                'employee.position:id,title',
                'issuer:id,full_name',
                'previousAction:id,reference_number,type,status',
                'inquiry',
            ]),
        ]);
    }

    public function update(Request $request, DisciplinaryAction $disciplinaryAction): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['sometimes', 'string'],
            'incident_date' => ['sometimes', 'date'],
            'response_deadline' => ['nullable', 'date'],
            'outcome' => ['nullable', 'string'],
        ]);

        $disciplinaryAction->update($validated);

        return response()->json([
            'message' => 'Disciplinary action updated.',
            'data' => $disciplinaryAction,
        ]);
    }

    public function issue(DisciplinaryAction $disciplinaryAction): JsonResponse
    {
        $disciplinaryAction->update([
            'status' => $disciplinaryAction->response_required ? 'pending_response' : 'issued',
            'issued_date' => now(),
        ]);

        return response()->json([
            'message' => 'Disciplinary action issued.',
            'data' => $disciplinaryAction,
        ]);
    }

    public function close(DisciplinaryAction $disciplinaryAction): JsonResponse
    {
        $disciplinaryAction->update(['status' => 'closed']);

        return response()->json([
            'message' => 'Case closed.',
            'data' => $disciplinaryAction,
        ]);
    }

    public function pdf(DisciplinaryAction $disciplinaryAction, DisciplinaryLetterService $letterService): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        try {
            $path = $letterService->generatePdf($disciplinaryAction);
        } catch (\RuntimeException $e) {
            abort(404, $e->getMessage());
        }

        return response()->download(
            Storage::disk('public')->path($path),
            "disciplinary_{$disciplinaryAction->reference_number}.pdf"
        );
    }

    public function employeeHistory(int $employeeId): JsonResponse
    {
        $actions = DisciplinaryAction::where('employee_id', $employeeId)
            ->with(['issuer:id,full_name', 'inquiry'])
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['data' => $actions]);
    }
}
