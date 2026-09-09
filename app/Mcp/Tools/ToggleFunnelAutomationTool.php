<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\Concerns\ScopesToMarketer;
use App\Models\FunnelAutomation;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class ToggleFunnelAutomationTool extends Tool
{
    use ScopesToMarketer;

    protected string $name = 'toggle_funnel_automation';

    protected string $description = <<<'MARKDOWN'
        Turn a funnel automation on or off. Provide the funnel_uuid and
        automation_id (from list_funnel_automations). Optionally set active
        explicitly; otherwise it flips the current state.
        MARKDOWN;

    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'funnel_uuid' => 'required|string',
            'automation_id' => 'required|integer',
            'active' => 'sometimes|boolean',
        ]);

        $funnel = $this->findScopedFunnel($request->user(), $validated['funnel_uuid']);
        if (! $funnel) {
            return Response::error('That funnel was not found or you do not have access to it.');
        }

        $automation = FunnelAutomation::query()
            ->where('funnel_id', $funnel->id)->whereKey($validated['automation_id'])->first();
        if (! $automation) {
            return Response::error('That automation was not found in this funnel.');
        }

        $automation->update(['is_active' => $validated['active'] ?? ! $automation->is_active]);

        return Response::json([
            'automation_id' => $automation->id,
            'is_active' => (bool) $automation->is_active,
            'message' => $automation->is_active ? 'Automation is now active.' : 'Automation is now paused.',
        ]);
    }

    /**
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'funnel_uuid' => $schema->string()->description('The uuid of the funnel.')->required(),
            'automation_id' => $schema->integer()->description('The id of the automation.')->required(),
            'active' => $schema->boolean()->description('Set active on/off explicitly. Omit to flip.'),
        ];
    }
}
