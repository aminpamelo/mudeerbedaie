<?php

namespace App\Http\Controllers\Cekbot;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessCekbotCloudWebhookJob;
use App\Models\CekbotSession;
use App\Services\SettingsService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Public endpoint for Meta's official WhatsApp Cloud API webhooks belonging to
 * Cekbot numbers.
 *
 * GET performs Meta's subscription challenge (echo hub.challenge when the verify
 * token matches). POST validates the X-Hub-Signature-256 against the app secret
 * (per-number override or global), then queues each change for the Cekbot
 * pipeline. Signature verification is per-number so it can't reuse the single
 * global middleware — hence the controller-level checks here.
 */
class CloudWebhookController extends Controller
{
    public function __construct(private SettingsService $settings) {}

    public function verify(Request $request): Response
    {
        $mode = $request->query('hub_mode');
        $token = (string) $request->query('hub_verify_token', '');
        $challenge = (string) $request->query('hub_challenge', '');

        if ($mode === 'subscribe' && $token !== '' && $this->tokenMatches($token)) {
            return response($challenge, 200);
        }

        return response('Forbidden', 403);
    }

    public function handle(Request $request): Response
    {
        if (! $this->signatureValid($request)) {
            return response('Invalid signature', 401);
        }

        foreach ((array) $request->input('entry', []) as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                $value = $change['value'] ?? null;

                if (is_array($value)) {
                    ProcessCekbotCloudWebhookJob::dispatch($value);
                }
            }
        }

        return response('OK', 200);
    }

    /**
     * Accept the global verify token, or any Cloud API number's own token.
     */
    private function tokenMatches(string $token): bool
    {
        $global = $this->settings->get('meta_verify_token')
            ?: config('services.whatsapp.meta.verify_token');

        if ($global && hash_equals((string) $global, $token)) {
            return true;
        }

        return CekbotSession::query()
            ->where('provider', CekbotSession::PROVIDER_CLOUD_API)
            ->whereNotNull('verify_token')
            ->pluck('verify_token')
            ->contains(fn ($stored) => $stored && hash_equals((string) $stored, $token));
    }

    /**
     * Validate the payload signature against every candidate app secret (global
     * + per-number overrides). When no secret is configured anywhere we accept —
     * parity with the WAHA webhook's optional HMAC.
     */
    private function signatureValid(Request $request): bool
    {
        $secrets = collect([
            $this->settings->get('meta_app_secret') ?: config('services.whatsapp.meta.app_secret'),
        ])->merge(
            CekbotSession::query()
                ->where('provider', CekbotSession::PROVIDER_CLOUD_API)
                ->get()
                ->map(fn (CekbotSession $s) => $s->app_secret)
        )->filter()->unique()->values();

        if ($secrets->isEmpty()) {
            return true;
        }

        $signature = (string) $request->header('X-Hub-Signature-256', '');

        if ($signature === '') {
            return false;
        }

        $provided = str_replace('sha256=', '', $signature);
        $body = $request->getContent();

        foreach ($secrets as $secret) {
            if (hash_equals(hash_hmac('sha256', $body, (string) $secret), $provided)) {
                return true;
            }
        }

        return false;
    }
}
