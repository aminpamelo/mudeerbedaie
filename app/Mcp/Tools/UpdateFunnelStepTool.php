<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\Concerns\ScopesToMarketer;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class UpdateFunnelStepTool extends Tool
{
    use ScopesToMarketer;

    protected string $name = 'update_funnel_step';

    protected string $description = <<<'MARKDOWN'
        Update a funnel step's name, type, or active state. Provide the
        funnel_uuid and step_id (from list_funnel_steps) plus the fields to
        change. Deactivating a step hides it from the public funnel.
        MARKDOWN;

    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'funnel_uuid' => 'required|string',
            'step_id' => 'required|integer',
            'name' => 'sometimes|string|max:255',
            'type' => 'sometimes|in:landing,sales,checkout,upsell,downsell,thankyou,optin',
            'is_active' => 'sometimes|boolean',
        ]);

        $step = $this->findFunnelStep($request->user(), $validated['funnel_uuid'], $validated['step_id']);
        if (! $step) {
            return Response::error('That step was not found or you do not have access to it.');
        }

        $step->fill(array_intersect_key($validated, array_flip(['name', 'type', 'is_active'])));
        $step->save();

        return Response::json([
            'step_id' => $step->id,
            'name' => $step->name,
            'type' => $step->type,
            'is_active' => (bool) $step->is_active,
            'message' => 'Step updated.',
        ]);
    }

    /**
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'funnel_uuid' => $schema->string()->description('The uuid of the funnel.')->required(),
            'step_id' => $schema->integer()->description('The id of the step (from list_funnel_steps).')->required(),
            'name' => $schema->string()->description('New step name.'),
            'type' => $schema->string()
                ->enum(['landing', 'sales', 'checkout', 'upsell', 'downsell', 'thankyou', 'optin'])
                ->description('New step type.'),
            'is_active' => $schema->boolean()->description('Whether the step is active (shown publicly).'),
        ];
    }
}
