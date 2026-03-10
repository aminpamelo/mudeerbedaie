<?php

namespace App\Services\WhatsApp;

use App\Models\WhatsAppCostAnalytics;
use App\Services\SettingsService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppCostService
{
    public function __construct(
        private SettingsService $settings,
    ) {}

    /**
     * Fetch pricing analytics data points from Meta Graph API.
     *
     * Meta returns: { data: [{ data_points: [{ start, end, country, pricing_category, volume, cost }] }] }
     *
     * @return array<int, array<string, mixed>>
     *
     * @throws \RuntimeException
     */
    public function fetchFromMeta(Carbon $start, Carbon $end): array
    {
        ['wabaId' => $wabaId, 'accessToken' => $accessToken, 'apiVersion' => $apiVersion] = $this->getMetaCredentials();

        $response = Http::withToken($accessToken)
            ->timeout(30)
            ->get("https://graph.facebook.com/{$apiVersion}/{$wabaId}/pricing_analytics", [
                'start' => $start->startOfDay()->timestamp,
                'end' => $end->endOfDay()->timestamp,
                'granularity' => 'DAILY',
                'metric_types' => ['COST', 'VOLUME'],
                'dimensions' => ['COUNTRY', 'PRICING_CATEGORY'],
            ]);

        if (! $response->successful()) {
            $error = $response->json('error.message', 'Unknown error');
            Log::error('WhatsAppCostService: Failed to fetch pricing analytics', [
                'error' => $error,
                'status' => $response->status(),
            ]);

            throw new \RuntimeException('Failed to fetch pricing analytics: '.$error);
        }

        // Meta nests data points inside data[0].data_points
        $data = $response->json('data', []);
        $dataPoints = [];

        foreach ($data as $entry) {
            foreach ($entry['data_points'] ?? [] as $point) {
                $dataPoints[] = $point;
            }
        }

        return $dataPoints;
    }

    /**
     * Sync analytics from Meta API into the database.
     * When no date is given, syncs the last 7 days to catch any delayed data.
     */
    public function syncDailyAnalytics(?Carbon $date = null): int
    {
        $usdToMyr = (float) config('whatsapp-pricing.usd_to_myr', 4.50);

        // If specific date, sync just that day; otherwise sync last 7 days
        $start = $date ? $date->copy() : now()->subDays(7);
        $end = $date ? $date->copy() : now()->subDay();

        try {
            $dataPoints = $this->fetchFromMeta($start, $end);
        } catch (\RuntimeException $e) {
            Log::warning('WhatsAppCostService: Skipping sync due to API error', [
                'error' => $e->getMessage(),
            ]);

            return 0;
        }

        $count = 0;

        foreach ($dataPoints as $point) {
            $pointDate = Carbon::createFromTimestamp($point['start'])->toDateString();
            $countryCode = $point['country'] ?? config('whatsapp-pricing.default_country', 'MY');
            $category = strtoupper($point['pricing_category'] ?? 'UNKNOWN');
            $volume = (int) ($point['volume'] ?? 0);
            $costUsd = (float) ($point['cost'] ?? 0);

            WhatsAppCostAnalytics::updateOrCreate(
                [
                    'date' => $pointDate,
                    'country_code' => $countryCode,
                    'pricing_category' => $category,
                ],
                [
                    'message_volume' => $volume,
                    'cost_usd' => $costUsd,
                    'cost_myr' => round($costUsd * $usdToMyr, 4),
                    'granularity' => 'DAILY',
                    'synced_at' => now(),
                ]
            );

            $count++;
        }

        Log::info('WhatsAppCostService: Analytics synced', [
            'range' => $start->toDateString().' to '.$end->toDateString(),
            'records' => $count,
        ]);

        return $count;
    }

    /**
     * Get estimated cost for a message category in USD.
     */
    public function estimateCost(string $category, ?string $country = null): float
    {
        $country = $country ?? config('whatsapp-pricing.default_country', 'MY');
        $rates = config("whatsapp-pricing.rates.{$country}", []);

        return (float) ($rates[strtolower($category)] ?? 0);
    }

    /**
     * Convert USD to MYR using configured rate.
     */
    public function convertToMyr(float $usd): float
    {
        return round($usd * (float) config('whatsapp-pricing.usd_to_myr', 4.50), 4);
    }

    /**
     * Get dashboard data for a date range.
     *
     * @return array{summary: array, categories: array, dailyTrend: Collection, lastSyncedAt: ?string}
     */
    public function getDashboardData(Carbon $start, Carbon $end): array
    {
        return [
            'summary' => $this->getSummaryCards($start, $end),
            'categories' => $this->getCategoryBreakdown($start, $end),
            'dailyTrend' => $this->getDailyTrend($start, $end),
            'lastSyncedAt' => WhatsAppCostAnalytics::query()->max('synced_at'),
        ];
    }

    /**
     * Get summary card data.
     *
     * @return array{totalCostMyr: float, totalMessages: int, avgCostPerMessage: float, freeServiceMessages: int}
     */
    public function getSummaryCards(Carbon $start, Carbon $end): array
    {
        $analytics = WhatsAppCostAnalytics::query()
            ->dateRange($start, $end)
            ->get();

        $totalCostMyr = (float) $analytics->sum('cost_myr');
        $totalMessages = (int) $analytics->sum('message_volume');
        $freeServiceMessages = (int) $analytics->where('pricing_category', 'SERVICE')->sum('message_volume');

        $paidMessages = $totalMessages - $freeServiceMessages;
        $avgCostPerMessage = $paidMessages > 0 ? round($totalCostMyr / $paidMessages, 4) : 0;

        return [
            'totalCostMyr' => round($totalCostMyr, 2),
            'totalCostUsd' => round((float) $analytics->sum('cost_usd'), 2),
            'totalMessages' => $totalMessages,
            'avgCostPerMessage' => $avgCostPerMessage,
            'freeServiceMessages' => $freeServiceMessages,
        ];
    }

    /**
     * Get cost breakdown by category.
     *
     * @return array<string, array{costMyr: float, costUsd: float, volume: int}>
     */
    public function getCategoryBreakdown(Carbon $start, Carbon $end): array
    {
        $analytics = WhatsAppCostAnalytics::query()
            ->dateRange($start, $end)
            ->get()
            ->groupBy('pricing_category');

        $categories = [];
        foreach (['MARKETING', 'UTILITY', 'AUTHENTICATION', 'SERVICE'] as $category) {
            $group = $analytics->get($category, collect());
            $categories[$category] = [
                'costMyr' => round((float) $group->sum('cost_myr'), 2),
                'costUsd' => round((float) $group->sum('cost_usd'), 6),
                'volume' => (int) $group->sum('message_volume'),
            ];
        }

        return $categories;
    }

    /**
     * Get daily cost trend data.
     *
     * @return Collection<int, array{date: string, costMyr: float, volume: int, categories: array}>
     */
    public function getDailyTrend(Carbon $start, Carbon $end): Collection
    {
        return WhatsAppCostAnalytics::query()
            ->dateRange($start, $end)
            ->orderBy('date')
            ->get()
            ->groupBy(fn ($item) => $item->date->toDateString())
            ->map(function ($dayEntries, $date) {
                $categories = [];
                foreach ($dayEntries as $entry) {
                    $categories[$entry->pricing_category] = [
                        'costMyr' => round((float) $entry->cost_myr, 2),
                        'volume' => (int) $entry->message_volume,
                    ];
                }

                return [
                    'date' => $date,
                    'costMyr' => round((float) $dayEntries->sum('cost_myr'), 2),
                    'volume' => (int) $dayEntries->sum('message_volume'),
                    'categories' => $categories,
                ];
            })
            ->values();
    }

    /**
     * Get Meta API credentials.
     *
     * @return array{wabaId: string, accessToken: string, apiVersion: string}
     *
     * @throws \RuntimeException
     */
    private function getMetaCredentials(): array
    {
        $wabaId = $this->settings->get('meta_waba_id');
        $accessToken = $this->settings->get('meta_access_token');
        $apiVersion = $this->settings->get('meta_api_version', 'v21.0');

        if (! $wabaId || ! $accessToken) {
            throw new \RuntimeException('Meta WABA ID and access token are required for cost analytics');
        }

        return compact('wabaId', 'accessToken', 'apiVersion');
    }
}
