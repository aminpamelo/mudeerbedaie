<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyWhatsAppWebhook
{
    /**
     * Validate the X-Hub-Signature-256 header from Meta's WhatsApp webhook.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $signature = $request->header('X-Hub-Signature-256');

        if (! $signature) {
            return response('Forbidden', 403);
        }

        $expectedHash = hash_hmac(
            'sha256',
            $request->getContent(),
            config('services.whatsapp.meta.app_secret')
        );

        $actualHash = str_replace('sha256=', '', $signature);

        if (! hash_equals($expectedHash, $actualHash)) {
            return response('Forbidden', 403);
        }

        return $next($request);
    }
}
