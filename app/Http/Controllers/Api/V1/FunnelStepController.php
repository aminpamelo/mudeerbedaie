<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Funnel\SaveStepContentRequest;
use App\Http\Requests\Funnel\StoreFunnelStepRequest;
use App\Http\Requests\Funnel\UpdateFunnelStepRequest;
use App\Http\Resources\FunnelStepResource;
use App\Models\Funnel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class FunnelStepController extends Controller
{
    /**
     * List steps for a funnel.
     */
    public function index(string $funnelUuid): JsonResponse
    {
        $funnel = Funnel::where('uuid', $funnelUuid)->firstOrFail();

        $steps = $funnel->steps()
            ->with(['draftContent', 'publishedContent', 'products', 'orderBumps'])
            ->orderBy('sort_order')
            ->get();

        return response()->json([
            'data' => FunnelStepResource::collection($steps),
        ]);
    }

    /**
     * Create a new step.
     */
    public function store(StoreFunnelStepRequest $request, string $funnelUuid): JsonResponse
    {
        $funnel = Funnel::where('uuid', $funnelUuid)->firstOrFail();
        $data = $request->validated();

        // Generate slug if not provided
        if (empty($data['slug'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        // Ensure unique slug within funnel
        $originalSlug = $data['slug'];
        $counter = 1;
        while ($funnel->steps()->where('slug', $data['slug'])->exists()) {
            $data['slug'] = "{$originalSlug}-{$counter}";
            $counter++;
        }

        // Get next sort order
        $maxSortOrder = $funnel->steps()->max('sort_order') ?? -1;
        $data['sort_order'] = $maxSortOrder + 1;

        $step = $funnel->steps()->create([
            'name' => $data['name'],
            'slug' => $data['slug'],
            'type' => $data['type'],
            'sort_order' => $data['sort_order'],
            'is_active' => true,
            'settings' => $data['settings'] ?? [],
        ]);

        // Create initial empty content
        $step->contents()->create([
            'content' => ['content' => [], 'root' => []],
            'version' => 1,
            'is_published' => false,
        ]);

        return response()->json([
            'message' => 'Step created successfully',
            'data' => new FunnelStepResource($step->load(['draftContent', 'products', 'orderBumps'])),
        ], 201);
    }

    /**
     * Get a single step.
     */
    public function show(string $funnelUuid, int $stepId): JsonResponse
    {
        $funnel = Funnel::where('uuid', $funnelUuid)->firstOrFail();
        $step = $funnel->steps()
            ->with(['draftContent', 'publishedContent', 'products', 'orderBumps'])
            ->findOrFail($stepId);

        return response()->json([
            'data' => new FunnelStepResource($step),
        ]);
    }

    /**
     * Update a step.
     */
    public function update(UpdateFunnelStepRequest $request, string $funnelUuid, int $stepId): JsonResponse
    {
        $funnel = Funnel::where('uuid', $funnelUuid)->firstOrFail();
        $step = $funnel->steps()->findOrFail($stepId);
        $data = $request->validated();

        // Ensure unique slug if being changed
        if (isset($data['slug']) && $data['slug'] !== $step->slug) {
            $originalSlug = $data['slug'];
            $counter = 1;
            while ($funnel->steps()->where('slug', $data['slug'])->where('id', '!=', $step->id)->exists()) {
                $data['slug'] = "{$originalSlug}-{$counter}";
                $counter++;
            }
        }

        $step->update($data);

        return response()->json([
            'message' => 'Step updated successfully',
            'data' => new FunnelStepResource($step->fresh(['draftContent', 'products', 'orderBumps'])),
        ]);
    }

    /**
     * Delete a step.
     */
    public function destroy(string $funnelUuid, int $stepId): JsonResponse
    {
        $funnel = Funnel::where('uuid', $funnelUuid)->firstOrFail();
        $step = $funnel->steps()->findOrFail($stepId);

        // Delete associated content
        $step->contents()->delete();
        $step->delete();

        // Reorder remaining steps
        $funnel->steps()
            ->where('sort_order', '>', $step->sort_order)
            ->decrement('sort_order');

        return response()->json([
            'message' => 'Step deleted successfully',
        ]);
    }

    /**
     * Duplicate a step.
     */
    public function duplicate(string $funnelUuid, int $stepId): JsonResponse
    {
        $funnel = Funnel::where('uuid', $funnelUuid)->firstOrFail();
        $step = $funnel->steps()
            ->with(['draftContent', 'products', 'orderBumps'])
            ->findOrFail($stepId);

        // Create duplicate step
        $newSlug = $step->slug.'-copy';
        $counter = 1;
        while ($funnel->steps()->where('slug', $newSlug)->exists()) {
            $newSlug = "{$step->slug}-copy-{$counter}";
            $counter++;
        }

        $maxSortOrder = $funnel->steps()->max('sort_order');

        $newStep = $funnel->steps()->create([
            'name' => $step->name.' (Copy)',
            'slug' => $newSlug,
            'type' => $step->type,
            'sort_order' => $maxSortOrder + 1,
            'is_active' => true,
            'settings' => $step->settings,
        ]);

        // Copy content
        if ($step->draftContent) {
            $newStep->contents()->create([
                'content' => $step->draftContent->content,
                'custom_css' => $step->draftContent->custom_css,
                'custom_js' => $step->draftContent->custom_js,
                'meta_title' => $step->draftContent->meta_title,
                'meta_description' => $step->draftContent->meta_description,
                'og_image' => $step->draftContent->og_image,
                'version' => 1,
                'is_published' => false,
            ]);
        }

        // Copy products
        foreach ($step->products as $product) {
            $newStep->products()->create($product->only([
                'product_id', 'course_id', 'type', 'name', 'description',
                'image_url', 'funnel_price', 'compare_at_price',
                'is_recurring', 'billing_interval', 'sort_order', 'is_active',
            ]));
        }

        // Copy order bumps
        foreach ($step->orderBumps as $bump) {
            $newStep->orderBumps()->create($bump->only([
                'product_id', 'name', 'headline', 'description', 'image_url',
                'price', 'compare_at_price', 'sort_order', 'is_active',
            ]));
        }

        return response()->json([
            'message' => 'Step duplicated successfully',
            'data' => new FunnelStepResource($newStep->load(['draftContent', 'products', 'orderBumps'])),
        ], 201);
    }

    /**
     * Reorder steps.
     */
    public function reorder(Request $request, string $funnelUuid): JsonResponse
    {
        $request->validate([
            'steps' => ['required', 'array'],
            'steps.*.id' => ['required', 'integer'],
            'steps.*.sort_order' => ['required', 'integer', 'min:0'],
        ]);

        $funnel = Funnel::where('uuid', $funnelUuid)->firstOrFail();

        foreach ($request->input('steps') as $stepData) {
            $funnel->steps()
                ->where('id', $stepData['id'])
                ->update(['sort_order' => $stepData['sort_order']]);
        }

        return response()->json([
            'message' => 'Steps reordered successfully',
        ]);
    }

    /**
     * Get step content.
     */
    public function getContent(string $funnelUuid, int $stepId): JsonResponse
    {
        $funnel = Funnel::where('uuid', $funnelUuid)->firstOrFail();
        $step = $funnel->steps()->with(['draftContent', 'publishedContent'])->findOrFail($stepId);

        $content = $step->draftContent ?? $step->publishedContent;

        return response()->json([
            'data' => [
                'content' => $content?->content ?? ['content' => [], 'root' => []],
                'custom_css' => $content?->custom_css,
                'custom_js' => $content?->custom_js,
                'meta_title' => $content?->meta_title,
                'meta_description' => $content?->meta_description,
                'og_image' => $content?->og_image,
                'version' => $content?->version ?? 0,
                'is_published' => $content?->is_published ?? false,
            ],
        ]);
    }

    /**
     * Save step content (Puck data).
     */
    public function saveContent(SaveStepContentRequest $request, string $funnelUuid, int $stepId): JsonResponse
    {
        $funnel = Funnel::where('uuid', $funnelUuid)->firstOrFail();
        $step = $funnel->steps()->findOrFail($stepId);
        $data = $request->validated();

        // Get or create draft content
        $draftContent = $step->draftContent;

        if ($draftContent) {
            // Update existing draft
            $draftContent->update([
                'content' => $data['content'],
                'custom_css' => $data['custom_css'] ?? $draftContent->custom_css,
                'custom_js' => $data['custom_js'] ?? $draftContent->custom_js,
                'meta_title' => $data['meta_title'] ?? $draftContent->meta_title,
                'meta_description' => $data['meta_description'] ?? $draftContent->meta_description,
                'og_image' => $data['og_image'] ?? $draftContent->og_image,
                'version' => $draftContent->version + 1,
            ]);
        } else {
            // Create new draft
            $draftContent = $step->contents()->create([
                'content' => $data['content'],
                'custom_css' => $data['custom_css'] ?? null,
                'custom_js' => $data['custom_js'] ?? null,
                'meta_title' => $data['meta_title'] ?? null,
                'meta_description' => $data['meta_description'] ?? null,
                'og_image' => $data['og_image'] ?? null,
                'version' => 1,
                'is_published' => false,
            ]);
        }

        return response()->json([
            'message' => 'Content saved successfully',
            'data' => [
                'version' => $draftContent->version,
                'updated_at' => $draftContent->updated_at->toIso8601String(),
            ],
        ]);
    }

    /**
     * Publish step content.
     */
    public function publishContent(string $funnelUuid, int $stepId): JsonResponse
    {
        $funnel = Funnel::where('uuid', $funnelUuid)->firstOrFail();
        $step = $funnel->steps()->with('draftContent')->findOrFail($stepId);

        if (! $step->draftContent) {
            return response()->json([
                'message' => 'No draft content to publish',
            ], 422);
        }

        // Publish the draft
        $step->draftContent->publish();

        return response()->json([
            'message' => 'Content published successfully',
        ]);
    }
}
