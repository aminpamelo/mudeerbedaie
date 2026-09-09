<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\Concerns\ScopesToMarketer;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class AddFunnelProductTool extends Tool
{
    use ScopesToMarketer;

    protected string $name = 'add_funnel_product';

    protected string $description = <<<'MARKDOWN'
        Assign a product to a funnel step so the checkout on that step can sell
        it. Provide funnel_uuid, step_id (from list_funnel_steps), a price, and
        the type (main = the offer, upsell, or downsell). Sell an existing
        catalog product with product_id (see list_products), or a one-off offer
        with product_name. compare_at_price shows a "was" price.
        MARKDOWN;

    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'funnel_uuid' => 'required|string',
            'step_id' => 'required|integer',
            'price' => 'required|numeric|min:0',
            'type' => 'sometimes|in:main,upsell,downsell',
            'product_id' => 'sometimes|nullable|integer',
            'product_name' => 'sometimes|nullable|string|max:255',
            'compare_at_price' => 'sometimes|nullable|numeric|min:0',
        ]);

        $user = $request->user();
        $step = $this->findFunnelStep($user, $validated['funnel_uuid'], $validated['step_id']);
        if (! $step) {
            return Response::error('That step was not found or you do not have access to it.');
        }

        $attributes = [
            'type' => $validated['type'] ?? 'main',
            'funnel_price' => $validated['price'],
            'compare_at_price' => $validated['compare_at_price'] ?? null,
            'sort_order' => (int) $step->products()->max('sort_order') + 1,
            'is_active' => true,
        ];

        if (! empty($validated['product_id'])) {
            $product = $this->sellableProductsQuery($user)->find($validated['product_id']);
            if (! $product) {
                return Response::error("Product #{$validated['product_id']} was not found or you cannot sell it. Use list_products, or pass product_name instead.");
            }
            $attributes['product_id'] = $product->id;
            $attributes['name'] = $validated['product_name'] ?? $product->name;
        } else {
            $attributes['name'] = $validated['product_name'] ?? 'Offer';
        }

        $funnelProduct = $step->products()->create($attributes);

        return Response::json([
            'funnel_product_id' => $funnelProduct->id,
            'step_id' => $step->id,
            'name' => $funnelProduct->getDisplayName(),
            'type' => $funnelProduct->type,
            'funnel_price' => (float) $funnelProduct->funnel_price,
            'message' => 'Product attached to the step. Make sure the step (or its page) has a [checkout_form] so it can take payment.',
        ]);
    }

    /**
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'funnel_uuid' => $schema->string()->description('The uuid of the funnel.')->required(),
            'step_id' => $schema->integer()->description('The step to attach the product to (from list_funnel_steps).')->required(),
            'price' => $schema->number()->description('Price charged to the customer, in RM.')->min(0)->required(),
            'type' => $schema->string()->enum(['main', 'upsell', 'downsell'])->description('Offer type. Defaults to main.'),
            'product_id' => $schema->integer()->description('Optional: id of an existing catalog product (from list_products).'),
            'product_name' => $schema->string()->description('Optional: name for a one-off offer when not using product_id.'),
            'compare_at_price' => $schema->number()->description('Optional: a higher "was" price to show a discount.')->min(0),
        ];
    }
}
