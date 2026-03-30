<?php

namespace App\Http\Controllers\Api\Cms;

use App\Http\Controllers\Controller;
use App\Models\Content;
use App\Models\ContentStage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CmsContentStageController extends Controller
{
    /**
     * Add an assignee to a content stage.
     */
    public function addAssignee(Request $request, Content $content, string $stage): JsonResponse
    {
        $validated = $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'role' => ['nullable', 'string', 'max:100'],
        ]);

        $contentStage = ContentStage::where('content_id', $content->id)
            ->where('stage', $stage)
            ->firstOrFail();

        $assignee = $contentStage->assignees()->firstOrCreate(
            ['employee_id' => $validated['employee_id']],
            ['role' => $validated['role'] ?? null]
        );

        $assignee->load('employee:id,full_name,profile_photo');

        return response()->json([
            'data' => $assignee,
            'message' => 'Assignee added successfully.',
        ], 201);
    }

    /**
     * Remove an assignee from a content stage.
     */
    public function removeAssignee(Content $content, string $stage, int $employeeId): JsonResponse
    {
        $contentStage = ContentStage::where('content_id', $content->id)
            ->where('stage', $stage)
            ->firstOrFail();

        $contentStage->assignees()
            ->where('employee_id', $employeeId)
            ->delete();

        return response()->json(['message' => 'Assignee removed successfully.']);
    }
}
