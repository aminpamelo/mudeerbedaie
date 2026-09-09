<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\Concerns\ScopesToMarketer;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class GetFunnelEmbedTool extends Tool
{
    use ScopesToMarketer;

    protected string $name = 'get_funnel_embed';

    protected string $description = <<<'MARKDOWN'
        Enable embedding for a funnel and return the embed code so it can be
        placed on an external website. Provide funnel_uuid. Returns an iframe
        snippet and the embed URL. Set enable=false to turn embedding off.
        MARKDOWN;

    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'funnel_uuid' => 'required|string',
            'enable' => 'sometimes|boolean',
        ]);

        $funnel = $this->findScopedFunnel($request->user(), $validated['funnel_uuid']);
        if (! $funnel) {
            return Response::error('That funnel was not found or you do not have access to it.');
        }

        $enable = $validated['enable'] ?? true;

        if (! $enable) {
            $funnel->update(['embed_enabled' => false]);

            return Response::json(['funnel_uuid' => $funnel->uuid, 'embed_enabled' => false, 'message' => 'Embedding disabled.']);
        }

        if (empty($funnel->embed_key)) {
            $funnel->embed_key = Str::random(32);
        }
        $funnel->embed_enabled = true;
        $funnel->save();

        $embedUrl = route('funnel.embed', ['embedKey' => $funnel->embed_key]);
        $iframe = '<iframe src="'.$embedUrl.'" width="100%" height="800" frameborder="0" style="border:0;"></iframe>';

        return Response::json([
            'funnel_uuid' => $funnel->uuid,
            'embed_enabled' => true,
            'embed_key' => $funnel->embed_key,
            'embed_url' => $embedUrl,
            'iframe_code' => $iframe,
            'message' => 'Embedding enabled. Paste iframe_code into any website to embed this funnel.',
        ]);
    }

    /**
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'funnel_uuid' => $schema->string()->description('The uuid of the funnel.')->required(),
            'enable' => $schema->boolean()->description('Enable (true, default) or disable (false) embedding.'),
        ];
    }
}
