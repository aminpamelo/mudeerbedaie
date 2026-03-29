<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Models\Interview;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HrInterviewController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Interview::query()
            ->with(['applicant:id,full_name,applicant_number', 'interviewer:id,full_name']);

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        if ($from = $request->get('from')) {
            $query->where('interview_date', '>=', $from);
        }

        if ($to = $request->get('to')) {
            $query->where('interview_date', '<=', $to);
        }

        $interviews = $query->orderBy('interview_date')->orderBy('start_time')->paginate($request->get('per_page', 15));

        return response()->json($interviews);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'applicant_id' => ['required', 'exists:applicants,id'],
            'interviewer_id' => ['required', 'exists:employees,id'],
            'interview_date' => ['required', 'date'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'type' => ['required', 'in:phone,video,in_person'],
            'location' => ['nullable', 'string', 'max:255'],
        ]);

        $interview = Interview::create($validated);

        return response()->json([
            'message' => 'Interview scheduled successfully.',
            'data' => $interview->load(['applicant:id,full_name', 'interviewer:id,full_name']),
        ], 201);
    }

    public function update(Request $request, Interview $interview): JsonResponse
    {
        $validated = $request->validate([
            'interview_date' => ['sometimes', 'date'],
            'start_time' => ['sometimes', 'date_format:H:i'],
            'end_time' => ['sometimes', 'date_format:H:i'],
            'type' => ['sometimes', 'in:phone,video,in_person'],
            'location' => ['nullable', 'string', 'max:255'],
            'status' => ['sometimes', 'in:scheduled,completed,cancelled,no_show'],
        ]);

        $interview->update($validated);

        return response()->json([
            'message' => 'Interview updated successfully.',
            'data' => $interview,
        ]);
    }

    public function destroy(Interview $interview): JsonResponse
    {
        $interview->delete();

        return response()->json(['message' => 'Interview cancelled.']);
    }

    public function feedback(Request $request, Interview $interview): JsonResponse
    {
        $validated = $request->validate([
            'feedback' => ['required', 'string'],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
        ]);

        $interview->update(array_merge($validated, ['status' => 'completed']));

        return response()->json([
            'message' => 'Interview feedback submitted.',
            'data' => $interview,
        ]);
    }
}
