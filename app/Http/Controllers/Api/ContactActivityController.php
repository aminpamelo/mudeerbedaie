<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Services\CRM\ContactActivityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContactActivityController extends Controller
{
    public function __construct(
        private ContactActivityService $activityService
    ) {}

    public function index(Request $request, Student $student): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'nullable|string|max:100',
            'limit' => 'nullable|integer|min:1|max:100',
            'days' => 'nullable|integer|min:1|max:365',
        ]);

        $limit = $validated['limit'] ?? 50;

        if (isset($validated['type'])) {
            $activities = $this->activityService->getActivitiesByType(
                $student,
                $validated['type'],
                $limit
            );
        } elseif (isset($validated['days'])) {
            $activities = $this->activityService->getRecentActivities(
                $student,
                $validated['days']
            );
        } else {
            $activities = $this->activityService->getActivities($student, $limit);
        }

        return response()->json([
            'data' => $activities,
        ]);
    }

    public function store(Request $request, Student $student): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'required|string|max:100',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'metadata' => 'nullable|array',
        ]);

        $activity = $this->activityService->log(
            $student,
            $validated['type'],
            $validated['title'],
            $validated['description'] ?? null,
            $validated['metadata'] ?? []
        );

        return response()->json([
            'data' => $activity,
            'message' => 'Activity logged successfully',
        ], 201);
    }

    public function types(): JsonResponse
    {
        return response()->json([
            'data' => [
                ContactActivityService::TYPE_PAGE_VIEW,
                ContactActivityService::TYPE_EMAIL_OPENED,
                ContactActivityService::TYPE_EMAIL_CLICKED,
                ContactActivityService::TYPE_WHATSAPP_SENT,
                ContactActivityService::TYPE_WHATSAPP_REPLIED,
                ContactActivityService::TYPE_ORDER_CREATED,
                ContactActivityService::TYPE_ORDER_PAID,
                ContactActivityService::TYPE_ORDER_CANCELLED,
                ContactActivityService::TYPE_ENROLLMENT_CREATED,
                ContactActivityService::TYPE_CLASS_ATTENDED,
                ContactActivityService::TYPE_CLASS_ABSENT,
                ContactActivityService::TYPE_TAG_ADDED,
                ContactActivityService::TYPE_TAG_REMOVED,
                ContactActivityService::TYPE_WORKFLOW_ENTERED,
                ContactActivityService::TYPE_WORKFLOW_COMPLETED,
                ContactActivityService::TYPE_WORKFLOW_EXITED,
                ContactActivityService::TYPE_NOTE_ADDED,
                ContactActivityService::TYPE_PROFILE_UPDATED,
                ContactActivityService::TYPE_LOGIN,
                ContactActivityService::TYPE_CUSTOM,
            ],
        ]);
    }
}
