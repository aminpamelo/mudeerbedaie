<?php

namespace App\Console\Commands;

use App\Models\Funnel;
use App\Services\Funnel\PixelDetectionService;
use Illuminate\Console\Command;

/**
 * Checks every published funnel's tracking pixels against its live page and
 * records the result in settings.pixel_settings.health, so the Studio can
 * warn about funnels whose ads are running with broken tracking.
 */
class FunnelPixelHealthCheck extends Command
{
    protected $signature = 'funnel:pixel-health {--funnel= : Only check this funnel ID}';

    protected $description = 'Verify tracking pixels are installed on every published funnel and record health status';

    public function handle(PixelDetectionService $detectionService): int
    {
        $funnels = Funnel::query()
            ->where('status', 'published')
            ->when($this->option('funnel'), fn ($q, $id) => $q->where('id', $id))
            ->get();

        $checked = 0;
        $warnings = 0;

        foreach ($funnels as $funnel) {
            $pixelSettings = $funnel->settings['pixel_settings'] ?? [];
            $facebookEnabled = (bool) data_get($pixelSettings, 'facebook.enabled');
            $googleEnabled = (bool) data_get($pixelSettings, 'google.enabled');

            $settings = $funnel->settings ?? [];

            if (! $facebookEnabled && ! $googleEnabled) {
                // Nothing to check — clear any stale health record.
                if (isset($settings['pixel_settings']['health'])) {
                    unset($settings['pixel_settings']['health']);
                    $funnel->update(['settings' => $settings]);
                }

                continue;
            }

            $result = $detectionService->detect($funnel);
            $issues = [];

            if (! $result['success']) {
                $issues[] = ['platform' => 'page', 'message' => $result['message']];
            } else {
                $fb = $result['facebook'];
                // An invalid pixel ID (e.g. an email pasted by mistake) still
                // renders into the page, so check format as well as presence.
                if ($facebookEnabled && (! ($fb['detected'] ?? false) || ! ($fb['valid_format'] ?? true))) {
                    $issues[] = ['platform' => 'facebook', 'message' => $fb['message']];
                }

                $google = $result['google'];
                $googleInstalled = ($google['ga4_detected'] ?? false) || ($google['ads_detected'] ?? false);
                $googleFormatsOk = ($google['ga4_valid_format'] ?? true) && ($google['ads_valid_format'] ?? true);
                if ($googleEnabled && (! $googleInstalled || ! $googleFormatsOk)) {
                    $issues[] = ['platform' => 'google', 'message' => $google['message']];
                }
            }

            $settings['pixel_settings']['health'] = [
                'checked_at' => now()->toIso8601String(),
                'status' => empty($issues) ? 'ok' : 'warning',
                'issues' => $issues,
            ];

            $funnel->update(['settings' => $settings]);

            $checked++;
            if (! empty($issues)) {
                $warnings++;
                $this->warn("{$funnel->name}: ".collect($issues)->pluck('message')->implode(' | '));
            } else {
                $this->line("{$funnel->name}: OK");
            }
        }

        $this->info("Checked {$checked} funnel(s), {$warnings} with pixel problems.");

        return self::SUCCESS;
    }
}
