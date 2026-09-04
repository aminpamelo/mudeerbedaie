<?php

namespace App\Http\Controllers\Cekbot;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessCekbotWebhookJob;
use App\Services\SettingsService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Public endpoint that receives WAHA webhooks for Cekbot.
 *
 * WAHA is configured (per-session `config.webhooks[]` or the global
 * WHATSAPP_HOOK_URL env) to POST events here. We verify an optional HMAC
 * signature, then queue each event and acknowledge fast.
 */
class WebhookController extends Controller
{
    public function handle(Request $request, SettingsService $settings): Response
    {
        $secret = $settings->get('cekbot_webhook_secret');

        if ($secret) {
            $signature = (string) $request->header('X-Webhook-Hmac', '');
            $expected = hash_hmac('sha512', $request->getContent(), $secret);

            if ($signature === '' || ! hash_equals($expected, $signature)) {
                return response('Invalid signature', 401);
            }
        }

        $data = $request->all();

        // WAHA posts a single {event, session, payload} object; tolerate a batch.
        $events = array_key_exists('event', $data) ? [$data] : array_values($data);

        foreach ($events as $event) {
            if (is_array($event) && array_key_exists('event', $event)) {
                ProcessCekbotWebhookJob::dispatch($event);
            }
        }

        return response('OK', 200);
    }
}
