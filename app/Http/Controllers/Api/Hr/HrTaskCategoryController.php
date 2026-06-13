<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\StoreTaskCategoryRequest;
use App\Models\TaskCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HrTaskCategoryController extends Controller
{
    /**
     * List task categories, optionally with their task counts.
     */
    public function index(Request $request): JsonResponse
    {
        $query = TaskCategory::query()->ordered();

        if ($request->boolean('active_only')) {
            $query->active();
        }

        if ($request->boolean('with_counts')) {
            $query->withCount('tasks');
        }

        return response()->json(['data' => $query->get()]);
    }

    /**
     * Create a new task category.
     */
    public function store(StoreTaskCategoryRequest $request): JsonResponse
    {
        $category = TaskCategory::create($request->validated());

        return response()->json([
            'data' => $category,
            'message' => 'Task category created successfully.',
        ], 201);
    }

    /**
     * Update a task category.
     */
    public function update(StoreTaskCategoryRequest $request, TaskCategory $category): JsonResponse
    {
        $category->update($request->validated());

        return response()->json([
            'data' => $category->fresh(),
            'message' => 'Task category updated successfully.',
        ]);
    }

    /**
     * Delete a task category. Existing tasks keep their data but become uncategorised.
     */
    public function destroy(Request $request, TaskCategory $category): JsonResponse
    {
        abort_unless($request->user() && $request->user()->isAdmin(), 403);

        $category->delete();

        return response()->json(['message' => 'Task category deleted successfully.']);
    }
}
