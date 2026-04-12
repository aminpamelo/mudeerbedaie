<?php

use App\Services\TikTok\TikTokUrlParser;

it('extracts video id from standard @username url', function () {
    expect(TikTokUrlParser::extractVideoId('https://www.tiktok.com/@myshop/video/7452345678901234567'))
        ->toBe('7452345678901234567');
});

it('extracts video id from mobile m.tiktok.com url', function () {
    expect(TikTokUrlParser::extractVideoId('https://m.tiktok.com/v/7452345678901234567.html'))
        ->toBe('7452345678901234567');
});

it('extracts video id when url has query params', function () {
    expect(TikTokUrlParser::extractVideoId('https://www.tiktok.com/@myshop/video/7452345678901234567?is_from_webapp=1'))
        ->toBe('7452345678901234567');
});

it('returns null for non-tiktok urls', function () {
    expect(TikTokUrlParser::extractVideoId('https://instagram.com/p/ABCDEF/'))
        ->toBeNull();
});

it('returns null for null or empty input', function () {
    expect(TikTokUrlParser::extractVideoId(null))->toBeNull();
    expect(TikTokUrlParser::extractVideoId(''))->toBeNull();
});

it('returns null for short vt.tiktok.com urls', function () {
    expect(TikTokUrlParser::extractVideoId('https://vt.tiktok.com/ZS8abcdef/'))
        ->toBeNull();
});
