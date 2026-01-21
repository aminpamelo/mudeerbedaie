<?php

namespace App\Services;

use App\Models\BroadcastLog;
use App\Models\NotificationLog;

class EmailTrackingService
{
    /**
     * Generate tracking pixel HTML for notification logs.
     */
    public static function getTrackingPixelForNotification(NotificationLog $log): string
    {
        return self::generateTrackingPixel($log->tracking_id, 'notification');
    }

    /**
     * Generate tracking pixel HTML for broadcast logs.
     */
    public static function getTrackingPixelForBroadcast(BroadcastLog $log): string
    {
        return self::generateTrackingPixel($log->tracking_id, 'broadcast');
    }

    /**
     * Generate the tracking pixel HTML.
     */
    protected static function generateTrackingPixel(string $trackingId, string $type): string
    {
        $url = route('email.track.open', ['trackingId' => $trackingId, 'type' => $type]);

        return sprintf(
            '<img src="%s" alt="" width="1" height="1" style="display:none;width:1px;height:1px;border:0;" />',
            htmlspecialchars($url, ENT_QUOTES, 'UTF-8')
        );
    }

    /**
     * Generate a tracked URL that redirects through our tracking endpoint.
     */
    public static function getTrackedUrl(string $originalUrl, string $trackingId, string $type = 'notification'): string
    {
        return route('email.track.click', [
            'trackingId' => $trackingId,
            'type' => $type,
            'url' => $originalUrl,
        ]);
    }

    /**
     * Inject tracking pixel into HTML email content.
     */
    public static function injectTrackingPixel(string $htmlContent, string $trackingId, string $type = 'notification'): string
    {
        $pixel = self::generateTrackingPixel($trackingId, $type);

        // Try to inject before closing body tag
        if (stripos($htmlContent, '</body>') !== false) {
            return str_ireplace('</body>', $pixel . '</body>', $htmlContent);
        }

        // Otherwise append to the end
        return $htmlContent . $pixel;
    }

    /**
     * Replace links in HTML content with tracked URLs.
     */
    public static function trackLinks(string $htmlContent, string $trackingId, string $type = 'notification'): string
    {
        // Pattern to match href attributes in anchor tags
        $pattern = '/<a\s+([^>]*?)href=["\']([^"\']+)["\']([^>]*?)>/i';

        return preg_replace_callback($pattern, function ($matches) use ($trackingId, $type) {
            $before = $matches[1];
            $url = $matches[2];
            $after = $matches[3];

            // Skip tracking for special URLs (mailto, tel, #anchors, javascript)
            if (preg_match('/^(mailto:|tel:|#|javascript:)/i', $url)) {
                return $matches[0];
            }

            // Skip tracking pixel URLs
            if (strpos($url, 'track/') !== false) {
                return $matches[0];
            }

            $trackedUrl = self::getTrackedUrl($url, $trackingId, $type);

            return sprintf('<a %shref="%s"%s>', $before, htmlspecialchars($trackedUrl, ENT_QUOTES, 'UTF-8'), $after);
        }, $htmlContent);
    }

    /**
     * Apply both pixel injection and link tracking to HTML content.
     */
    public static function applyTracking(string $htmlContent, string $trackingId, string $type = 'notification'): string
    {
        // First track the links
        $content = self::trackLinks($htmlContent, $trackingId, $type);

        // Then inject the tracking pixel
        return self::injectTrackingPixel($content, $trackingId, $type);
    }
}
