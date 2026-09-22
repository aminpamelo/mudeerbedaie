<?php

namespace App\Services\Broadcast;

use App\Models\BroadcastLog;
use Illuminate\Support\Facades\URL;

class BroadcastTrackingInjector
{
    /**
     * Add open (pixel) and click (redirect) tracking to outgoing email HTML.
     * Tracking targets are signed so they cannot be tampered with.
     */
    public function inject(string $html, BroadcastLog $log): string
    {
        $html = preg_replace_callback(
            '/href\s*=\s*(["\'])(https?:\/\/[^"\'\s]+)\1/i',
            function ($matches) use ($log) {
                $tracked = URL::signedRoute('email.track.click', [
                    'log' => $log->id,
                    'u' => $matches[2],
                ]);

                return 'href='.$matches[1].$tracked.$matches[1];
            },
            $html
        ) ?? $html;

        $pixel = '<img src="'.URL::signedRoute('email.track.open', ['log' => $log->id])
            .'" width="1" height="1" alt="" style="display:none;max-height:0;overflow:hidden" />';

        if (stripos($html, '</body>') !== false) {
            return preg_replace('/<\/body>/i', $pixel.'</body>', $html, 1);
        }

        return $html.$pixel;
    }
}
