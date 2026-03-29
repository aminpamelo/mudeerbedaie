<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Models\KpiTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HrKpiTemplateController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = KpiTemplate::query()
            ->with(['position:id,title', 'department:id,name']);

        if ($positionId = $request->get('position_id')) {
            $query->where('position_id', $positionId);
        }

        if ($departmentId = $request->get('department_id')) {
            $query->where('department_id', $departmentId);
        }

        if ($category = $request->get('category')) {
            $query->where('category', $category);
        }

        return response()->json(['data' => $query->orderBy('title')->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'position_id' => ['nullable', 'exists:positions,id'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'target' => ['required', 'string', 'max:255'],
            'weight' => ['required', 'numeric', 'min:1', 'max:100'],
            'category' => ['required', 'in:quantitative,qualitative,behavioral'],
        ]);

        $kpi = KpiTemplate::create($validated);

        return response()->json([
            'message' => 'KPI template created.',
            'data' => $kpi,
        ], 201);
    }

    public function update(Request $request, KpiTemplate $kpiTemplate): JsonResponse
    {
        $validated = $request->validate([
            'position_id' => ['nullable', 'exists:positions,id'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'target' => ['sometimes', 'string', 'max:255'],
            'weight' => ['sometimes', 'numeric', 'min:1', 'max:100'],
            'category' => ['sometimes', 'in:quantitative,qualitative,behavioral'],
            'is_active' => ['boolean'],
        ]);

        $kpiTemplate->update($validated);

        return response()->json([
            'message' => 'KPI template updated.',
            'data' => $kpiTemplate,
        ]);
    }

    public function destroy(KpiTemplate $kpiTemplate): JsonResponse
    {
        $kpiTemplate->delete();

        return response()->json(['message' => 'KPI template deleted.']);
    }
}
