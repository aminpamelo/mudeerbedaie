<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Funnel\StoreFunnelRequest;
use App\Http\Requests\Funnel\UpdateFunnelRequest;
use App\Http\Resources\FunnelResource;
use App\Models\Funnel;
use App\Models\FunnelTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;

class FunnelController extends Controller
{
    /**
     * List all funnels with optional filtering.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Funnel::query()
            ->with(['steps' => fn ($q) => $q->orderBy('sort_order')])
            ->withCount('steps')
            ->latest();

        // Search filter
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Status filter
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        // Type filter
        if ($type = $request->input('type')) {
            $query->where('type', $type);
        }

        $funnels = $query->paginate($request->input('per_page', 15));

        return FunnelResource::collection($funnels);
    }

    /**
     * Create a new funnel.
     */
    public function store(StoreFunnelRequest $request): JsonResponse
    {
        $data = $request->validated();

        // Generate slug from name
        $data['slug'] = Str::slug($data['name']);

        // Ensure unique slug
        $originalSlug = $data['slug'];
        $counter = 1;
        while (Funnel::where('slug', $data['slug'])->exists()) {
            $data['slug'] = "{$originalSlug}-{$counter}";
            $counter++;
        }

        // Create funnel from template if specified
        if (! empty($data['template_id'])) {
            $template = FunnelTemplate::findOrFail($data['template_id']);
            $funnel = $this->createFromTemplate($template, $data);
        } else {
            $funnel = Funnel::create([
                'user_id' => auth()->id(),
                'name' => $data['name'],
                'slug' => $data['slug'],
                'description' => $data['description'] ?? null,
                'type' => $data['type'] ?? 'sales',
                'status' => 'draft',
                'settings' => $data['settings'] ?? [],
            ]);

            // Create default landing step
            $funnel->steps()->create([
                'name' => 'Landing Page',
                'slug' => 'landing',
                'type' => 'landing',
                'sort_order' => 0,
                'is_active' => true,
            ]);
        }

        return response()->json([
            'message' => 'Funnel created successfully',
            'data' => new FunnelResource($funnel->load('steps')),
        ], 201);
    }

    /**
     * Get a single funnel.
     */
    public function show(string $uuid): JsonResponse
    {
        $funnel = Funnel::where('uuid', $uuid)
            ->with([
                'steps' => fn ($q) => $q->orderBy('sort_order'),
                'steps.products',
                'steps.orderBumps',
            ])
            ->withCount(['sessions', 'orders'])
            ->firstOrFail();

        return response()->json([
            'data' => new FunnelResource($funnel),
        ]);
    }

    /**
     * Update a funnel.
     */
    public function update(UpdateFunnelRequest $request, string $uuid): JsonResponse
    {
        $funnel = Funnel::where('uuid', $uuid)->firstOrFail();
        $data = $request->validated();

        $funnel->update($data);

        return response()->json([
            'message' => 'Funnel updated successfully',
            'data' => new FunnelResource($funnel->fresh(['steps'])),
        ]);
    }

    /**
     * Delete a funnel (soft delete).
     */
    public function destroy(string $uuid): JsonResponse
    {
        $funnel = Funnel::where('uuid', $uuid)->firstOrFail();
        $funnel->delete();

        return response()->json([
            'message' => 'Funnel deleted successfully',
        ]);
    }

    /**
     * Duplicate a funnel.
     */
    public function duplicate(string $uuid): JsonResponse
    {
        $funnel = Funnel::where('uuid', $uuid)
            ->with(['steps', 'steps.products', 'steps.orderBumps', 'steps.draftContent'])
            ->firstOrFail();

        $newFunnel = $funnel->duplicate();

        return response()->json([
            'message' => 'Funnel duplicated successfully',
            'data' => new FunnelResource($newFunnel->load('steps')),
        ], 201);
    }

    /**
     * Publish a funnel.
     */
    public function publish(string $uuid): JsonResponse
    {
        $funnel = Funnel::where('uuid', $uuid)->firstOrFail();
        $funnel->publish();

        return response()->json([
            'message' => 'Funnel published successfully',
            'data' => new FunnelResource($funnel->fresh()),
        ]);
    }

    /**
     * Unpublish a funnel.
     */
    public function unpublish(string $uuid): JsonResponse
    {
        $funnel = Funnel::where('uuid', $uuid)->firstOrFail();
        $funnel->unpublish();

        return response()->json([
            'message' => 'Funnel unpublished successfully',
            'data' => new FunnelResource($funnel->fresh()),
        ]);
    }

