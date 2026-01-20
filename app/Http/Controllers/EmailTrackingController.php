<?php

namespace App\Http\Controllers;

use App\Models\BroadcastLog;
use App\Models\NotificationLog;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class EmailTrackingController extends Controller
{
    /**
     * Track email open via tracking pixel.
     * Returns a 1x1 transparent GIF image.
     */
    public function trackOpen(Request $request, string $trackingId, string $type = 'notification')
    {
        $this->recordOpen($trackingId, $type);

        return $this->transparentPixelResponse();
    }

    /**
     * Track link click and redirect to destination URL.
     */
    public function trackClick(Request $request, string $trackingId, string $type = 'notification')
    {
        $this->recordClick($trackingId, $type);

        $url = $request->query('url');

        if (!$url || !filter_var($url, FILTER_VALIDATE_URL)) {
            return redirect('/');
        }

        return redirect()->away($url);
    }

    /**
     * Record the open event for the given tracking ID.
     */
    protected function recordOpen(string $trackingId, string $type): void
    {
        try {
            if ($type === 'broadcast') {
                $log = BroadcastLog::findByTrackingId($trackingId);
            } else {
                $log = NotificationLog::findByTrackingId($trackingId);
            }

            if ($log) {
                $log->markAsOpened();
            }
        } catch (\Exception $e) {
            // Silently fail - tracking should not break user experience
            report($e);
        }
    }

    /**
     * Record the click event for the given tracking ID.
     */
    protected function recordClick(string $trackingId, string $type): void
    {
        try {
            if ($type === 'broadcast') {
                $log = BroadcastLog::findByTrackingId($trackingId);
            } else {
                $log = NotificationLog::findByTrackingId($trackingId);
            }

            if ($log) {
                $log->markAsClicked();
            }
        } catch (\Exception $e) {
            // Silently fail - tracking should not break user experience
            report($e);
        }
    }

    /**
     * Return a 1x1 transparent GIF pixel response.
     */
    protected function transparentPixelResponse(): Response
    {
        // 1x1 transparent GIF
        $pixel = base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');

        return response($pixel, 200, [
            'Content-Type' => 'image/gif',
            'Content-Length' => strlen($pixel),
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'Expires' => 'Thu, 01 Jan 1970 00:00:00 GMT',
        ]);
    }
}
