<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\Tag;
use App\Services\CRM\TagService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudentTagController extends Controller
{
    public function __construct(
        private TagService $tagService
    ) {}

    public function index(Student $student): JsonResponse
    {
        $tags = $this->tagService->getStudentTags($student);

        return response()->json([
            'data' => $tags,
        ]);
    }

    public function store(Request $request, Student $student): JsonResponse
    {
        $validated = $request->validate([
            'tag_id' => 'required|exists:tags,id',
            'source' => 'nullable|string|max:50',
        ]);

        $tag = Tag::findOrFail($validated['tag_id']);

        $added = $this->tagService->addTagToStudent(
            $student,
            $tag,
            $validated['source'] ?? 'manual'
        );

        if (! $added) {
            return response()->json([
                'message' => 'Tag is already assigned to this student',
            ], 422);
        }

        return response()->json([
            'message' => 'Tag added successfully',
        ], 201);
    }

    public function destroy(Student $student, Tag $tag): JsonResponse
    {
        $removed = $this->tagService->removeTagFromStudent($student, $tag);

        if (! $removed) {
            return response()->json([
                'message' => 'Tag was not assigned to this student',
            ], 404);
        }

        return response()->json([
            'message' => 'Tag removed successfully',
        ]);
    }

    public function sync(Request $request, Student $student): JsonResponse
    {
        $validated = $request->validate([
            'tag_ids' => 'required|array',
            'tag_ids.*' => 'exists:tags,id',
            'source' => 'nullable|string|max:50',
        ]);

        $this->tagService->syncStudentTags(
            $student,
            $validated['tag_ids'],
            $validated['source'] ?? 'manual'
        );

        $tags = $this->tagService->getStudentTags($student);

        return response()->json([
            'data' => $tags,
            'message' => 'Tags synced successfully',
        ]);
    }
}
