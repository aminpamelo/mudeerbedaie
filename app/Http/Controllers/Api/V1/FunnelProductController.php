<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Funnel;
use App\Models\FunnelOrderBump;
use App\Models\FunnelProduct;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FunnelProductController extends Controller
{
    /**
     * List all products across all steps in a funnel.
     */
    public function index(string $funnelUuid): JsonResponse
    {
        $funnel = Funnel::where('uuid', $funnelUuid)->firstOrFail();

        $products = FunnelProduct::whereHas('step', function ($query) use ($funnel) {
            $query->where('funnel_id', $funnel->id);
        })
            ->with(['step:id,name,type,sort_order', 'product:id,name,base_price,status', 'course:id,name,status'])
            ->orderBy('funnel_step_id')
            ->orderBy('sort_order')
            ->get();

        return response()->json([
            'data' => $products->map(fn ($p) => $this->formatProduct($p)),
        ]);
    }

    /**
     * Attach a product to a step.
     */
    public function store(Request $request, string $funnelUuid, int $stepId): JsonResponse
    {
        $request->validate([
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
            'course_id' => ['nullable', 'integer', 'exists:courses,id'],
            'type' => ['required', 'string', 'in:main,upsell,downsell'],
            'name' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'image_url' => ['nullable', 'string', 'url', 'max:2048'],
            'funnel_price' => ['required', 'numeric', 'min:0'],
            'compare_at_price' => ['nullable', 'numeric', 'min:0'],
            'is_recurring' => ['nullable', 'boolean'],
            'billing_interval' => ['nullable', 'string', 'in:monthly,yearly,weekly'],
        ]);

        // Validate that either product_id or course_id is provided, but not both
        if (! $request->product_id && ! $request->course_id) {
            return response()->json([
                'message' => 'Either product_id or course_id must be provided',
            ], 422);
        }

        if ($request->product_id && $request->course_id) {
            return response()->json([
                'message' => 'Only one of product_id or course_id should be provided',
            ], 422);
        }

        $funnel = Funnel::where('uuid', $funnelUuid)->firstOrFail();
        $step = $funnel->steps()->findOrFail($stepId);

        // Get next sort order
        $maxSortOrder = $step->products()->max('sort_order') ?? -1;

        // Auto-populate name if not provided
        $name = $request->name;
        if (! $name) {
            if ($request->product_id) {
                $product = Product::find($request->product_id);
                $name = $product?->name;
            } elseif ($request->course_id) {
                $course = Course::find($request->course_id);
                $name = $course?->name;
            }
        }

        $funnelProduct = $step->products()->create([
            'product_id' => $request->product_id,
            'course_id' => $request->course_id,
            'type' => $request->type,
            'name' => $name,
            'description' => $request->description,
            'image_url' => $request->image_url,
            'funnel_price' => $request->funnel_price,
            'compare_at_price' => $request->compare_at_price,
            'is_recurring' => $request->is_recurring ?? false,
            'billing_interval' => $request->billing_interval,
            'sort_order' => $maxSortOrder + 1,
        ]);

        return response()->json([
            'message' => 'Product added to step successfully',
            'data' => $this->formatProduct($funnelProduct->load(['step', 'product', 'course'])),
        ], 201);
    }

    /**
     * Update a funnel product.
     */
    public function update(Request $request, string $funnelUuid, int $stepId, int $productId): JsonResponse
    {
        $request->validate([
            'type' => ['nullable', 'string', 'in:main,upsell,downsell'],
            'name' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'image_url' => ['nullable', 'string', 'url', 'max:2048'],
            'funnel_price' => ['nullable', 'numeric', 'min:0'],
            'compare_at_price' => ['nullable', 'numeric', 'min:0'],
            'is_recurring' => ['nullable', 'boolean'],
            'billing_interval' => ['nullable', 'string', 'in:monthly,yearly,weekly'],
        ]);

        $funnel = Funnel::where('uuid', $funnelUuid)->firstOrFail();
        $step = $funnel->steps()->findOrFail($stepId);
        $funnelProduct = $step->products()->findOrFail($productId);

        $funnelProduct->update($request->only([
            'type',
            'name',
            'description',
            'image_url',
            'funnel_price',
            'compare_at_price',
            'is_recurring',
            'billing_interval',
        ]));

        return response()->json([
            'message' => 'Product updated successfully',
            'data' => $this->formatProduct($funnelProduct->fresh(['step', 'product', 'course'])),
        ]);
    }

    /**
     * Remove a product from a step.
     */
    public function destroy(string $funnelUuid, int $stepId, int $productId): JsonResponse
    {
        $funnel = Funnel::where('uuid', $funnelUuid)->firstOrFail();
        $step = $funnel->steps()->findOrFail($stepId);
        $funnelProduct = $step->products()->findOrFail($productId);

        $sortOrder = $funnelProduct->sort_order;
        $funnelProduct->delete();

        // Reorder remaining products
        $step->products()
            ->where('sort_order', '>', $sortOrder)
            ->decrement('sort_order');

        return response()->json([
            'message' => 'Product removed from step successfully',
        ]);
    }

    /**
     * Reorder products within a step.
     */
    public function reorder(Request $request, string $funnelUuid, int $stepId): JsonResponse
    {
        $request->validate([
            'products' => ['required', 'array'],
            'products.*.id' => ['required', 'integer'],
            'products.*.sort_order' => ['required', 'integer', 'min:0'],
        ]);

        $funnel = Funnel::where('uuid', $funnelUuid)->firstOrFail();
        $step = $funnel->steps()->findOrFail($stepId);

        foreach ($request->input('products') as $productData) {
            $step->products()
                ->where('id', $productData['id'])
                ->update(['sort_order' => $productData['sort_order']]);
        }

        return response()->json([
            'message' => 'Products reordered successfully',
        ]);
    }

    /**
     * Search available products.
     */
    public function searchProducts(Request $request): JsonResponse
    {
        $search = $request->input('q', '');
        $limit = min($request->input('limit', 20), 50);

        $products = Product::query()
            ->active()
            ->when($search, fn ($q) => $q->search($search))
            ->with(['primaryImage', 'category:id,name'])
            ->orderBy('name')
            ->limit($limit)
            ->get();

        return response()->json([
            'data' => $products->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'sku' => $p->sku,
                'price' => $p->base_price,
                'formatted_price' => $p->formatted_price,
                'image_url' => $p->primaryImage?->url,
                'category' => $p->category?->name,
                'type' => $p->type,
                'status' => $p->status,
            ]),
        ]);
    }

    /**
     * Search available courses.
     */
    public function searchCourses(Request $request): JsonResponse
    {
        $search = $request->input('q', '');
        $limit = min($request->input('limit', 20), 50);

        $courses = Course::query()
            ->where('status', 'active')
            ->when($search, fn ($q) => $q->where('name', 'like', "%{$search}%"))
            ->with('feeSettings')
            ->orderBy('name')
            ->limit($limit)
            ->get();

        return response()->json([
            'data' => $courses->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'description' => $c->description,
                'price' => $c->feeSettings?->fee_amount ?? 0,
                'formatted_price' => $c->formatted_fee,
                'status' => $c->status,
                'enrollment_count' => $c->enrollment_count,
            ]),
        ]);
    }

    /**
     * List order bumps for a step.
     */
    public function indexOrderBumps(string $funnelUuid, int $stepId): JsonResponse
    {
        $funnel = Funnel::where('uuid', $funnelUuid)->firstOrFail();
        $step = $funnel->steps()->findOrFail($stepId);

        $orderBumps = $step->orderBumps()
            ->with(['product:id,name,base_price', 'course:id,name'])
            ->orderBy('sort_order')
            ->get();

        return response()->json([
            'data' => $orderBumps->map(fn ($b) => $this->formatOrderBump($b)),
        ]);
    }

    /**
     * Add an order bump to a step.
     */
    public function storeOrderBump(Request $request, string $funnelUuid, int $stepId): JsonResponse
    {
        $request->validate([
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
            'course_id' => ['nullable', 'integer', 'exists:courses,id'],
            'name' => ['nullable', 'string', 'max:255'],
            'headline' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'image_url' => ['nullable', 'string', 'url', 'max:2048'],
            'price' => ['required', 'numeric', 'min:0'],
            'compare_at_price' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            'is_checked_by_default' => ['nullable', 'boolean'],
        ]);

        $funnel = Funnel::where('uuid', $funnelUuid)->firstOrFail();
        $step = $funnel->steps()->findOrFail($stepId);

        // Get next sort order
        $maxSortOrder = $step->orderBumps()->max('sort_order') ?? -1;

        // Auto-populate name if not provided
        $name = $request->name;
        if (! $name) {
            if ($request->product_id) {
                $product = Product::find($request->product_id);
                $name = $product?->name;
            } elseif ($request->course_id) {
                $course = Course::find($request->course_id);
                $name = $course?->name;
            }
        }

        $orderBump = $step->orderBumps()->create([
            'product_id' => $request->product_id,
            'course_id' => $request->course_id,
            'name' => $name,
            'headline' => $request->headline,
            'description' => $request->description,
            'image_url' => $request->image_url,
            'price' => $request->price,
            'compare_at_price' => $request->compare_at_price,
            'is_active' => $request->is_active ?? true,
            'is_checked_by_default' => $request->is_checked_by_default ?? false,
            'sort_order' => $maxSortOrder + 1,
        ]);

        return response()->json([
            'message' => 'Order bump added successfully',
            'data' => $this->formatOrderBump($orderBump->load(['product', 'course'])),
        ], 201);
    }

    /**
     * Update an order bump.
     */
    public function updateOrderBump(Request $request, string $funnelUuid, int $stepId, int $bumpId): JsonResponse
    {
        $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'headline' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'image_url' => ['nullable', 'string', 'url', 'max:2048'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'compare_at_price' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            'is_checked_by_default' => ['nullable', 'boolean'],
        ]);

        $funnel = Funnel::where('uuid', $funnelUuid)->firstOrFail();
        $step = $funnel->steps()->findOrFail($stepId);
        $orderBump = $step->orderBumps()->findOrFail($bumpId);

        $orderBump->update($request->only([
            'name',
            'headline',
            'description',
            'image_url',
            'price',
            'compare_at_price',
            'is_active',
            'is_checked_by_default',
        ]));

        return response()->json([
            'message' => 'Order bump updated successfully',
            'data' => $this->formatOrderBump($orderBump->fresh(['product', 'course'])),
        ]);
    }

    /**
     * Remove an order bump.
     */
    public function destroyOrderBump(string $funnelUuid, int $stepId, int $bumpId): JsonResponse
    {
        $funnel = Funnel::where('uuid', $funnelUuid)->firstOrFail();
        $step = $funnel->steps()->findOrFail($stepId);
        $orderBump = $step->orderBumps()->findOrFail($bumpId);

        $sortOrder = $orderBump->sort_order;
        $orderBump->delete();

        // Reorder remaining bumps
        $step->orderBumps()
            ->where('sort_order', '>', $sortOrder)
            ->decrement('sort_order');

        return response()->json([
            'message' => 'Order bump removed successfully',
        ]);
    }

    /**
     * Format a funnel product for response.
     */
    private function formatProduct(FunnelProduct $product): array
    {
        return [
            'id' => $product->id,
            'funnel_step_id' => $product->funnel_step_id,
            'product_id' => $product->product_id,
            'course_id' => $product->course_id,
            'type' => $product->type,
            'name' => $product->getDisplayName(),
            'description' => $product->getDisplayDescription(),
            'image_url' => $product->getImageUrl(),
            'funnel_price' => $product->funnel_price,
            'compare_at_price' => $product->compare_at_price,
            'formatted_price' => $product->getFormattedPrice(),
            'formatted_compare_at_price' => $product->compare_at_price ? $product->getFormattedCompareAtPrice() : null,
            'has_discount' => $product->hasDiscount(),
            'discount_percentage' => $product->getDiscountPercentage(),
            'is_recurring' => $product->is_recurring,
            'billing_interval' => $product->billing_interval,
            'sort_order' => $product->sort_order,
            'is_product' => $product->isProduct(),
            'is_course' => $product->isCourse(),
            'step' => $product->relationLoaded('step') ? [
                'id' => $product->step->id,
                'name' => $product->step->name,
                'type' => $product->step->type,
                'sort_order' => $product->step->sort_order,
            ] : null,
            'source_product' => $product->product ? [
                'id' => $product->product->id,
                'name' => $product->product->name,
                'base_price' => $product->product->base_price,
                'status' => $product->product->status,
            ] : null,
            'source_course' => $product->course ? [
                'id' => $product->course->id,
                'name' => $product->course->name,
                'status' => $product->course->status,
            ] : null,
        ];
    }

    /**
     * Format an order bump for response.
     */
    private function formatOrderBump(FunnelOrderBump $bump): array
    {
        return [
            'id' => $bump->id,
            'funnel_step_id' => $bump->funnel_step_id,
            'product_id' => $bump->product_id,
            'course_id' => $bump->course_id,
            'name' => $bump->name,
            'headline' => $bump->headline,
            'description' => $bump->description,
            'image_url' => $bump->image_url,
            'price' => $bump->price,
            'compare_at_price' => $bump->compare_at_price,
            'formatted_price' => 'RM '.number_format($bump->price, 2),
            'formatted_compare_at_price' => $bump->compare_at_price ? 'RM '.number_format($bump->compare_at_price, 2) : null,
            'has_discount' => $bump->compare_at_price && $bump->compare_at_price > $bump->price,
            'is_active' => $bump->is_active,
            'is_checked_by_default' => $bump->is_checked_by_default ?? false,
            'sort_order' => $bump->sort_order,
            'source_product' => $bump->product ? [
                'id' => $bump->product->id,
                'name' => $bump->product->name,
                'base_price' => $bump->product->base_price,
            ] : null,
            'source_course' => $bump->course ? [
                'id' => $bump->course->id,
                'name' => $bump->course->name,
            ] : null,
        ];
    }
}
