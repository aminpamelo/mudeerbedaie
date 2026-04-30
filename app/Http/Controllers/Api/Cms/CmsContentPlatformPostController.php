<?php

namespace App\Http\Controllers\Api\Cms;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cms\BulkAssignPlatformPostsRequest;
use App\Http\Requests\Cms\UpdatePlatformPostRequest;
use App\Http\Requests\Cms\UpdatePlatformPostStatsRequest;
use App\Models\CmsContentPlatformPost;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CmsContentPlatformPostController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = CmsContentPlatformPost::query()
            ->whereHas('content') // exclude orphans whose content was soft-deleted
            ->with([
                'content:id,title,tiktok_url',
                'platform',
                'assignee:id,full_name,profile_photo',
            ])
            ->latest('updated_at');

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($platformId = $request->query('platform_id')) {
            $query->where('platform_id', $platformId);
        }

        if ($assigneeId = $request->query('assignee_id')) {
            $query->where('assignee_id', $assigneeId);
        }

        if ($search = $request->query('search')) {
            $query->whereHas('content', fn ($q) => $q->where('title', 'like', "%{$search}%"));
        }

        return response()->json([
            'data' => $query->paginate($request->integer('per_page', 25))->items(),
        ]);
    }

    public function show(CmsContentPlatformPost $platformPost): JsonResponse
    {
        return response()->json([
            'data' => $platformPost->load(['content:id,title,tiktok_url', 'platform', 'assignee:id,full_name,profile_photo']),
        ]);
    }

    public function update(UpdatePlatformPostRequest $request, CmsContentPlatformPost $platformPost): JsonResponse
    {
        $platformPost->update($request->validated());

        return response()->json([
            'data' => $platformPost->fresh()->load(['content:id,title', 'platform', 'assignee:id,full_name,profile_photo']),
        ]);
    }

    public function updateStats(UpdatePlatformPostStatsRequest $request, CmsContentPlatformPost $platformPost): JsonResponse
    {
        $existing = $platformPost->stats ?? [];
        $merged = array_merge($existing, $request->validated(), ['last_synced_at' => now()->toIso8601String()]);

        $platformPost->update(['stats' => $merged]);

        return response()->json([
            'data' => $platformPost->fresh(),
        ]);
    }

    public function bulkAssign(BulkAssignPlatformPostsRequest $request): JsonResponse
    {
        $validated = $request->validated();

        CmsContentPlatformPost::whereIn('id', $validated['post_ids'])
            ->update(['assignee_id' => $validated['assignee_id'] ?? null]);

        return response()->json(['updated' => count($validated['post_ids'])]);
    }
}
