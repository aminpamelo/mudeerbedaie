<?php

declare(strict_types=1);

namespace App\Services\TikTok;

class TikTokUrlParser
{
    /**
     * Extract the numeric video id from a TikTok URL.
     * Supports:
     *   - https://www.tiktok.com/@user/video/{id}
     *   - https://m.tiktok.com/v/{id}.html
     * Returns null for short URLs (vt.tiktok.com/...) which require HTTP redirect resolution.
     */
    public static function extractVideoId(?string $url): ?string
    {
        if (empty($url)) {
            return null;
        }

        if (! str_contains($url, 'tiktok.com')) {
            return null;
        }

        if (str_contains($url, 'vt.tiktok.com')) {
            return null;
        }

        if (preg_match('#/video/(\d+)#', $url, $matches)) {
            return $matches[1];
        }

        if (preg_match('#/v/(\d+)\.html#', $url, $matches)) {
            return $matches[1];
        }

        return null;
    }
}
