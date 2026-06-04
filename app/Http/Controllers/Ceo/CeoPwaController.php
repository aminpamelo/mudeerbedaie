<?php

namespace App\Http\Controllers\Ceo;

use App\Http\Controllers\Controller;
use App\Services\SettingsService;
use Illuminate\Http\JsonResponse;

/**
 * Serves the installable Web App Manifest for the CEO Overview PWA.
 *
 * Hand-rolled to match the existing HR / pocket PWAs (no vite-plugin-pwa). The
 * app name follows the configured site name so the installed icon label matches
 * the sidebar brand; everything else is fixed to the CEO surface (scope /ceo,
 * glass-vibrant theme). The companion service worker is the static
 * public/ceo-sw.js, registered with scope /ceo in resources/js/ceo/app.jsx.
 */
class CeoPwaController extends Controller
{
    public function manifest(SettingsService $settings): JsonResponse
    {
        $brand = (string) $settings->get('site_name', config('app.name', 'Mudeer Bedaie'));

        $manifest = [
            'name' => $brand.' · CEO',
            'short_name' => 'CEO',
            'description' => 'Executive operational overview across every department.',
            'id' => '/ceo',
            'start_url' => '/ceo',
            'scope' => '/ceo',
            'display' => 'standalone',
            'orientation' => 'any',
            'background_color' => '#EEF2FF',
            'theme_color' => '#6366F1',
            'lang' => app()->getLocale(),
            'dir' => 'ltr',
            'icons' => [
                ['src' => '/icons/ceo-192.svg', 'sizes' => '192x192', 'type' => 'image/svg+xml', 'purpose' => 'any maskable'],
                ['src' => '/icons/ceo-512.svg', 'sizes' => '512x512', 'type' => 'image/svg+xml', 'purpose' => 'any maskable'],
            ],
        ];

        return response()
            ->json($manifest)
            ->header('Content-Type', 'application/manifest+json')
            ->header('Cache-Control', 'public, max-age=3600');
    }
}
