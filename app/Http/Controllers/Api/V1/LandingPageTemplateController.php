<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Funnel\LandingLibrary\SaveStepAsTemplateRequest;
use App\Http\Requests\Funnel\LandingLibrary\StoreLandingTemplateRequest;
use App\Http\Requests\Funnel\LandingLibrary\UpdateLandingTemplateRequest;
use App\Models\Funnel;
use App\Models\FunnelOrder;
use App\Models\FunnelSession;
use App\Models\FunnelStep;
use App\Models\LandingPageTemplate;
use App\Services\Funnel\LandingPageTemplateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Funnel Studio Landing Library — reusable landing page templates and which
 * funnels use them. Everyone in the builder can browse, save their own
 * pages and apply templates to funnels they can edit; admin/employee (or
 * the template's creator) manage templates; admin/employee run the import.
 */
class LandingPageTemplateController extends Controller
{
    public function __construct(protected LandingPageTemplateService $templates) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $templates = LandingPageTemplate::query()
            ->with('creator:id,name')
            ->when(! $request->boolean('include_inactive'), fn ($q) => $q->where('is_active', true))
            ->latest('id')
            ->get();

        $steps = FunnelStep::query()
            ->whereIn('landing_page_template_id', $templates->pluck('id'))
            ->with('funnel:id,uuid,name,status,user_id')
            ->get(['id', 'funnel_id', 'name', 'type', 'landing_page_template_id'])
            ->filter(fn (FunnelStep $step) => $step->funnel !== null);

        $funnelIds = $steps->pluck('funnel_id')->unique()->values();
        $since = now()->subDays(30)->startOfDay();

        $sessions = FunnelSession::query()
            ->whereIn('funnel_id', $funnelIds)
            ->where('created_at', '>=', $since)
            ->selectRaw('funnel_id, COUNT(*) as sessions')
            ->groupBy('funnel_id')
            ->pluck('sessions', 'funnel_id');

        $orders = FunnelOrder::query()
            ->whereIn('funnel_id', $funnelIds)
            ->where('created_at', '>=', $since)
            ->selectRaw('funnel_id, COUNT(*) as orders, SUM(funnel_revenue) as revenue')
            ->groupBy('funnel_id')
            ->get()
            ->keyBy('funnel_id');

        $stepsByTemplate = $steps->groupBy('landing_page_template_id');

        $data = $templates->map(function (LandingPageTemplate $template) use ($request, $stepsByTemplate, $sessions, $orders, $user) {
            $usedBy = ($stepsByTemplate[$template->id] ?? collect())
                ->map(fn (FunnelStep $step) => [
                    'funnel_uuid' => $step->funnel->uuid,
                    'funnel_name' => $step->funnel->name,
                    'funnel_status' => $step->funnel->status,
                    'step_id' => $step->id,
                    'step_name' => $step->name,
                    'sessions_30d' => (int) ($sessions[$step->funnel_id] ?? 0),
                    'orders_30d' => (int) ($orders[$step->funnel_id]->orders ?? 0),
                    'revenue_30d' => (float) ($orders[$step->funnel_id]->revenue ?? 0),
                ])
                ->values();

            $totalSessions = $usedBy->sum('sessions_30d');
            $totalOrders = $usedBy->unique('funnel_uuid')->sum('orders_30d');

            return [
                'id' => $template->id,
                'uuid' => $template->uuid,
                'name' => $template->name,
                'description' => $template->description,
                'category' => $template->category,
                'is_active' => $template->is_active,
                'is_full_html' => $this->isFullHtml($template),
                'created_by' => $template->creator?->name,
                'created_at' => $template->created_at?->toIso8601String(),
                'can_edit' => $this->canEdit($request, $template),
                'preview_url' => route('funnel-builder.landing-preview', $template->uuid),
                'used_by' => $user?->isFighter()
                    ? $usedBy->filter(fn ($row) => $this->ownsFunnel($user, $row['funnel_uuid']))->values()
                    : $usedBy,
                'funnels_count' => $usedBy->unique('funnel_uuid')->count(),
                'sessions_30d' => $totalSessions,
                'orders_30d' => $totalOrders,
                'revenue_30d' => round($usedBy->unique('funnel_uuid')->sum('revenue_30d'), 2),
                'conversion_rate' => $totalSessions > 0 ? round($totalOrders / $totalSessions * 100, 2) : null,
            ];
        });

        return response()->json([
            'data' => $data,
            'categories' => LandingPageTemplate::CATEGORIES,
            'can_import' => in_array($user?->role, ['admin', 'employee'], true),
        ]);
    }

    public function store(StoreLandingTemplateRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $template = LandingPageTemplate::create([
            'created_by' => $request->user()->id,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'category' => $validated['category'] ?? null,
            'content' => filled($validated['html'] ?? null)
                ? $this->templates->htmlContent($validated['html'])
                : ['content' => [], 'root' => ['props' => []]],
        ]);

        return response()->json(['success' => true, 'data' => ['id' => $template->id, 'uuid' => $template->uuid]], 201);
    }

    public function update(UpdateLandingTemplateRequest $request, LandingPageTemplate $template): JsonResponse
    {
        $template->update($request->validated());

        return response()->json(['success' => true]);
    }

    public function destroy(Request $request, LandingPageTemplate $template): JsonResponse
    {
        abort_unless($this->canEdit($request, $template), 403, 'You cannot delete this template.');

        $template->delete();

        return response()->json(['success' => true, 'message' => 'Template deleted. Funnels keep their pages; they are just no longer linked.']);
    }

    /**
     * Snapshot a funnel step's page into the library.
     */
    public function saveFromStep(SaveStepAsTemplateRequest $request, string $funnelUuid, int $stepId): JsonResponse
    {
        $step = $this->resolveStep($funnelUuid, $stepId);
        $template = $this->templates->createFromStep($step, $request->validated(), $request->user());

        if (! $template) {
            return response()->json(['success' => false, 'message' => 'This step has no page content to save yet.'], 422);
        }

        return response()->json(['success' => true, 'message' => "Saved \"{$template->name}\" to the Landing Library.", 'data' => ['id' => $template->id]], 201);
    }

    /**
     * Copy a template into a funnel step as a new draft.
     */
    public function apply(Request $request, string $funnelUuid, int $stepId): JsonResponse
    {
        $validated = $request->validate([
            'template_id' => ['required', 'integer', 'exists:landing_page_templates,id'],
        ]);

        $step = $this->resolveStep($funnelUuid, $stepId);
        $template = LandingPageTemplate::findOrFail($validated['template_id']);

        $this->templates->apply($template, $step);

        return response()->json([
            'success' => true,
            'message' => "Applied \"{$template->name}\" as a draft. Open the editor to customise, then publish.",
        ]);
    }

    public function import(Request $request): JsonResponse
    {
        abort_unless(in_array($request->user()?->role, ['admin', 'employee'], true), 403);

        $count = $this->templates->importExisting($request->user());

        return response()->json([
            'success' => true,
            'message' => $count ? "Imported {$count} existing landing page(s) into the library." : 'Nothing new to import — every landing page is already linked.',
        ]);
    }

    /**
     * Templates in a compact form for the "Use template" picker on a step.
     */
    public function options(): JsonResponse
    {
        $data = LandingPageTemplate::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'uuid', 'name', 'category'])
            ->map(fn (LandingPageTemplate $template) => [
                'id' => $template->id,
                'name' => $template->name,
                'category' => $template->category,
                'preview_url' => route('funnel-builder.landing-preview', $template->uuid),
            ]);

        return response()->json(['data' => $data]);
    }

    protected function resolveStep(string $funnelUuid, int $stepId): FunnelStep
    {
        $funnel = Funnel::query()->where('uuid', $funnelUuid)->firstOrFail();

        return $funnel->steps()->findOrFail($stepId);
    }

    protected function canEdit(Request $request, LandingPageTemplate $template): bool
    {
        $user = $request->user();

        return in_array($user?->role, ['admin', 'employee'], true) || (int) $template->created_by === (int) $user?->id;
    }

    protected function ownsFunnel($user, string $funnelUuid): bool
    {
        return Funnel::query()->where('uuid', $funnelUuid)->where('user_id', $user->id)->exists();
    }

    protected function isFullHtml(LandingPageTemplate $template): bool
    {
        return app(\App\Services\Funnel\PuckRenderer::class)->isFullPageHtml($template->content ?? []);
    }
}
