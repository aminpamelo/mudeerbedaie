<?php

namespace App\Http\Controllers\LiveHostPocket;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * Serves the installable Web App Manifest for the Live Host Pocket PWA.
 *
 * Hand-rolled to match the existing HR / CEO PWAs (no vite-plugin-pwa). The
 * surface is fixed to the host pocket — scope /live-host, the violet pocket
 * brand, and the re-tinted Bedaie mark. The companion service worker is the
 * static public/pocket-sw.js, registered with scope /live-host in
 * resources/js/livehost-pocket/app.jsx.
 */
class PocketPwaController extends Controller
{
    public function manifest(): JsonResponse
    {
        $manifest = [
            'name' => 'Hos Siaran Langsung',
            'short_name' => 'Hos',
            'description' => 'Pocket hos siaran langsung — jadual, sesi, go-live & rekap.',
            'id' => '/live-host',
            'start_url' => '/live-host',
            'scope' => '/live-host',
            'display' => 'standalone',
            'orientation' => 'portrait',
            'background_color' => '#F0F0F5',
            'theme_color' => '#F0F0F5',
            'lang' => 'ms',
            'dir' => 'ltr',
            'categories' => ['business', 'productivity'],
            'icons' => [
                ['src' => '/icons/pocket-192.svg', 'sizes' => '192x192', 'type' => 'image/svg+xml', 'purpose' => 'any maskable'],
                ['src' => '/icons/pocket-512.svg', 'sizes' => '512x512', 'type' => 'image/svg+xml', 'purpose' => 'any maskable'],
            ],
            'shortcuts' => [
                [
                    'name' => 'Go Live',
                    'short_name' => 'Go Live',
                    'url' => '/live-host/go-live',
                    'icons' => [['src' => '/icons/pocket-192.svg', 'sizes' => '192x192', 'type' => 'image/svg+xml']],
                ],
                [
                    'name' => 'Jadual',
                    'short_name' => 'Jadual',
                    'url' => '/live-host/schedule',
                    'icons' => [['src' => '/icons/pocket-192.svg', 'sizes' => '192x192', 'type' => 'image/svg+xml']],
                ],
            ],
        ];

        return response()
            ->json($manifest)
            ->header('Content-Type', 'application/manifest+json')
            ->header('Cache-Control', 'public, max-age=3600');
    }
}
