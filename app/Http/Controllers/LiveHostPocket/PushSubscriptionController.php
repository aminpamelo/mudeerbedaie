<?php

namespace App\Http\Controllers\LiveHostPocket;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Web Push subscription lifecycle for the Live Host Pocket PWA.
 *
 * Mirrors the HR push-subscription endpoints but lives on the web/session
 * stack (the Pocket is Inertia, not the HR API). The browser PushSubscription
 * is persisted against the authenticated host via the HasPushSubscriptions
 * trait on User, then targeted by WebPushChannel when a Pocket notification
 * fires.
 */
class PushSubscriptionController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'endpoint' => ['required', 'url'],
            'keys.auth' => ['required', 'string'],
            'keys.p256dh' => ['required', 'string'],
            'content_encoding' => ['nullable', 'in:aesgcm,aes128gcm'],
        ]);

        $request->user()->updatePushSubscription(
            $validated['endpoint'],
            $validated['keys']['p256dh'],
            $validated['keys']['auth'],
            $validated['content_encoding'] ?? 'aesgcm'
        );

        return response()->json(['message' => 'Langganan notifikasi disimpan.']);
    }

    public function destroy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'endpoint' => ['required', 'url'],
        ]);

        $request->user()->deletePushSubscription($validated['endpoint']);

        return response()->json(['message' => 'Langganan notifikasi dibuang.']);
    }
}
