<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\Concerns\ScopesToMarketer;
use App\Models\FunnelProduct;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class RemoveFunnelProductTool extends Tool
{
    use ScopesToMarketer;

    protected string $name = 'remove_funnel_product';

    protected string $description = <<<'MARKDOWN'
        Remove a product from a funnel. Provide the funnel_uuid and
        funnel_product_id (from list_funnel_products). The step's checkout will
        no longer offer this product.
        MARKDOWN;

    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'funnel_uuid' => 'required|string',
            'funnel_product_id' => 'required|integer',
        ]);

        $funnel = $this->findScopedFunnel($request->user(), $validated['funnel_uuid']);
        if (! $funnel) {
            return Response::error('That funnel was not found or you do not have access to it.');
        }

        $product = FunnelProduct::query()
            ->whereKey($validated['funnel_product_id'])
            ->whereIn('funnel_step_id', $funnel->steps()->pluck('id'))
            ->first();
        if (! $product) {
            return Response::error('That funnel product was not found in this funnel.');
        }

        $product->delete();

        return Response::json(['message' => 'Product removed from the funnel.']);
    }

    /**
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'funnel_uuid' => $schema->string()->description('The uuid of the funnel.')->required(),
            'funnel_product_id' => $schema->integer()->description('The id of the funnel product to remove.')->required(),
        ];
    }
}
