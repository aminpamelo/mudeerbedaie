<?php

declare(strict_types=1);

use App\Services\TikTok\Sdk\AnalyticsExtended;

it('defines getShopLivePerformanceList and getShopLivePerformanceOverview methods', function () {
    expect(method_exists(AnalyticsExtended::class, 'getShopLivePerformanceList'))->toBeTrue();
    expect(method_exists(AnalyticsExtended::class, 'getShopLivePerformanceOverview'))->toBeTrue();
});

it('declares minimum_version 202509', function () {
    $reflection = new ReflectionClass(AnalyticsExtended::class);
    $prop = $reflection->getProperty('minimum_version');
    expect((int) $prop->getValue($reflection->newInstance()))->toBe(202509);
});
