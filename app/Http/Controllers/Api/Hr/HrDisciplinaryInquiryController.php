<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Models\DisciplinaryInquiry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HrDisciplinaryInquiryController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'disciplinary_action_id' => ['required', 'exists:disciplinary_actions,id'],
            'hearing_date' => ['required', 'date'],
            'hearing_time' => ['required', 'date_format:H:i'],
            'location' => ['required', 'string', 'max:255'],
            'panel_members' => ['required', 'array', 'min:1'],
            'panel_members.*' => ['integer', 'exists:employees,id'],
        ]);

        $inquiry = DisciplinaryInquiry::create(array_merge($validated, [
            'status' => 'scheduled',
        ]));

        return response()->json([
            'message' => 'Domestic inquiry scheduled.',
            'data' => $inquiry,
        ], 201);
    }

    public function show(DisciplinaryInquiry $disciplinaryInquiry): JsonResponse
    {
        return response()->json([
            'data' => $disciplinaryInquiry->load([
                'disciplinaryAction.employee:id,full_name,employee_id',
            ]),
        ]);
    }

    public function update(Request $request, DisciplinaryInquiry $disciplinaryInquiry): JsonResponse
    {
        $validated = $request->validate([
            'hearing_date' => ['sometimes', 'date'],
            'hearing_time' => ['sometimes', 'date_format:H:i'],
            'location' => ['sometimes', 'string', 'max:255'],
            'panel_members' => ['sometimes', 'array'],
            'panel_members.*' => ['integer', 'exists:employees,id'],
            'minutes' => ['nullable', 'string'],
            'findings' => ['nullable', 'string'],
        ]);

        $disciplinaryInquiry->update($validated);

        return response()->json([
            'message' => 'Inquiry updated.',
            'data' => $disciplinaryInquiry,
        ]);
    }

    public function complete(Request $request, DisciplinaryInquiry $disciplinaryInquiry): JsonResponse
    {
        $validated = $request->validate([
            'decision' => ['required', 'in:guilty,not_guilty,partially_guilty'],
            'findings' => ['required', 'string'],
            'penalty' => ['nullable', 'string'],
        ]);

        return DB::transaction(function () use ($validated, $disciplinaryInquiry) {
            $disciplinaryInquiry->update(array_merge($validated, [
                'status' => 'completed',
            ]));

            $action = $disciplinaryInquiry->disciplinaryAction;
            if ($validated['decision'] === 'not_guilty') {
                $action->update([
                    'outcome' => 'Acquitted after domestic inquiry.',
                    'status' => 'closed',
                ]);
            } else {
                $action->update([
                    'outcome' => "Decision: {$validated['decision']}. Penalty: ".($validated['penalty'] ?? 'N/A'),
                ]);
            }

            return response()->json([
                'message' => 'Inquiry completed.',
                'data' => $disciplinaryInquiry->fresh('disciplinaryAction'),
            ]);
        });
    }
}
