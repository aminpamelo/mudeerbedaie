<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\Concerns\ScopesToMarketer;
use App\Models\FunnelProduct;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class ListFunnelProductsTool extends Tool
{
    use ScopesToMarketer;

    protected string $name = 'list_funnel_products';

    protected string $description = <<<'MARKDOWN'
        List the products attached to a funnel (across all its steps), each with
        its funnel_product_id, step, name, price, type (main/upsell/downsell),
        and the catalog product it links to. Use funnel_product_id with
        update_funnel_product / remove_funnel_product.
        MARKDOWN;

    public function handle(Request $request): Response
    {
        $validated = $request->validate(['funnel_uuid' => 'required|string']);

        $funnel = $this->findScopedFunnel($request->user(), $validated['funnel_uuid']);
        if (! $funnel) {
            return Response::error('That funnel was not found or you do not have access to it.');
        }

        $stepIds = $funnel->steps()->pluck('id');
        $products = FunnelProduct::query()
            ->whereIn('funnel_step_id', $stepIds)
            ->with('step:id,name,type')
            ->orderBy('funnel_step_id')->orderBy('sort_order')
            ->get()
            ->map(fn (FunnelProduct $p) => [
                'funnel_product_id' => $p->id,
                'step_id' => $p->funnel_step_id,
                'step_name' => $p->step?->name,
                'name' => $p->getDisplayName(),
                'type' => $p->type,
                'funnel_price' => (float) $p->funnel_price,
                'compare_at_price' => $p->compare_at_price !== null ? (float) $p->compare_at_price : null,
                'product_id' => $p->product_id,
                'is_active' => (bool) $p->is_active,
            ]);

        return Response::json(['funnel_uuid' => $funnel->uuid, 'products' => $products]);
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
