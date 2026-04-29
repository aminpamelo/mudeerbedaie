<?php

namespace App\Http\Controllers\Api\Cms;

use App\Http\Controllers\Controller;
use App\Models\CmsContentPlatformPost;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CmsContentPlatformPostController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = CmsContentPlatformPost::query()
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
}
