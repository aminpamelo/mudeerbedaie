<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Funnel\StoreFunnelCategoryRequest;
use App\Http\Requests\Funnel\UpdateFunnelCategoryRequest;
use App\Models\FunnelCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FunnelCategoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $categories = FunnelCategory::query()
            ->forUser($request->user()->id)
            ->withCount('funnels')
            ->ordered()
            ->get()
            ->map(fn (FunnelCategory $c) => $this->transform($c));

        return response()->json(['data' => $categories]);
    }

    public function store(StoreFunnelCategoryRequest $request): JsonResponse
    {
        $category = FunnelCategory::create([
            'user_id' => $request->user()->id,
            'name' => $request->input('name'),
            'color' => $request->input('color') ?: 'zinc',
            'sort_order' => $request->input('sort_order', 0),
        ]);

        return response()->json([
            'message' => 'Category created successfully',
            'data' => $this->transform($category->loadCount('funnels')),
        ], 201);
    }

    public function update(UpdateFunnelCategoryRequest $request, FunnelCategory $category): JsonResponse
    {
        abort_unless($category->user_id === $request->user()->id, 403);

        $category->fill($request->only(['name', 'color', 'sort_order']));
        $category->save();

        return response()->json([
            'message' => 'Category updated successfully',
            'data' => $this->transform($category->fresh()->loadCount('funnels')),
        ]);
    }

    public function destroy(Request $request, FunnelCategory $category): JsonResponse
    {
        abort_unless($category->user_id === $request->user()->id, 403);

        // Funnels keep working; their funnel_category_id is set to null via FK.
        $category->delete();

        return response()->json(['message' => 'Category deleted successfully']);
    }

    public function reorder(Request $request): JsonResponse
    {
        $data = $request->validate([
            'categories' => ['required', 'array'],
            'categories.*.id' => ['required', 'integer', 'exists:funnel_categories,id'],
            'categories.*.sort_order' => ['required', 'integer', 'min:0'],
        ]);

        $userId = $request->user()->id;

        foreach ($data['categories'] as $item) {
            FunnelCategory::where('id', $item['id'])
                ->where('user_id', $userId)
                ->update(['sort_order' => $item['sort_order']]);
        }

        return response()->json(['message' => 'Categories reordered successfully']);
    }

    /**
     * @return array<string, mixed>
     */
    protected function transform(FunnelCategory $category): array
    {
        return [
            'id' => $category->id,
            'name' => $category->name,
            'slug' => $category->slug,
            'color' => $category->color,
            'sort_order' => $category->sort_order,
            'funnels_count' => $category->funnels_count ?? 0,
            'created_at' => $category->created_at?->toIso8601String(),
            'updated_at' => $category->updated_at?->toIso8601String(),
        ];
    }
}
