<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\Concerns\ScopesToMarketer;
use App\Services\Funnel\FunnelStudioAuthorService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class UpdateLandingPageTool extends Tool
{
    use ScopesToMarketer;

    public function __construct(
        protected FunnelStudioAuthorService $author
    ) {}

    protected string $name = 'update_landing_page';

    protected string $description = <<<'MARKDOWN'
        Update an existing landing page's HTML and/or price. Provide the
        funnel_uuid (from create_landing_page or list_funnels) and the new html
        (full replacement) and/or a new price. Changes apply immediately if the
        funnel is already published. Keep the [checkout_form] tag in your HTML
        so the page keeps its working checkout.
        MARKDOWN;

    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'funnel_uuid' => 'required|string',
            'html' => 'sometimes|nullable|string',
            'price' => 'sometimes|nullable|numeric|min:0',
        ]);

        if (empty($validated['html'] ?? null) && ! isset($validated['price'])) {
            return Response::error('Provide new html and/or a new price to update.');
        }

        $funnel = $this->findScopedFunnel($request->user(), $validated['funnel_uuid']);
        if (! $funnel) {
            return Response::error('That funnel was not found or you do not have access to it.');
        }

        $this->author->updateLandingPage(
            $funnel,
            $validated['html'] ?? null,
            isset($validated['price']) ? (float) $validated['price'] : null,
        );

        return Response::json([
            'funnel_uuid' => $funnel->uuid,
            'status' => $funnel->status,
            'public_url' => $funnel->status === 'published' ? route('funnel.show', $funnel->slug) : null,
            'message' => 'Landing page updated.',
        ]);
    }

    /**
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'funnel_uuid' => $schema->string()
                ->description('The uuid of the funnel to update.')
                ->required(),
            'html' => $schema->string()
                ->description('New landing page HTML (full replacement). Keep the [checkout_form] tag.'),
            'price' => $schema->number()
                ->description('New price charged to the customer, in RM.')
                ->min(0),
        ];
    }
}
