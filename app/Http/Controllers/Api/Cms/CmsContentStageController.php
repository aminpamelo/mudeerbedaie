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
     * Update the due date for a content stage.
     */
    public function updateDueDate(Request $request, Content $content, string $stage): JsonResponse
    {
        $validated = $request->validate([
            'due_date' => ['nullable', 'date'],
        ]);

        $contentStage = ContentStage::where('content_id', $content->id)
            ->where('stage', $stage)
            ->firstOrFail();

        $contentStage->update(['due_date' => $validated['due_date']]);

        return response()->json([
            'data' => $contentStage,
            'message' => 'Due date updated successfully.',
        ]);
    }

    /**
     * Update stage-specific meta fields (video_concept, stage_description, account_name, posting_time).
     */
    public function updateMeta(Request $request, Content $content, string $stage): JsonResponse
    {
        $validated = $request->validate([
            'video_concept' => ['nullable', 'string'],
            'stage_description' => ['nullable', 'string'],
            'account_name' => ['nullable', 'string', 'max:255'],
            'posting_time' => ['nullable', 'date'],
        ]);

        $contentStage = ContentStage::where('content_id', $content->id)
            ->where('stage', $stage)
            ->firstOrFail();

        $contentStage->update($validated);

        return response()->json([
            'data' => $contentStage,
            'message' => 'Stage details updated successfully.',
        ]);
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
