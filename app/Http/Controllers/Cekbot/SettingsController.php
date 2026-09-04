<?php

namespace App\Http\Controllers\Cekbot;

use App\Http\Controllers\Controller;
use App\Services\SettingsService;
use App\Services\WhatsApp\WahaSessionManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function __construct(private SettingsService $settings) {}

    public function index(WahaSessionManager $waha): Response
    {
        return Inertia::render('Settings/Index', [
            'config' => [
                'waha_url' => $this->settings->get('cekbot_waha_url', ''),
                'waha_key' => $this->settings->get('cekbot_waha_key', ''),
                'webhook_secret' => $this->settings->get('cekbot_webhook_secret', ''),
            ],
            'webhookUrl' => url('/api/cekbot/webhook'),
            'status' => $this->connectionStatus($waha),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'waha_url' => 'nullable|url|max:255',
            'waha_key' => 'nullable|string|max:1024',
            'webhook_secret' => 'nullable|string|max:255',
        ]);

        $this->settings->set('cekbot_waha_url', rtrim((string) ($validated['waha_url'] ?? ''), '/'), 'string', 'cekbot');
        $this->settings->set('cekbot_waha_key', (string) ($validated['waha_key'] ?? ''), 'string', 'cekbot');
        $this->settings->set('cekbot_webhook_secret', (string) ($validated['webhook_secret'] ?? ''), 'string', 'cekbot');

        Cache::forget('cekbot.waha.writes_allowed');

        return back()->with('success', 'Sambungan WAHA dikemas kini.');
    }

    /**
     * @return array<string, mixed>
     */
    private function connectionStatus(WahaSessionManager $waha): array
    {
        if (! $waha->isConfigured()) {
            return ['configured' => false, 'reachable' => false, 'version' => null, 'tier' => null, 'sessions' => 0];
        }

        $version = $waha->version();

        $sessions = 0;
        $reachable = $version !== null;
        try {
            $sessions = count($waha->listSessions());
            $reachable = true;
        } catch (\Throwable $e) {
            // keep reachable from version()
        }

        return [
            'configured' => true,
            'reachable' => $reachable,
            'version' => $version['version'] ?? null,
            'tier' => $version['tier'] ?? null,
            'engine' => $version['engine'] ?? null,
            'sessions' => $sessions,
            'serverUrl' => $waha->serverUrl(),
        ];
    }
}
