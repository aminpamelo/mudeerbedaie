<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\Concerns\ScopesToMarketer;
use App\Services\Funnel\FunnelStudioAuthorService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class CreateLandingPageTool extends Tool
{
    use ScopesToMarketer;

    public function __construct(
        protected FunnelStudioAuthorService $author
    ) {}

    protected string $name = 'create_landing_page';

    protected string $description = <<<'MARKDOWN'
        Create a complete single-page sales funnel from HTML and publish it. You
        provide the landing page HTML and a price; the platform builds a funnel
        with a working checkout and returns its public URL.

        The HTML may be a full document or a fragment. Put the literal tag
        [checkout_form] on its own line where the payment form should appear —
        the platform replaces it with a real, working checkout (do NOT build
        your own payment fields). If you omit the tag, one is added at the end.

        To sell an existing catalog product pass its product_id (see
        list_products); otherwise pass product_name for a one-off offer. price
        is what the customer is charged (RM). Set publish=true to go live now,
        or leave it false to create a draft the marketer reviews first.
        MARKDOWN;

    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'html' => 'required|string',
            'price' => 'required|numeric|min:0',
            'product_id' => 'sometimes|nullable|integer',
            'product_name' => 'sometimes|nullable|string|max:255',
            'compare_at_price' => 'sometimes|nullable|numeric|min:0',
            'publish' => 'sometimes|boolean',
        ], [
            'price.required' => 'You must set a price (in RM) for the offer, e.g. 49.00.',
        ]);

        $user = $request->user();

        // If selling an existing catalog product, it must be one this marketer
        // is allowed to sell.
        if (! empty($validated['product_id'])) {
            $product = $this->sellableProductsQuery($user)->find($validated['product_id']);
            if (! $product) {
                return Response::error("Product #{$validated['product_id']} was not found or you cannot sell it. Use list_products to find a valid product, or pass product_name instead.");
            }
        }

        $funnel = $this->author->createSellingFunnel(
            user: $user,
            name: $validated['name'],
            html: $validated['html'],
            product: [
                'product_id' => $validated['product_id'] ?? null,
                'name' => $validated['product_name'] ?? $validated['name'],
                'price' => (float) $validated['price'],
                'compare_at_price' => isset($validated['compare_at_price']) ? (float) $validated['compare_at_price'] : null,
            ],
            publish: (bool) ($validated['publish'] ?? false),
        );

        $published = $funnel->status === 'published';

        return Response::json([
            'funnel_uuid' => $funnel->uuid,
            'name' => $funnel->name,
            'status' => $funnel->status,
            'public_url' => $published ? route('funnel.show', $funnel->slug) : null,
            'price' => (float) $validated['price'],
            'message' => $published
                ? 'Landing page is live. Share the public_url to start selling.'
                : 'Draft created. Review it, then publish with publish_funnel to go live.',
        ]);
    }

    /**
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()
                ->description('Name of the funnel / landing page (internal + page title).')
                ->required(),
            'html' => $schema->string()
                ->description('The landing page HTML. Include the [checkout_form] tag where the payment form should go.')
                ->required(),
            'price' => $schema->number()
                ->description('Price charged to the customer, in RM (e.g. 49.00).')
                ->min(0)
                ->required(),
            'product_id' => $schema->integer()
                ->description('Optional: id of an existing catalog product to sell (from list_products).'),
            'product_name' => $schema->string()
                ->description('Optional: name for a one-off offer when not using an existing product_id.'),
            'compare_at_price' => $schema->number()
                ->description('Optional: a higher "was" price to show a discount.')
                ->min(0),
            'publish' => $schema->boolean()
                ->description('Publish immediately (true) or create a draft (false, default).')
                ->default(false),
        ];
    }
}
