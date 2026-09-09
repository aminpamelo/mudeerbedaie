<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\Concerns\ScopesToMarketer;
use App\Models\FunnelStep;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class ListFunnelStepsTool extends Tool
{
    use ScopesToMarketer;

    protected string $name = 'list_funnel_steps';

    protected string $description = <<<'MARKDOWN'
        List the steps (pages) of a funnel — each with its id, name, type
        (landing/sales/checkout/upsell/downsell/thankyou/optin), slug, order,
        active state, and how many products are attached. Use a step id with the
        product and content tools.
        MARKDOWN;

    public function handle(Request $request): Response
    {
        $validated = $request->validate(['funnel_uuid' => 'required|string']);

        $funnel = $this->findScopedFunnel($request->user(), $validated['funnel_uuid']);
        if (! $funnel) {
            return Response::error('That funnel was not found or you do not have access to it.');
        }

        $steps = $funnel->steps()->withCount('products')->orderBy('sort_order')->get()
            ->map(fn (FunnelStep $step) => [
                'step_id' => $step->id,
                'name' => $step->name,
                'type' => $step->type,
                'slug' => $step->slug,
                'sort_order' => $step->sort_order,
                'is_active' => (bool) $step->is_active,
                'products_count' => (int) $step->products_count,
            ]);

        return Response::json(['funnel_uuid' => $funnel->uuid, 'steps' => $steps]);
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
