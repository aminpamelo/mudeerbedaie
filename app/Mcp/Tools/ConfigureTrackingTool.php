<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\Concerns\ScopesToMarketer;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class ConfigureTrackingTool extends Tool
{
    use ScopesToMarketer;

    protected string $name = 'configure_tracking';

    protected string $description = <<<'MARKDOWN'
        Set a funnel's tracking pixels (the Tracking tab): a Facebook Pixel id,
        a Google Analytics 4 measurement id (G-XXXX), and/or a Google Ads
        conversion id (AW-XXXX). Provide funnel_uuid and any of the ids to set.
        Passing an id enables that pixel.
        MARKDOWN;

    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'funnel_uuid' => 'required|string',
            'facebook_pixel_id' => 'sometimes|nullable|string|max:50',
            'ga4_measurement_id' => 'sometimes|nullable|string|max:50',
            'google_ads_conversion_id' => 'sometimes|nullable|string|max:50',
        ]);

        $funnel = $this->findScopedFunnel($request->user(), $validated['funnel_uuid']);
        if (! $funnel) {
            return Response::error('That funnel was not found or you do not have access to it.');
        }

        if (! array_intersect_key($validated, array_flip(['facebook_pixel_id', 'ga4_measurement_id', 'google_ads_conversion_id']))) {
            return Response::error('Provide at least one pixel id to set.');
        }

        $settings = $funnel->settings ?? [];
        $pixels = $settings['pixel_settings'] ?? [];

        if (array_key_exists('facebook_pixel_id', $validated)) {
            $pixels['facebook'] = array_merge($pixels['facebook'] ?? [], [
                'enabled' => filled($validated['facebook_pixel_id']),
                'pixel_id' => $validated['facebook_pixel_id'],
            ]);
        }
        if (array_key_exists('ga4_measurement_id', $validated) || array_key_exists('google_ads_conversion_id', $validated)) {
            $google = $pixels['google'] ?? [];
            if (array_key_exists('ga4_measurement_id', $validated)) {
                $google['ga4_measurement_id'] = $validated['ga4_measurement_id'];
            }
            if (array_key_exists('google_ads_conversion_id', $validated)) {
                $google['ads_conversion_id'] = $validated['google_ads_conversion_id'];
            }
            $google['enabled'] = filled($google['ga4_measurement_id'] ?? null) || filled($google['ads_conversion_id'] ?? null);
            $pixels['google'] = $google;
        }

        $settings['pixel_settings'] = $pixels;
        $funnel->update(['settings' => $settings]);

        return Response::json([
            'funnel_uuid' => $funnel->uuid,
            'pixel_settings' => $pixels,
            'message' => 'Tracking pixels updated.',
        ]);
    }

    /**
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'funnel_uuid' => $schema->string()->description('The uuid of the funnel.')->required(),
            'facebook_pixel_id' => $schema->string()->description('Facebook Pixel id (e.g. 1234567890123456).'),
            'ga4_measurement_id' => $schema->string()->description('Google Analytics 4 measurement id (G-XXXXXXX).'),
            'google_ads_conversion_id' => $schema->string()->description('Google Ads conversion id (AW-XXXXXXX).'),
        ];
    }
}
