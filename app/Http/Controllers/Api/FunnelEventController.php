<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FunnelSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FunnelEventController extends Controller
{
    /**
     * Track button click on thank you pages.
     */
    public function trackButtonClick(Request $request): JsonResponse
    {
        $request->validate([
            'session_uuid' => 'required|string',
            'step_id' => 'required|integer',
            'button_url' => 'nullable|string|max:2048',
            'button_text' => 'nullable|string|max:255',
        ]);

        $session = FunnelSession::where('uuid', $request->session_uuid)->first();

        if (! $session) {
            return response()->json(['success' => false, 'message' => 'Session not found'], 404);
        }

        // Get the step to verify it's a thank you page
        $step = $session->funnel->steps()->find($request->step_id);

        if (! $step || $step->type !== 'thankyou') {
            return response()->json(['success' => false, 'message' => 'Invalid step'], 400);
        }

        // Track the button click event (only once per session)
        $session->trackEventOnce('thankyou_button_click', [
            'button_url' => $request->button_url,
            'button_text' => $request->button_text,
        ], $step);

        return response()->json(['success' => true]);
    }
}
