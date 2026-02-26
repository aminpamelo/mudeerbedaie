<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Funnel;
use App\Models\FunnelAutomation;
use App\Models\FunnelAutomationAction;
use App\Services\MergeTag\VariableRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class FunnelAutomationController extends Controller
{
    /**
     * List all automations for a funnel.
     */
    public function index(string $funnelUuid): JsonResponse
    {
        $funnel = Funnel::where('uuid', $funnelUuid)->firstOrFail();

        $automations = FunnelAutomation::where('funnel_id', $funnel->id)
            ->with(['actions' => fn ($q) => $q->orderBy('sort_order')])
            ->withCount(['logs as executions_count' => fn ($q) => $q->where('status', 'executed')])
            ->orderBy('priority')
            ->get();

        return response()->json([
            'data' => $automations->map(fn ($automation) => $this->transformAutomation($automation)),
        ]);
    }

    /**
     * Create a new automation.
     */
    public function store(Request $request, string $funnelUuid): JsonResponse
    {
        $funnel = Funnel::where('uuid', $funnelUuid)->firstOrFail();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'trigger_type' => 'required|string|max:100',
            'trigger_config' => 'nullable|array',
            'canvas_data' => 'nullable|array',
            'is_active' => 'boolean',
            'priority' => 'integer|min:0',
        ]);

        $automation = FunnelAutomation::create([
            'uuid' => Str::uuid()->toString(),
            'funnel_id' => $funnel->id,
            'name' => $validated['name'],
            'trigger_type' => $validated['trigger_type'],
            'trigger_config' => $validated['trigger_config'] ?? [],
            'is_active' => $validated['is_active'] ?? false,
            'priority' => $validated['priority'] ?? 0,
        ]);

        // Create actions from canvas data if provided
        if (isset($validated['canvas_data'])) {
            $this->syncCanvasData($automation, $validated['canvas_data']);
        }

        return response()->json([
            'message' => 'Automation created successfully',
            'data' => $this->transformAutomation($automation->fresh(['actions'])),
        ], 201);
    }

    /**
     * Get a single automation.
     */
    public function show(string $funnelUuid, int $automationId): JsonResponse
    {
        $funnel = Funnel::where('uuid', $funnelUuid)->firstOrFail();

        $automation = FunnelAutomation::where('id', $automationId)
            ->where('funnel_id', $funnel->id)
            ->with(['actions' => fn ($q) => $q->orderBy('sort_order')])
            ->withCount([
                'logs as executions_count' => fn ($q) => $q->where('status', 'executed'),
                'logs as failed_count' => fn ($q) => $q->where('status', 'failed'),
            ])
            ->firstOrFail();

        return response()->json([
            'data' => $this->transformAutomation($automation),
        ]);
    }

    /**
     * Update an automation.
     */
    public function update(Request $request, string $funnelUuid, int $automationId): JsonResponse
    {
        $funnel = Funnel::where('uuid', $funnelUuid)->firstOrFail();

        $automation = FunnelAutomation::where('id', $automationId)
            ->where('funnel_id', $funnel->id)
            ->firstOrFail();

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'trigger_type' => 'sometimes|string|max:100',
            'trigger_config' => 'nullable|array',
            'canvas_data' => 'nullable|array',
            'is_active' => 'boolean',
            'priority' => 'integer|min:0',
        ]);

        $automation->update($validated);

        // Sync actions from canvas data if provided
        if (isset($validated['canvas_data'])) {
            $this->syncCanvasData($automation, $validated['canvas_data']);
        }

        return response()->json([
            'message' => 'Automation updated successfully',
            'data' => $this->transformAutomation($automation->fresh(['actions'])),
        ]);
    }

    /**
     * Delete an automation.
     */
    public function destroy(string $funnelUuid, int $automationId): JsonResponse
    {
        $funnel = Funnel::where('uuid', $funnelUuid)->firstOrFail();

        $automation = FunnelAutomation::where('id', $automationId)
            ->where('funnel_id', $funnel->id)
            ->firstOrFail();

        // Delete related actions and logs
        $automation->actions()->delete();
        $automation->logs()->delete();
        $automation->delete();

        return response()->json([
            'message' => 'Automation deleted successfully',
        ]);
    }

    /**
     * Toggle automation active status.
     */
    public function toggleActive(string $funnelUuid, int $automationId): JsonResponse
    {
        $funnel = Funnel::where('uuid', $funnelUuid)->firstOrFail();

        $automation = FunnelAutomation::where('id', $automationId)
            ->where('funnel_id', $funnel->id)
            ->firstOrFail();

        $automation->update(['is_active' => ! $automation->is_active]);

        return response()->json([
            'message' => $automation->is_active ? 'Automation activated' : 'Automation deactivated',
            'data' => $this->transformAutomation($automation->fresh(['actions'])),
        ]);
    }

    /**
     * Duplicate an automation.
     */
    public function duplicate(string $funnelUuid, int $automationId): JsonResponse
    {
        $funnel = Funnel::where('uuid', $funnelUuid)->firstOrFail();

        $automation = FunnelAutomation::where('id', $automationId)
            ->where('funnel_id', $funnel->id)
            ->with('actions')
            ->firstOrFail();

        // Create new automation
        $newAutomation = FunnelAutomation::create([
            'uuid' => Str::uuid()->toString(),
            'funnel_id' => $funnel->id,
            'name' => $automation->name.' (Copy)',
            'trigger_type' => $automation->trigger_type,
            'trigger_config' => $automation->trigger_config,
            'is_active' => false,
            'priority' => $automation->priority,
        ]);

        // Duplicate actions
        foreach ($automation->actions as $action) {
            FunnelAutomationAction::create([
                'automation_id' => $newAutomation->id,
                'action_type' => $action->action_type,
                'action_config' => $action->action_config,
                'delay_minutes' => $action->delay_minutes,
                'sort_order' => $action->sort_order,
                'conditions' => $action->conditions,
            ]);
        }

        return response()->json([
            'message' => 'Automation duplicated successfully',
            'data' => $this->transformAutomation($newAutomation->fresh(['actions'])),
        ], 201);
    }

    /**
     * Get automation logs.
     */
    public function logs(Request $request, string $funnelUuid, int $automationId): JsonResponse
    {
        $funnel = Funnel::where('uuid', $funnelUuid)->firstOrFail();

        $automation = FunnelAutomation::where('id', $automationId)
            ->where('funnel_id', $funnel->id)
            ->firstOrFail();

        $logs = $automation->logs()
            ->with(['session:id,uuid,email,visitor_id'])
            ->latest()
            ->paginate($request->input('per_page', 20));

        return response()->json($logs);
    }

    /**
     * Sync canvas data (nodes/edges) to automation actions.
     */
    protected function syncCanvasData(FunnelAutomation $automation, array $canvasData): void
    {
        $nodes = $canvasData['nodes'] ?? [];
        $edges = $canvasData['edges'] ?? [];

        // Extract trigger from nodes
        $triggerNode = collect($nodes)->firstWhere('type', 'trigger');
        if ($triggerNode) {
            $automation->update([
                'trigger_type' => $triggerNode['data']['triggerType'] ?? $automation->trigger_type,
                'trigger_config' => array_merge(
                    $automation->trigger_config ?? [],
                    $triggerNode['data']['config'] ?? [],
                    ['canvas_position' => $triggerNode['position'] ?? null]
                ),
            ]);
        }

        // Delete existing actions
        $automation->actions()->delete();

        // Create actions from action nodes (following edge connections)
        $actionNodes = collect($nodes)->filter(fn ($n) => in_array($n['type'], ['action', 'condition', 'delay']));
        $sortOrder = 0;

        foreach ($actionNodes as $node) {
            $delayMinutes = 0;

            if ($node['type'] === 'delay') {
                $delay = $node['data']['delay'] ?? 1;
                $unit = $node['data']['unit'] ?? 'hours';
                $delayMinutes = match ($unit) {
                    'minutes' => $delay,
                    'hours' => $delay * 60,
                    'days' => $delay * 60 * 24,
                    'weeks' => $delay * 60 * 24 * 7,
                    default => $delay * 60,
                };
            }

            FunnelAutomationAction::create([
                'automation_id' => $automation->id,
                'action_type' => $node['data']['actionType'] ?? $node['type'],
                'action_config' => array_merge(
                    $node['data']['config'] ?? [],
                    [
                        'node_id' => $node['id'],
                        'label' => $node['data']['label'] ?? null,
                        'canvas_position' => $node['position'] ?? null,
                    ]
                ),
                'delay_minutes' => $delayMinutes,
                'sort_order' => $sortOrder++,
                'conditions' => $node['type'] === 'condition' ? [
                    'field' => $node['data']['field'] ?? null,
                    'operator' => $node['data']['operator'] ?? 'equals',
                    'value' => $node['data']['value'] ?? null,
                ] : null,
            ]);
        }

        // Store full canvas data in trigger_config for reconstruction
        $automation->update([
            'trigger_config' => array_merge(
                $automation->trigger_config ?? [],
                ['canvas_data' => $canvasData]
            ),
        ]);
    }

    /**
     * Transform automation for API response.
     */
    protected function transformAutomation(FunnelAutomation $automation): array
    {
        // Reconstruct canvas data from trigger_config
        $canvasData = $automation->trigger_config['canvas_data'] ?? null;

        return [
            'id' => $automation->id,
            'uuid' => $automation->uuid,
            'name' => $automation->name,
            'trigger_type' => $automation->trigger_type,
            'trigger_config' => collect($automation->trigger_config)->except(['canvas_data'])->all(),
            'canvas_data' => $canvasData,
            'is_active' => $automation->is_active,
            'priority' => $automation->priority,
            'actions_count' => $automation->actions->count(),
            'executions_count' => $automation->executions_count ?? 0,
            'failed_count' => $automation->failed_count ?? 0,
            'created_at' => $automation->created_at?->toISOString(),
            'updated_at' => $automation->updated_at?->toISOString(),
        ];
    }

    /**
     * Get all automation logs for a funnel (across all automations).
     */
    public function allLogs(Request $request, string $funnelUuid): JsonResponse
    {
        $funnel = Funnel::where('uuid', $funnelUuid)->firstOrFail();

        $logs = \App\Models\FunnelAutomationLog::query()
            ->whereHas('automation', fn ($q) => $q->where('funnel_id', $funnel->id))
            ->with([
                'automation:id,name,trigger_type',
                'action:id,action_type,action_config',
                'session:id,uuid,email,visitor_id',
            ])
            ->when($request->input('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->input('automation_id'), fn ($q, $id) => $q->where('automation_id', $id))
            ->latest('id')
            ->paginate($request->input('per_page', 25));

        return response()->json([
            'data' => $logs->items(),
            'meta' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
            ],
        ]);
    }

    /**
     * Get available merge tag variables for a trigger type.
     *
     * This endpoint returns all variables that can be used in message templates
     * (email, WhatsApp, etc.) for personalization with merge tags like {{contact.name}}.
     */
    public function variables(Request $request): JsonResponse
    {
        $triggerType = $request->input('trigger_type', 'purchase_completed');

        // Get variables for the specified trigger type
        $variables = VariableRegistry::getVariablesForTrigger($triggerType);

        return response()->json([
            'trigger_type' => $triggerType,
            'categories' => $variables,
        ]);
    }

    /**
     * Get all available merge tag variables (for documentation).
     */
    public function allVariables(): JsonResponse
    {
        return response()->json([
            'categories' => VariableRegistry::getAllVariables(),
        ]);
    }
}
