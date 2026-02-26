<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Funnel;
use App\Models\FunnelSession;
use App\Services\Funnel\FacebookPixelService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FunnelPixelController extends Controller
{
    public function __construct(
        protected FacebookPixelService $pixelService
    ) {}

    /**
     * Track a pixel event from the client-side.
     * This endpoint is called by the browser JavaScript to send events server-side.
     */
    public function trackEvent(Request $request, string $funnelUuid): JsonResponse
    {
        $request->validate([
            'event_name' => ['required', 'string', 'in:AddToCart,Lead,ViewContent,InitiateCheckout,Custom'],
            'event_id' => ['nullable', 'string'],
            'session_uuid' => ['nullable', 'string'],
            'data' => ['nullable', 'array'],
        ]);

        $funnel = Funnel::where('uuid', $funnelUuid)
            ->where('status', 'published')
            ->first();

        if (! $funnel) {
            return response()->json(['success' => false, 'message' => 'Funnel not found'], 404);
        }

        if (! $this->pixelService->isEnabled($funnel)) {
            return response()->json(['success' => false, 'message' => 'Pixel not enabled'], 400);
        }

        $eventName = $request->input('event_name');
        $eventId = $request->input('event_id');
        $data = $request->input('data', []);
        $sessionUuid = $request->input('session_uuid');

        // Get session if provided
        $session = null;
        if ($sessionUuid) {
            $session = FunnelSession::where('uuid', $sessionUuid)
                ->where('funnel_id', $funnel->id)
                ->first();
        }

        $success = false;

        switch ($eventName) {
            case 'AddToCart':
                $eventId = $this->pixelService->trackAddToCart(
                    $funnel,
                    $request,
                    [
                        'content_ids' => [(string) ($data['product_id'] ?? '')],
                        'value' => ($data['price'] ?? 0) * ($data['quantity'] ?? 1),
                        'currency' => 'MYR',
                        'contents' => [[
                            'id' => (string) ($data['product_id'] ?? ''),
                            'quantity' => $data['quantity'] ?? 1,
                            'item_price' => $data['price'] ?? 0,
                        ]],
                    ],
                    $session,
                    $eventId
                );
                $success = $eventId !== null;
                break;

            case 'Lead':
                $eventId = $this->pixelService->trackLead(
                    $funnel,
                    $request,
                    [
                        'email' => $data['email'] ?? null,
                        'name' => $data['name'] ?? null,
                        'phone' => $data['phone'] ?? null,
                        'value' => $data['value'] ?? null,
                        'content_name' => $data['content_name'] ?? $funnel->name,
                    ],
                    $session,
                    $eventId
                );
                $success = $eventId !== null;
                break;

            case 'ViewContent':
                $eventId = $this->pixelService->trackViewContent(
                    $funnel,
                    $request,
                    [
                        'content_ids' => $data['content_ids'] ?? [],
                        'content_name' => $data['content_name'] ?? '',
                        'value' => $data['value'] ?? 0,
                        'currency' => $data['currency'] ?? 'MYR',
                    ],
                    $session,
                    $eventId
                );
                $success = $eventId !== null;
                break;

            case 'InitiateCheckout':
                $eventId = $this->pixelService->trackInitiateCheckout(
                    $funnel,
                    $request,
                    [
                        'content_ids' => $data['content_ids'] ?? [],
                        'value' => $data['value'] ?? 0,
                        'currency' => $data['currency'] ?? 'MYR',
                        'num_items' => $data['num_items'] ?? 1,
                    ],
                    $session,
                    $eventId
                );
                $success = $eventId !== null;
                break;

            default:
                return response()->json(['success' => false, 'message' => 'Unsupported event'], 400);
        }

        return response()->json([
            'success' => $success,
            'event_id' => $eventId,
        ]);
    }

    /**
     * Test pixel connection (admin only).
     */
    public function testConnection(Request $request, string $funnelUuid): JsonResponse
    {
        $funnel = Funnel::where('uuid', $funnelUuid)->firstOrFail();

        // Verify user owns this funnel or is admin
        if ($funnel->user_id !== auth()->id() && ! auth()->user()?->isAdmin()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $result = $this->pixelService->testConnection($funnel);

        return response()->json($result);
    }
}