    /**
     * Get funnel analytics.
     */
    public function analytics(Request $request, string $uuid): JsonResponse
    {
        $funnel = Funnel::where('uuid', $uuid)
            ->with(['steps' => fn ($q) => $q->orderBy('sort_order')])
            ->firstOrFail();

        $period = $request->input('period', '7d');
        $startDate = match ($period) {
            '24h' => now()->subDay(),
            '7d' => now()->subDays(7),
            '30d' => now()->subDays(30),
            '90d' => now()->subDays(90),
            default => now()->subDays(7),
        };

        // Get overall funnel analytics (no step filter)
        $funnelAnalytics = $funnel->analytics()
            ->whereNull('funnel_step_id')
            ->where('date', '>=', $startDate->toDateString())
            ->get();

        // Get step-by-step analytics
        $stepAnalytics = $funnel->analytics()
            ->whereNotNull('funnel_step_id')
            ->where('date', '>=', $startDate->toDateString())
            ->get()
            ->groupBy('funnel_step_id');

        // Build summary
        $summary = [
            'total_visitors' => $funnelAnalytics->sum('unique_visitors'),
            'total_pageviews' => $funnelAnalytics->sum('pageviews'),
            'total_conversions' => $funnelAnalytics->sum('conversions'),
            'total_revenue' => (float) $funnelAnalytics->sum('revenue'),
            'conversion_rate' => $funnelAnalytics->sum('unique_visitors') > 0
                ? round(($funnelAnalytics->sum('conversions') / $funnelAnalytics->sum('unique_visitors')) * 100, 2)
                : 0,
            'avg_time_on_page' => round($funnelAnalytics->avg('avg_time_seconds') ?? 0),
            'bounce_rate' => $funnelAnalytics->sum('pageviews') > 0
                ? round(($funnelAnalytics->sum('bounce_count') / $funnelAnalytics->sum('pageviews')) * 100, 2)
                : 0,
        ];

        // Build step stats
        $steps = $funnel->steps->map(function ($step) use ($stepAnalytics) {
            $stats = $stepAnalytics->get($step->id, collect());

            $visitors = $stats->sum('unique_visitors');
            $conversions = $stats->sum('conversions');

            return [
                'id' => $step->id,
                'name' => $step->name,
                'type' => $step->type,
                'visitors' => $visitors,
                'pageviews' => $stats->sum('pageviews'),
                'conversions' => $conversions,
                'revenue' => (float) $stats->sum('revenue'),
                'conversion_rate' => $visitors > 0
                    ? round(($conversions / $visitors) * 100, 2)
                    : 0,
                'bounce_rate' => $stats->sum('pageviews') > 0
                    ? round(($stats->sum('bounce_count') / $stats->sum('pageviews')) * 100, 2)
                    : 0,
            ];
        });

        // Build time series data
        $timeseries = $funnelAnalytics
            ->sortBy('date')
            ->map(fn ($record) => [
                'date' => $record->date->format('Y-m-d'),
                'label' => $record->date->format('M d'),
                'visitors' => $record->unique_visitors,
                'pageviews' => $record->pageviews,
                'conversions' => $record->conversions,
                'revenue' => (float) $record->revenue,
            ])
            ->values();

        return response()->json([
            'data' => [
                'summary' => $summary,
                'steps' => $steps,
                'timeseries' => $timeseries,
            ],
        ]);
    }

    /**
     * Create funnel from template.
     */
    protected function createFromTemplate(FunnelTemplate $template, array $data): Funnel
    {
        $funnel = Funnel::create([
            'user_id' => auth()->id(),
            'name' => $data['name'],
            'slug' => $data['slug'],
            'description' => $data['description'] ?? $template->description,
            'type' => $template->type,
            'status' => 'draft',
            'settings' => array_merge($template->default_settings ?? [], $data['settings'] ?? []),
            'template_id' => $template->id,
        ]);

        // Copy steps from template
        foreach ($template->template_data['steps'] ?? [] as $index => $stepData) {
            $step = $funnel->steps()->create([
                'name' => $stepData['name'],
                'slug' => $stepData['slug'] ?? Str::slug($stepData['name']),
                'type' => $stepData['type'],
                'sort_order' => $index,
                'is_active' => true,
                'settings' => $stepData['settings'] ?? [],
            ]);

            // Create content for step
            if (! empty($stepData['content'])) {
                $step->contents()->create([
                    'content' => $stepData['content'],
                    'version' => 1,
                    'is_published' => false,
                ]);
            }
        }

        // Increment template usage
        $template->increment('usage_count');

        return $funnel;
    }
}
