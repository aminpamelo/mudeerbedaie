<?php

namespace App\Http\Middleware;

use App\Models\CekbotSession;
use Illuminate\Http\Request;

/**
 * Inertia middleware for the Cekbot WhatsApp number manager.
 *
 * Overrides the root view to `cekbot.app` and extends the shared flash bag with
 * `connectSessionId` so the page can auto-open the QR modal right after a number
 * is added.
 */
class HandleCekbotInertiaRequests extends HandleInertiaRequests
{
    protected $rootView = 'cekbot.app';

    /**
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'connectSessionId' => fn () => $request->session()->get('connectSessionId'),
            ],
            'cekbot' => [
                'total' => fn () => CekbotSession::query()->count(),
                'connected' => fn () => CekbotSession::query()->where('status', CekbotSession::STATUS_WORKING)->count(),
            ],
        ];
    }
}
