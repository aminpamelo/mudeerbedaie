<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\Concerns\ScopesToMarketer;
use App\Models\FunnelProduct;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class UpdateFunnelProductTool extends Tool
{
    use ScopesToMarketer;

    protected string $name = 'update_funnel_product';

    protected string $description = <<<'MARKDOWN'
        Update a funnel product's price, name, or compare-at price. Provide the
        funnel_uuid and funnel_product_id (from list_funnel_products) plus the
        fields to change.
        MARKDOWN;

    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'funnel_uuid' => 'required|string',
            'funnel_product_id' => 'required|integer',
            'price' => 'sometimes|numeric|min:0',
            'name' => 'sometimes|string|max:255',
            'compare_at_price' => 'sometimes|nullable|numeric|min:0',
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

        if (array_key_exists('price', $validated)) {
            $product->funnel_price = $validated['price'];
        }
        if (array_key_exists('name', $validated)) {
            $product->name = $validated['name'];
        }
        if (array_key_exists('compare_at_price', $validated)) {
            $product->compare_at_price = $validated['compare_at_price'];
        }
        $product->save();

        return Response::json([
            'funnel_product_id' => $product->id,
            'name' => $product->getDisplayName(),
            'funnel_price' => (float) $product->funnel_price,
            'message' => 'Product updated.',
        ]);
    }

    /**
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'funnel_uuid' => $schema->string()->description('The uuid of the funnel.')->required(),
            'funnel_product_id' => $schema->integer()->description('The id of the funnel product (from list_funnel_products).')->required(),
            'price' => $schema->number()->description('New price in RM.')->min(0),
            'name' => $schema->string()->description('New display name.'),
            'compare_at_price' => $schema->number()->description('New "was" price (or 0 to clear).')->min(0),
        ];
    }
}
