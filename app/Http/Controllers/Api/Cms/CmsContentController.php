<?php

namespace App\Http\Controllers\Api\Cms;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cms\StoreContentRequest;
use App\Http\Requests\Cms\UpdateContentRequest;
use App\Models\Content;
use App\Models\ContentStage;
use App\Models\ContentStat;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CmsContentController extends Controller
{
    /**
     * Paginated list with filters.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Content::query()
            ->with([
                'creator:id,full_name,profile_photo',
                'stages.assignees.employee:id,full_name,profile_photo',
            ]);

        if ($stage = $request->get('stage')) {
            $query->where('stage', $stage);
        }

        if ($priority = $request->get('priority')) {
            $query->where('priority', $priority);
        }

        if ($assigneeId = $request->get('assignee_id')) {
            $query->whereHas('stages.assignees', function ($q) use ($assigneeId) {
                $q->where('employee_id', $assigneeId);
            });
        }

        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($fromDate = $request->get('from_date')) {
            $query->whereDate('created_at', '>=', $fromDate);
        }

        if ($toDate = $request->get('to_date')) {
            $query->whereDate('created_at', '<=', $toDate);
        }

        $sortBy = $request->get('sort', 'created_at');
        $sortDir = $request->get('direction', 'desc');
        $query->orderBy($sortBy, $sortDir);

        $contents = $query->paginate($request->get('per_page', 15));

        return response()->json($contents);
    }

    /**
     * Create content with stages and assignees.
     */
    public function store(StoreContentRequest $request): JsonResponse
    {
        $validated = $request->validated();

        return DB::transaction(function () use ($validated, $request) {
            $employee = Employee::where('user_id', $request->user()->id)->first();

            $content = Content::create([
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'due_date' => $validated['due_date'] ?? null,
                'priority' => $validated['priority'],
                'tiktok_url' => $validated['tiktok_url'] ?? null,
                'video_url' => $validated['video_url'] ?? null,
                'stage' => 'idea',
                'created_by' => $employee?->id,
            ]);

            $stageNames = ['idea', 'shooting', 'editing', 'posting'];
            $stageData = collect($validated['stages'] ?? []);

            foreach ($stageNames as $stageName) {
                $requestStage = $stageData->firstWhere('stage', $stageName);

                $contentStage = ContentStage::create([
                    'content_id' => $content->id,
                    'stage' => $stageName,
                    'status' => $stageName === 'idea' ? 'in_progress' : 'pending',
                    'due_date' => $requestStage['due_date'] ?? null,
                    'started_at' => $stageName === 'idea' ? now() : null,
                ]);

                if (! empty($requestStage['assignees'])) {
                    foreach ($requestStage['assignees'] as $assignee) {
                        $contentStage->assignees()->create([
                            'employee_id' => $assignee['employee_id'],
                            'role' => $assignee['role'] ?? null,
                        ]);
                    }
                }
            }

            $content->load([
                'creator:id,full_name,profile_photo',
                'stages.assignees.employee:id,full_name,profile_photo',
            ]);

            return response()->json([
                'data' => $content,
                'message' => 'Content created successfully.',
            ], 201);
        });
    }

    /**
     * Show content with all relationships.
     */
    public function show(Content $content): JsonResponse
    {
        $content->load([
            'creator:id,full_name,profile_photo',
            'markedByEmployee:id,full_name,profile_photo',
            'stages.assignees.employee:id,full_name,profile_photo',
            'stats' => function ($query) {
                $query->latest('fetched_at')->limit(10);
            },
            'adCampaigns.assignedByEmployee:id,full_name,profile_photo',
        ]);

        return response()->json(['data' => $content]);
    }

    /**
     * Update content fields and optionally stages/assignees.
     */
    public function update(UpdateContentRequest $request, Content $content): JsonResponse
    {
        $validated = $request->validated();

        return DB::transaction(function () use ($validated, $content) {
            $content->update(collect($validated)->except('stages')->toArray());

            if (! empty($validated['stages'])) {
                foreach ($validated['stages'] as $stageData) {
                    $contentStage = ContentStage::where('content_id', $content->id)
                        ->where('stage', $stageData['stage'])
                        ->first();

                    if ($contentStage) {
                        if (isset($stageData['due_date'])) {
                            $contentStage->update(['due_date' => $stageData['due_date']]);
                        }

                        if (isset($stageData['assignees'])) {
                            $contentStage->assignees()->delete();
                            foreach ($stageData['assignees'] as $assignee) {
                                $contentStage->assignees()->create([
                                    'employee_id' => $assignee['employee_id'],
                                    'role' => $assignee['role'] ?? null,
                                ]);
                            }
                        }
                    }
                }
            }

            $content->load([
                'creator:id,full_name,profile_photo',
                'stages.assignees.employee:id,full_name,profile_photo',
            ]);

            return response()->json([
                'data' => $content,
                'message' => 'Content updated successfully.',
            ]);
        });
    }

    /**
     * Soft delete content.
     */
    public function destroy(Content $content): JsonResponse
    {
        $content->delete();

        return response()->json(['message' => 'Content deleted successfully.']);
    }

    /**
     * Update content stage progression.
     */
    public function updateStage(Request $request, Content $content): JsonResponse
    {
        $validated = $request->validate([
            'stage' => ['required', 'in:idea,shooting,editing,posting,posted'],
        ]);

        return DB::transaction(function () use ($validated, $content) {
            // Complete current stage
            $currentStage = ContentStage::where('content_id', $content->id)
                ->where('stage', $content->stage)
                ->first();

            if ($currentStage) {
                $currentStage->update([
                    'status' => 'completed',
                    'completed_at' => now(),
                ]);
            }

            // Start new stage (if not 'posted' which has no ContentStage record)
            if ($validated['stage'] !== 'posted') {
                $newStage = ContentStage::where('content_id', $content->id)
                    ->where('stage', $validated['stage'])
                    ->first();

                if ($newStage) {
                    $newStage->update([
                        'status' => 'in_progress',
                        'started_at' => now(),
                    ]);
                }
            }

            $updateData = ['stage' => $validated['stage']];
            if ($validated['stage'] === 'posted') {
                $updateData['posted_at'] = now();
            }

            $content->update($updateData);

            $content->load([
                'creator:id,full_name,profile_photo',
                'stages.assignees.employee:id,full_name,profile_photo',
            ]);

            return response()->json([
                'data' => $content,
                'message' => 'Content stage updated successfully.',
            ]);
        });
    }

    /**
     * Add performance stats to content.
     */
    public function addStats(Request $request, Content $content): JsonResponse
    {
        $validated = $request->validate([
            'views' => ['required', 'integer', 'min:0'],
            'likes' => ['required', 'integer', 'min:0'],
            'comments' => ['required', 'integer', 'min:0'],
            'shares' => ['required', 'integer', 'min:0'],
        ]);

        $stat = ContentStat::create([
            'content_id' => $content->id,
            'views' => $validated['views'],
            'likes' => $validated['likes'],
            'comments' => $validated['comments'],
            'shares' => $validated['shares'],
            'fetched_at' => now(),
        ]);

        // Auto-flag for ads based on thresholds
        $engagementRate = $validated['views'] > 0
            ? ($validated['likes'] + $validated['comments'] + $validated['shares']) / $validated['views'] * 100
            : 0;

        if ($validated['views'] > 10000 || $validated['likes'] > 1000 || $engagementRate > 5) {
            $content->update(['is_flagged_for_ads' => true]);
        }

        return response()->json([
            'data' => $stat,
            'message' => 'Stats added successfully.',
        ], 201);
    }

    /**
     * Toggle mark for ads status.
     */
    public function markForAds(Request $request, Content $content): JsonResponse
    {
        $employee = Employee::where('user_id', $request->user()->id)->first();

        if ($content->is_marked_for_ads) {
            $content->update([
                'is_marked_for_ads' => false,
                'marked_by' => null,
                'marked_at' => null,
            ]);
        } else {
            $content->update([
                'is_marked_for_ads' => true,
                'marked_by' => $employee?->id,
                'marked_at' => now(),
            ]);
        }

        $content->load('markedByEmployee:id,full_name,profile_photo');

        return response()->json([
            'data' => $content,
            'message' => $content->is_marked_for_ads
                ? 'Content marked for ads.'
                : 'Content unmarked for ads.',
        ]);
    }

    /**
     * Return contents grouped by stage for kanban view.
     */
    public function kanban(): JsonResponse
    {
        $stages = ['idea', 'shooting', 'editing', 'posting', 'posted'];
        $kanban = [];

        foreach ($stages as $stage) {
            $kanban[$stage] = Content::where('stage', $stage)
                ->with([
                    'creator:id,full_name,profile_photo',
                    'stages' => function ($query) use ($stage) {
                        $query->where('stage', $stage);
                    },
                    'stages.assignees.employee:id,full_name,profile_photo',
                ])
                ->orderByRaw("CASE priority WHEN 'urgent' THEN 1 WHEN 'high' THEN 2 WHEN 'medium' THEN 3 WHEN 'low' THEN 4 ELSE 5 END")
                ->orderBy('due_date', 'asc')
                ->get();
        }

        return response()->json(['data' => $kanban]);
    }

    /**
     * Return contents filtered by month/year for calendar view.
     */
    public function calendar(Request $request): JsonResponse
    {
        $month = $request->get('month', now()->month);
        $year = $request->get('year', now()->year);

        $contents = Content::whereNotNull('due_date')
            ->whereMonth('due_date', $month)
            ->whereYear('due_date', $year)
            ->with('creator:id,full_name,profile_photo')
            ->orderBy('due_date')
            ->get();

        return response()->json(['data' => $contents]);
    }
}
