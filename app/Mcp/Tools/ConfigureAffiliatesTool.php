<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\Concerns\ScopesToMarketer;
use App\Models\FunnelAffiliateCommissionRule;
use App\Models\FunnelProduct;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class ConfigureAffiliatesTool extends Tool
{
    use ScopesToMarketer;

    protected string $name = 'configure_affiliates';

    protected string $description = <<<'MARKDOWN'
        Turn a funnel's affiliate program on or off, and optionally set a
        commission rate that applies to all of the funnel's products. Provide
        funnel_uuid and enabled. To set commission, pass commission_type
        (percentage or fixed) and commission_value (e.g. 10 for 10% or RM10).
        MARKDOWN;

    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'funnel_uuid' => 'required|string',
            'enabled' => 'required|boolean',
            'commission_type' => 'sometimes|nullable|in:percentage,fixed',
            'commission_value' => 'sometimes|nullable|numeric|min:0',
        ]);

        $funnel = $this->findScopedFunnel($request->user(), $validated['funnel_uuid']);
        if (! $funnel) {
            return Response::error('That funnel was not found or you do not have access to it.');
        }

        $funnel->update(['affiliate_enabled' => $validated['enabled']]);

        $rulesSet = 0;
        if (! empty($validated['commission_type']) && isset($validated['commission_value'])) {
            $productIds = FunnelProduct::query()
                ->whereIn('funnel_step_id', $funnel->steps()->pluck('id'))
                ->pluck('id');

            foreach ($productIds as $productId) {
                FunnelAffiliateCommissionRule::updateOrCreate(
                    ['funnel_id' => $funnel->id, 'funnel_product_id' => $productId],
                    ['commission_type' => $validated['commission_type'], 'commission_value' => $validated['commission_value']],
                );
                $rulesSet++;
            }
        }

        return Response::json([
            'funnel_uuid' => $funnel->uuid,
            'affiliate_enabled' => (bool) $funnel->affiliate_enabled,
            'commission_rules_set' => $rulesSet,
            'message' => $validated['enabled']
                ? 'Affiliate program enabled'.($rulesSet ? " with commission on {$rulesSet} product(s)." : '. Add products first if you want commission rules.')
                : 'Affiliate program disabled.',
        ]);
    }

    /**
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'funnel_uuid' => $schema->string()->description('The uuid of the funnel.')->required(),
            'enabled' => $schema->boolean()->description('Enable or disable the affiliate program.')->required(),
            'commission_type' => $schema->string()->enum(['percentage', 'fixed'])->description('Commission type for all products.'),
            'commission_value' => $schema->number()->description('Commission amount (e.g. 10 for 10% or RM10).')->min(0),
        ];
    }
}
