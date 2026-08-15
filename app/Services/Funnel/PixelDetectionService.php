<?php

namespace App\Services\Funnel;

use App\Models\Funnel;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Verifies that tracking pixels are actually installed on a funnel's public
 * page by fetching the rendered HTML and scanning for the init snippets
 * (fbq('init', ...) for Facebook, gtag config IDs for GA4 / Google Ads).
 */
class PixelDetectionService
{
    public function detect(Funnel $funnel): array
    {
        $url = $funnel->getPublicUrl();

        if (! $funnel->isPublished()) {
            return [
                'success' => false,
                'message' => 'Funnel is not published — publish it first so the public page exists.',
                'url' => $url,
            ];
        }

        try {
            $response = Http::timeout(15)
                ->withHeaders(['User-Agent' => 'MudeerBedaie-PixelCheck/1.0'])
                ->get($url);
        } catch (\Exception $e) {
            Log::warning('Pixel detection fetch failed', ['funnel_id' => $funnel->id, 'url' => $url, 'error' => $e->getMessage()]);

            return [
                'success' => false,
                'message' => 'Could not fetch the funnel page: '.$e->getMessage(),
                'url' => $url,
            ];
        }

        if (! $response->successful()) {
            return [
                'success' => false,
                'message' => "Funnel page returned HTTP {$response->status()} — cannot check pixels.",
                'url' => $url,
            ];
        }

        $html = $response->body();
        $pixelSettings = $funnel->settings['pixel_settings'] ?? [];

        return [
            'success' => true,
            'url' => $url,
            'facebook' => $this->checkFacebook($html, $pixelSettings['facebook'] ?? []),
            'google' => $this->checkGoogle($html, $pixelSettings['google'] ?? []),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function checkFacebook(string $html, array $settings): array
    {
        $configuredId = trim((string) ($settings['pixel_id'] ?? ''));
        $enabled = (bool) ($settings['enabled'] ?? false);

        preg_match_all("/fbq\(\s*['\"]init['\"]\s*,\s*['\"]([^'\"]+)['\"]/", $html, $matches);
        $foundIds = array_values(array_unique($matches[1] ?? []));

        $detected = $configuredId !== '' && in_array($configuredId, $foundIds, true);
        $validFormat = (bool) preg_match('/^\d{15,16}$/', $configuredId);

        return [
            'enabled' => $enabled,
            'configured_id' => $configuredId,
            'valid_format' => $validFormat,
            'found_ids' => $foundIds,
            'detected' => $detected,
            'message' => $this->facebookMessage($enabled, $configuredId, $validFormat, $foundIds, $detected),
        ];
    }

    protected function facebookMessage(bool $enabled, string $configuredId, bool $validFormat, array $foundIds, bool $detected): string
    {
        if (! $enabled) {
            return 'Facebook Pixel is disabled for this funnel.';
        }

        if ($configuredId === '') {
            return 'No Facebook Pixel ID configured.';
        }

        if (! $validFormat) {
            return "\"{$configuredId}\" does not look like a valid Pixel ID (expected 15-16 digits).";
        }

        if ($detected) {
            return "Pixel {$configuredId} is installed and firing on the page.";
        }

        if (! empty($foundIds)) {
            return 'A different pixel ('.implode(', ', $foundIds).') was found on the page instead of '.$configuredId.'.';
        }

        return 'Pixel script was NOT found on the page. Try re-saving the tracking settings.';
    }

    /**
     * @return array<string, mixed>
     */
    protected function checkGoogle(string $html, array $settings): array
    {
        $enabled = (bool) ($settings['enabled'] ?? false);
        $ga4Id = trim((string) ($settings['ga4_measurement_id'] ?? ''));
        $adsId = trim((string) ($settings['ads_conversion_id'] ?? ''));

        preg_match_all("/gtag\(\s*['\"]config['\"]\s*,\s*['\"]([^'\"]+)['\"]/", $html, $matches);
        $foundIds = array_values(array_unique($matches[1] ?? []));

        $ga4Detected = $ga4Id !== '' && in_array($ga4Id, $foundIds, true);
        $adsDetected = $adsId !== '' && in_array($adsId, $foundIds, true);
        $ga4Valid = $ga4Id === '' || preg_match('/^G-[A-Z0-9]{6,}$/i', $ga4Id) === 1;
        $adsValid = $adsId === '' || preg_match('/^AW-\d{6,}$/i', $adsId) === 1;

        $messages = [];
        if (! $enabled) {
            $messages[] = 'Google tracking is disabled for this funnel.';
        } else {
            if ($ga4Id !== '') {
                if (! preg_match('/^G-[A-Z0-9]{6,}$/i', $ga4Id)) {
                    $messages[] = "\"{$ga4Id}\" does not look like a valid GA4 Measurement ID (expected G-XXXXXXXXXX).";
                } else {
                    $messages[] = $ga4Detected
                        ? "GA4 tag {$ga4Id} is installed on the page."
                        : "GA4 tag {$ga4Id} was NOT found on the page.";
                }
            }
            if ($adsId !== '') {
                if (! preg_match('/^AW-\d{6,}$/i', $adsId)) {
                    $messages[] = "\"{$adsId}\" does not look like a valid Google Ads Conversion ID (expected AW-XXXXXXXXX).";
                } else {
                    $messages[] = $adsDetected
                        ? "Google Ads tag {$adsId} is installed on the page."
                        : "Google Ads tag {$adsId} was NOT found on the page.";
                }
            }
            if ($ga4Id === '' && $adsId === '') {
                $messages[] = 'No Google IDs configured.';
            }
        }

        return [
            'enabled' => $enabled,
            'ga4_id' => $ga4Id,
            'ads_id' => $adsId,
            'found_ids' => $foundIds,
            'ga4_detected' => $ga4Detected,
            'ads_detected' => $adsDetected,
            'ga4_valid_format' => $ga4Valid,
            'ads_valid_format' => $adsValid,
            'message' => implode(' ', $messages),
        ];
    }
}
