<?php

declare(strict_types=1);

namespace App\Services\TikTok\Sdk;

use EcomPHP\TiktokShop\Resources\Analytics;
use GuzzleHttp\RequestOptions;

class AnalyticsExtended extends Analytics
{
    protected $minimum_version = 202508;

    /**
     * GET /analytics/{version}/shop_lives/performance
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function getShopLivePerformanceList(array $params = []): array
    {
        return $this->call('GET', 'shop_lives/performance', [
            RequestOptions::QUERY => $params,
        ]);
    }

    /**
     * GET /analytics/{version}/shop_lives/overview_performance
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function getShopLivePerformanceOverview(array $params = []): array
    {
        return $this->call('GET', 'shop_lives/overview_performance', [
            RequestOptions::QUERY => $params,
        ]);
    }
}
