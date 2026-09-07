<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\Concerns\ScopesToMarketer;
use App\Models\Product;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class ListProductsTool extends Tool
{
    use ScopesToMarketer;

    protected string $name = 'list_products';

    protected string $description = <<<'MARKDOWN'
        List the catalog products the marketer can sell, with each product's id,
        name, and base price. Use a returned product id as "product_id" when
        creating a landing page so the funnel sells a real catalog product
        (rather than a one-off custom offer). Optionally search by name/SKU.
        MARKDOWN;

    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'search' => 'sometimes|string|max:100',
        ]);

        $user = $request->user();

        $products = $this->sellableProductsQuery($user)
            ->when($validated['search'] ?? null, fn ($q, $search) => $q->search($search))
            ->orderBy('name')
            ->limit(50)
            ->get(['id', 'name', 'sku', 'base_price', 'status'])
            ->map(fn (Product $product) => [
                'product_id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'base_price' => (float) $product->base_price,
            ]);

        return Response::json([
            'count' => $products->count(),
            'products' => $products,
        ]);
    }

    /**
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'search' => $schema->string()
                ->description('Filter products whose name or SKU matches this text.'),
        ];
    }
}
