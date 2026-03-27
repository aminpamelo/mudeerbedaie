<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\StoreAssetCategoryRequest;
use App\Models\AssetCategory;
use Illuminate\Http\JsonResponse;

class HrAssetCategoryController extends Controller
{
    /**
     * List all asset categories.
     */
    public function index(): JsonResponse
    {
        $categories = AssetCategory::query()
            ->ordered()
            ->get();

        return response()->json(['data' => $categories]);
    }

    /**
     * Create a new asset category.
     */
    public function store(StoreAssetCategoryRequest $request): JsonResponse
    {
        $category = AssetCategory::create($request->validated());

        return response()->json([
            'data' => $category,
            'message' => 'Asset category created successfully.',
        ], 201);
    }

    /**
     * Update an asset category.
     */
    public function update(StoreAssetCategoryRequest $request, AssetCategory $category): JsonResponse
    {
        $category->update($request->validated());

        return response()->json([
            'data' => $category->fresh(),
            'message' => 'Asset category updated successfully.',
        ]);
    }

    /**
     * Delete an asset category.
     */
    public function destroy(AssetCategory $category): JsonResponse
    {
        if ($category->assets()->exists()) {
            return response()->json([
                'message' => 'Cannot delete asset category that has existing assets.',
            ], 422);
        }

        $category->delete();

        return response()->json(['message' => 'Asset category deleted successfully.']);
    }
}
