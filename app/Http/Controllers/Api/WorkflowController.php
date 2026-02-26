<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Workflow;
use App\Models\WorkflowConnection;
use App\Models\WorkflowStep;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class WorkflowController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Workflow::query()
            ->with('creator:id,name')
            ->withCount(['enrollments', 'activeEnrollments']);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        if ($request->has('search')) {
            $query->where('name', 'like', '%'.$request->search.'%');
        }

        $workflows = $query->orderBy('updated_at', 'desc')
            ->paginate($request->per_page ?? 15);

        return response()->json($workflows);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'nullable|in:automation,funnel,sequence,broadcast',
            'trigger_type' => 'nullable|string|max:100',
            'trigger_config' => 'nullable|array',
            'canvas_data' => 'nullable|array',
            'settings' => 'nullable|array',
        ]);

        $workflow = Workflow::create([
            'uuid' => (string) Str::uuid(),
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'type' => $validated['type'] ?? 'automation',
            'status' => 'draft',
            'trigger_type' => $validated['trigger_type'] ?? 'manual',
            'trigger_config' => $validated['trigger_config'] ?? null,
            'canvas_data' => $validated['canvas_data'] ?? null,
            'settings' => $validated['settings'] ?? null,
            'created_by' => auth()->id(),
        ]);

        // Create steps and connections from canvas data
        if (isset($validated['canvas_data'])) {
            $this->syncCanvasData($workflow, $validated['canvas_data']);
        }

        return response()->json([
            'data' => $workflow->fresh(['steps', 'connections']),
            'message' => 'Workflow created successfully',
        ], 201);
    }

    public function show(string $uuid): JsonResponse
    {
        $workflow = Workflow::where('uuid', $uuid)
            ->with(['creator:id,name', 'steps', 'connections'])
            ->withCount(['enrollments', 'activeEnrollments'])
            ->firstOrFail();

        return response()->json([
            'data' => $workflow,
        ]);
    }

    public function update(Request $request, string $uuid): JsonResponse
    {
        $workflow = Workflow::where('uuid', $uuid)->firstOrFail();

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'type' => 'nullable|in:automation,funnel,sequence,broadcast',
            'trigger_type' => 'nullable|string|max:100',
            'trigger_config' => 'nullable|array',
            'canvas_data' => 'nullable|array',
            'settings' => 'nullable|array',
        ]);

        $workflow->update($validated);

        // Sync steps and connections if canvas data is provided
        if (isset($validated['canvas_data'])) {
            $this->syncCanvasData($workflow, $validated['canvas_data']);
        }

        return response()->json([
            'data' => $workflow->fresh(['steps', 'connections']),
            'message' => 'Workflow updated successfully',
        ]);
    }

    public function destroy(string $uuid): JsonResponse
    {
        $workflow = Workflow::where('uuid', $uuid)->firstOrFail();

        // Check if workflow has active enrollments
        if ($workflow->activeEnrollments()->exists()) {
            return response()->json([
                'message' => 'Cannot delete workflow with active enrollments',
            ], 422);
        }

        $workflow->delete();

        return response()->json([
            'message' => 'Workflow deleted successfully',
        ]);
    }

    public function publish(string $uuid): JsonResponse
    {
        $workflow = Workflow::where('uuid', $uuid)->firstOrFail();

        // Validate workflow before publishing
        $validation = $this->validateWorkflow($workflow);
        if (! $validation['is_valid']) {
            return response()->json([
                'message' => 'Workflow validation failed',
                'errors' => $validation['errors'],
            ], 422);
        }

        $workflow->publish();

        return response()->json([
            'data' => $workflow->fresh(),
            'message' => 'Workflow published successfully',
        ]);
    }

    public function pause(string $uuid): JsonResponse
    {
        $workflow = Workflow::where('uuid', $uuid)->firstOrFail();
        $workflow->pause();

        return response()->json([
            'data' => $workflow->fresh(),
            'message' => 'Workflow paused successfully',
        ]);
    }

    public function stats(string $uuid): JsonResponse
    {
        $workflow = Workflow::where('uuid', $uuid)->firstOrFail();

        $stats = [
            'total_enrollments' => $workflow->enrollments()->count(),
            'active_enrollments' => $workflow->activeEnrollments()->count(),
            'completed_enrollments' => $workflow->enrollments()->where('status', 'completed')->count(),
            'failed_enrollments' => $workflow->enrollments()->where('status', 'failed')->count(),
            'exited_enrollments' => $workflow->enrollments()->where('status', 'exited')->count(),
        ];

        return response()->json([
            'data' => $stats,
        ]);
    }

    protected function syncCanvasData(Workflow $workflow, array $canvasData): void
    {
        $nodes = $canvasData['nodes'] ?? [];
        $edges = $canvasData['edges'] ?? [];

        // Create/update steps
        $nodeIdToStepId = [];
        foreach ($nodes as $node) {
            $step = WorkflowStep::updateOrCreate(
                [
                    'workflow_id' => $workflow->id,
                    'node_id' => $node['id'],
                ],
                [
                    'uuid' => Str::uuid(),
                    'type' => $node['type'] ?? 'action',
                    'action_type' => $node['data']['actionType'] ?? $node['data']['triggerType'] ?? null,
                    'name' => $node['data']['label'] ?? null,
                    'config' => $node['data']['config'] ?? $node['data'] ?? null,
                    'position_x' => (int) ($node['position']['x'] ?? 0),
                    'position_y' => (int) ($node['position']['y'] ?? 0),
                ]
            );
            $nodeIdToStepId[$node['id']] = $step->id;
        }

        // Delete steps that are no longer in the canvas
        $currentNodeIds = array_column($nodes, 'id');
        $workflow->steps()
            ->whereNotIn('node_id', $currentNodeIds)
            ->delete();

        // Create/update connections
        $workflow->connections()->delete();
        foreach ($edges as $edge) {
            if (isset($nodeIdToStepId[$edge['source']]) && isset($nodeIdToStepId[$edge['target']])) {
                WorkflowConnection::create([
                    'workflow_id' => $workflow->id,
                    'source_step_id' => $nodeIdToStepId[$edge['source']],
                    'target_step_id' => $nodeIdToStepId[$edge['target']],
                    'source_handle' => $edge['sourceHandle'] ?? null,
                    'target_handle' => $edge['targetHandle'] ?? null,
                    'label' => $edge['label'] ?? null,
                ]);
            }
        }
    }

    protected function validateWorkflow(Workflow $workflow): array
    {
        $errors = [];

        // Check for at least one trigger
        $triggerSteps = $workflow->steps()->where('type', 'trigger')->count();
        if ($triggerSteps === 0) {
            $errors[] = 'Workflow must have at least one trigger';
        }

        // Check for at least one action
        $actionSteps = $workflow->steps()->where('type', 'action')->count();
        if ($actionSteps === 0) {
            $errors[] = 'Workflow must have at least one action';
        }

        return [
            'is_valid' => empty($errors),
            'errors' => $errors,
        ];
    }
}
