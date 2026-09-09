<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\Concerns\ScopesToMarketer;
use App\Models\FunnelAutomation;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class ListFunnelAutomationsTool extends Tool
{
    use ScopesToMarketer;

    protected string $name = 'list_funnel_automations';

    protected string $description = <<<'MARKDOWN'
        List a funnel's automations — each with its automation_id, name, trigger,
        active state, and number of actions. Use the automation_id with
        toggle_funnel_automation / delete_funnel_automation.
        MARKDOWN;

    public function handle(Request $request): Response
    {
        $validated = $request->validate(['funnel_uuid' => 'required|string']);

        $funnel = $this->findScopedFunnel($request->user(), $validated['funnel_uuid']);
        if (! $funnel) {
            return Response::error('That funnel was not found or you do not have access to it.');
        }

        $automations = FunnelAutomation::query()
            ->where('funnel_id', $funnel->id)
            ->withCount('actions')
            ->orderByDesc('is_active')->orderBy('name')
            ->get()
            ->map(fn (FunnelAutomation $a) => [
                'automation_id' => $a->id,
                'name' => $a->name,
                'trigger_type' => $a->trigger_type,
                'is_active' => (bool) $a->is_active,
                'actions_count' => (int) $a->actions_count,
            ]);

        return Response::json(['funnel_uuid' => $funnel->uuid, 'automations' => $automations]);
    }

    /**
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'funnel_uuid' => $schema->string()->description('The uuid of the funnel.')->required(),
        ];
    }
}
