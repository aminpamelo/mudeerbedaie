<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\Concerns\ScopesToMarketer;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class PublishFunnelTool extends Tool
{
    use ScopesToMarketer;

    protected string $name = 'publish_funnel';

    protected string $description = <<<'MARKDOWN'
        Publish a funnel so its landing page goes live and can take orders, or
        take it offline again. Provide the funnel_uuid. Set unpublish=true to
        revert it to a draft. Publishing makes the page publicly reachable at
        its public_url immediately.
        MARKDOWN;

    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'funnel_uuid' => 'required|string',
            'unpublish' => 'sometimes|boolean',
        ]);

        $funnel = $this->findScopedFunnel($request->user(), $validated['funnel_uuid']);
        if (! $funnel) {
            return Response::error('That funnel was not found or you do not have access to it.');
        }

        if ($validated['unpublish'] ?? false) {
            $funnel->unpublish();

            return Response::json([
                'funnel_uuid' => $funnel->uuid,
                'status' => $funnel->status,
                'message' => 'Funnel is now a draft and no longer public.',
            ]);
        }

        $funnel->publish();

        return Response::json([
            'funnel_uuid' => $funnel->uuid,
            'status' => $funnel->status,
            'public_url' => route('funnel.show', $funnel->slug),
            'message' => 'Funnel is live. Share the public_url to start selling.',
        ]);
    }

    /**
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'funnel_uuid' => $schema->string()
                ->description('The uuid of the funnel to publish or unpublish.')
                ->required(),
            'unpublish' => $schema->boolean()
                ->description('Set true to take the funnel offline (revert to draft). Defaults to false.')
                ->default(false),
        ];
    }
}
