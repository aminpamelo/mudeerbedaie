<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tag;
use App\Services\CRM\TagService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TagController extends Controller
{
    public function __construct(
        private TagService $tagService
    ) {}

    public function index(): JsonResponse
    {
        $tags = Tag::withCount('students')
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $tags,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'color' => 'nullable|string|max:7',
            'description' => 'nullable|string|max:500',
            'type' => 'nullable|in:manual,auto,system',
        ]);

        $tag = $this->tagService->createTag(
            $validated['name'],
            $validated['color'] ?? '#6366f1',
            $validated['description'] ?? null,
            $validated['type'] ?? 'manual'
        );

        return response()->json([
            'data' => $tag,
            'message' => 'Tag created successfully',
        ], 201);
    }

    public function show(Tag $tag): JsonResponse
    {
        $tag->loadCount('students');

        return response()->json([
            'data' => $tag,
        ]);
    }

    public function update(Request $request, Tag $tag): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:100',
            'color' => 'nullable|string|max:7',
            'description' => 'nullable|string|max:500',
        ]);

        $tag = $this->tagService->updateTag($tag, $validated);

        return response()->json([
            'data' => $tag,
            'message' => 'Tag updated successfully',
        ]);
    }

    public function destroy(Tag $tag): JsonResponse
    {
        if ($tag->students()->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete tag that is assigned to students',
            ], 422);
        }

        $this->tagService->deleteTag($tag);

        return response()->json([
            'message' => 'Tag deleted successfully',
        ]);
    }

    public function stats(): JsonResponse
    {
        $stats = $this->tagService->getTagUsageStats();

        return response()->json([
            'data' => $stats,
        ]);
    }
}
