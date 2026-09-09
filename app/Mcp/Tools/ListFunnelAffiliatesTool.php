<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\Concerns\ScopesToMarketer;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class ListFunnelAffiliatesTool extends Tool
{
    use ScopesToMarketer;

    protected string $name = 'list_funnel_affiliates';

    protected string $description = <<<'MARKDOWN'
        List the affiliates promoting a funnel, with each affiliate's name,
        referral code, and status. Also reports whether the affiliate program is
        enabled on the funnel.
        MARKDOWN;

    public function handle(Request $request): Response
    {
        $validated = $request->validate(['funnel_uuid' => 'required|string']);

        $funnel = $this->findScopedFunnel($request->user(), $validated['funnel_uuid']);
        if (! $funnel) {
            return Response::error('That funnel was not found or you do not have access to it.');
        }

        $affiliates = $funnel->affiliates()
            ->get(['funnel_affiliates.id', 'name', 'ref_code', 'funnel_affiliates.status'])
            ->map(fn ($a) => [
                'name' => $a->name,
                'ref_code' => $a->ref_code,
                'status' => $a->pivot->status ?? $a->status,
            ]);

        return Response::json([
            'funnel_uuid' => $funnel->uuid,
            'affiliate_enabled' => (bool) $funnel->affiliate_enabled,
            'affiliates' => $affiliates,
        ]);
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
