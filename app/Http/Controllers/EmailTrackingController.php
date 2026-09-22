<?php

namespace App\Http\Controllers;

use App\Models\BroadcastLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class EmailTrackingController extends Controller
{
    /**
     * 1x1 transparent tracking pixel. Records an open for the delivery log, then
     * returns the image regardless so the email always renders.
     */
    public function open(BroadcastLog $log): Response
    {
        $log->markOpened();

        $pixel = base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');

        return response($pixel, 200, [
            'Content-Type' => 'image/gif',
            'Content-Length' => (string) strlen($pixel),
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
        ]);
    }

    /**
     * Records a click for the delivery log, then redirects to the original URL.
     * The target lives inside the signed payload, so it can't be tampered with.
     */
    public function click(Request $request, BroadcastLog $log): RedirectResponse
    {
        $target = (string) $request->query('u', '');

        if (! preg_match('#^https?://#i', $target)) {
            abort(404);
        }

        $log->markClicked();

        return redirect()->away($target);
    }
}
