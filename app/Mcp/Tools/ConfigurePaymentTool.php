<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\Concerns\ScopesToMarketer;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class ConfigurePaymentTool extends Tool
{
    use ScopesToMarketer;

    protected string $name = 'configure_payment';

    protected string $description = <<<'MARKDOWN'
        Configure a funnel's checkout payment methods (the Payment tab). Provide
        funnel_uuid and the methods to enable: any of "stripe" (card),
        "bayarcash_fpx" (FPX online banking), "cod" (cash on delivery).
        Optionally set which is the default. At least one method must be enabled
        for checkout to work.
        MARKDOWN;

    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'funnel_uuid' => 'required|string',
            'methods' => 'required|array|min:1',
            'methods.*' => 'in:stripe,bayarcash_fpx,cod',
            'default_method' => 'sometimes|nullable|in:stripe,bayarcash_fpx,cod',
        ]);

        $funnel = $this->findScopedFunnel($request->user(), $validated['funnel_uuid']);
        if (! $funnel) {
            return Response::error('That funnel was not found or you do not have access to it.');
        }

        $methods = array_values(array_unique($validated['methods']));
        $default = $validated['default_method'] ?? $methods[0];
        if (! in_array($default, $methods, true)) {
            return Response::error('The default_method must be one of the enabled methods.');
        }

        $settings = $funnel->payment_settings ?? [];
        $settings['enabled_methods'] = $methods;
        $settings['default_method'] = $default;
        $settings['show_method_selector'] = count($methods) > 1;
        $settings['stripe_enabled'] = in_array('stripe', $methods, true);
        $settings['bayarcash_fpx_enabled'] = in_array('bayarcash_fpx', $methods, true);
        $settings['cod_enabled'] = in_array('cod', $methods, true);

        $funnel->update(['payment_settings' => $settings]);

        return Response::json([
            'funnel_uuid' => $funnel->uuid,
            'enabled_methods' => $methods,
            'default_method' => $default,
            'message' => 'Payment methods configured.',
        ]);
    }

    /**
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'funnel_uuid' => $schema->string()->description('The uuid of the funnel.')->required(),
            'methods' => $schema->array()->description('Payment methods to enable: stripe, bayarcash_fpx, cod.')->required(),
            'default_method' => $schema->string()->enum(['stripe', 'bayarcash_fpx', 'cod'])->description('Which method is pre-selected. Defaults to the first enabled.'),
        ];
    }
}
