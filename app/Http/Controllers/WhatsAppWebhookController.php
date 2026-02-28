<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessWhatsAppWebhookJob;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class WhatsAppWebhookController extends Controller
{
    /**
     * Handle Meta's webhook verification challenge (GET).
     */
    public function verify(Request $request): Response
    {
        $mode = $request->query('hub_mode');
        $token = $request->query('hub_verify_token');
        $challenge = $request->query('hub_challenge');

        if ($mode === 'subscribe' && $token === config('services.whatsapp.meta.verify_token')) {
            return response($challenge, 200);
        }

        return response('Forbidden', 403);
    }

    /**
     * Handle incoming webhook payloads (POST).
     *
     * Dispatches the payload to a queued job for processing
     * and returns 200 immediately to acknowledge receipt.
     */
    public function handle(Request $request): Response
    {
        ProcessWhatsAppWebhookJob::dispatch($request->all());

        return response('OK', 200);
    }
}
