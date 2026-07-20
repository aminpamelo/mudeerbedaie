<?php

namespace App\Services\Funnel;

use App\Models\Funnel;

/**
 * Browser-side Google tracking (gtag.js) for funnels — Google Analytics 4 and
 * Google Ads conversion tracking. Config lives alongside the Facebook pixel in
 * `funnel.settings.pixel_settings.google`.
 *
 * @see FacebookPixelService for the parallel FB pixel.
 */
class GoogleTrackingService
{
    /**
     * Default enabled state for each trackable event.
     *
     * @return array<string, bool>
     */
    public static function defaultEvents(): array
    {
        return [
            'page_view' => true,
            'view_item' => true,
            'add_to_cart' => true,
            'begin_checkout' => true,
            'purchase' => true,
        ];
    }

    /**
     * Resolve the Google tracking settings for a funnel.
     *
     * @return array{enabled: bool, ga4_measurement_id: string, ads_conversion_id: string, ads_purchase_label: string, events: array<string, bool>}
     */
    public function getSettings(Funnel $funnel): array
    {
        $settings = $funnel->settings['pixel_settings']['google'] ?? [];

        return [
            'enabled' => $settings['enabled'] ?? false,
            'ga4_measurement_id' => trim((string) ($settings['ga4_measurement_id'] ?? '')),
            'ads_conversion_id' => trim((string) ($settings['ads_conversion_id'] ?? '')),
            'ads_purchase_label' => trim((string) ($settings['ads_purchase_label'] ?? '')),
            'events' => ($settings['events'] ?? []) + self::defaultEvents(),
        ];
    }

    /**
     * Enabled only when the toggle is on AND at least one destination id is set.
     */
    public function isEnabled(Funnel $funnel): bool
    {
        $settings = $this->getSettings($funnel);

        return $settings['enabled']
            && (! empty($settings['ga4_measurement_id']) || ! empty($settings['ads_conversion_id']));
    }

    public function hasGa4(Funnel $funnel): bool
    {
        return $this->isEnabled($funnel) && ! empty($this->getSettings($funnel)['ga4_measurement_id']);
    }

    public function hasGoogleAds(Funnel $funnel): bool
    {
        return $this->isEnabled($funnel) && ! empty($this->getSettings($funnel)['ads_conversion_id']);
    }

    public function isEventEnabled(Funnel $funnel, string $eventKey): bool
    {
        return $this->getSettings($funnel)['events'][$eventKey] ?? true;
    }

    /**
     * The `send_to` target for a Google Ads purchase conversion, e.g.
     * "AW-123456789/AbCdEfGh" — or null when Ads/label are not configured.
     */
    public function adsPurchaseSendTo(Funnel $funnel): ?string
    {
        $settings = $this->getSettings($funnel);

        if (empty($settings['ads_conversion_id'])) {
            return null;
        }

        return $settings['ads_purchase_label'] !== ''
            ? $settings['ads_conversion_id'].'/'.$settings['ads_purchase_label']
            : $settings['ads_conversion_id'];
    }

    /**
     * The gtag.js base loader + config lines for the enabled destinations.
     */
    public function getGtagInitCode(Funnel $funnel): string
    {
        if (! $this->isEnabled($funnel)) {
            return '';
        }

        $settings = $this->getSettings($funnel);
        $ga4 = $settings['ga4_measurement_id'];
        $ads = $settings['ads_conversion_id'];
        $sendPageView = $this->isEventEnabled($funnel, 'page_view') ? 'true' : 'false';

        $configLines = [];
        if ($ga4 !== '') {
            $configLines[] = "gtag('config', ".json_encode($ga4).", { send_page_view: {$sendPageView} });";
        }
        if ($ads !== '') {
            $configLines[] = "gtag('config', ".json_encode($ads).');';
        }
        $config = implode("\n", $configLines);

        return <<<JS
window.dataLayer = window.dataLayer || [];
function gtag(){dataLayer.push(arguments);}
gtag('js', new Date());
{$config}
JS;
    }

    /**
     * The gtag.js loader script URL for the primary destination id.
     */
    public function getLoaderId(Funnel $funnel): string
    {
        $settings = $this->getSettings($funnel);

        return $settings['ga4_measurement_id'] ?: $settings['ads_conversion_id'];
    }
}
