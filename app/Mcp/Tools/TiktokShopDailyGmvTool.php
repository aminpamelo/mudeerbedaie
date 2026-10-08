<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\Concerns\ScopesToMarketer;
use App\Models\Platform;
use App\Models\PlatformAccount;
use App\Models\TiktokShopDailyPerformance;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Carbon;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class TiktokShopDailyGmvTool extends Tool
{
    use ScopesToMarketer;

    public const PERIODS = ['yesterday', '7d', '30d', 'this_month', 'last_month', 'custom'];

    protected string $name = 'tiktok_shop_daily_gmv';

    protected string $description = <<<'MARKDOWN'
        Daily TikTok Shop performance from TikTok Seller Center → Analytics
        (NOT TikTok Ads Manager): GMV per day (with LIVE / video / product-card
        split), orders, units sold, buyers, average order value and refunds,
        for one shop or every connected shop. Figures match Seller Center's
        Key metrics. TikTok publishes completed days only, so the newest day is
        usually yesterday. Call with no shop to compare all shops.
        MARKDOWN;

    public function handle(Request $request): Response
    {
        if (! $this->canSeeTikTokShop($request->user())) {
            return Response::error('You do not have access to TikTok Shop data.');
        }

        $validated = $request->validate([
            'shop' => 'sometimes|nullable|string|max:100',
            'period' => 'sometimes|in:'.implode(',', self::PERIODS),
            'start_date' => 'required_if:period,custom|nullable|date',
            'end_date' => 'required_if:period,custom|nullable|date|after_or_equal:start_date',
        ]);

        $shops = $this->shops();

        if (filled($validated['shop'] ?? null)) {
            $needle = mb_strtolower(trim((string) $validated['shop']));
            $shops = $shops->filter(fn (PlatformAccount $a) => (string) $a->id === $needle || str_contains(mb_strtolower((string) $a->name), $needle));

            if ($shops->isEmpty()) {
                return Response::error("No TikTok shop matches \"{$validated['shop']}\". Shops: ".$this->shops()->pluck('name')->implode(', '));
            }
        }

        $period = $validated['period'] ?? '7d';
        [$from, $to] = $this->range($period, $validated['start_date'] ?? null, $validated['end_date'] ?? null);

        $rows = TiktokShopDailyPerformance::query()
            ->whereIn('platform_account_id', $shops->pluck('id'))
            ->where('date', '>=', $from->toDateString())
            ->where('date', '<', $to->copy()->addDay()->toDateString())
            ->orderBy('date')
            ->get()
            ->groupBy('platform_account_id');

        $payload = [
            'source' => 'TikTok Seller Center Analytics (shop performance, daily)',
            'currency' => 'RM',
            'period' => ['name' => $period, 'from' => $from->toDateString(), 'to' => $to->toDateString()],
            'latest_available_date' => TiktokShopDailyPerformance::query()->whereIn('platform_account_id', $shops->pluck('id'))->max('date'),
            'shops' => $shops->map(function (PlatformAccount $shop) use ($rows): array {
                $days = ($rows->get($shop->id) ?? collect())->map(fn (TiktokShopDailyPerformance $d): array => [
                    'date' => $d->date->toDateString(),
                    'gmv' => (float) $d->gmv,
                    'gmv_live' => (float) $d->gmv_live,
                    'gmv_video' => (float) $d->gmv_video,
                    'gmv_product_card' => (float) $d->gmv_product_card,
                    'orders' => $d->orders,
                    'units_sold' => $d->units_sold,
                    'buyers' => $d->buyers,
                    'avg_order_value' => (float) $d->avg_order_value,
                    'refunds' => (float) $d->refunds,
                ])->values();

                $gmv = round($days->sum('gmv'), 2);
                $orders = (int) $days->sum('orders');

                return [
                    'shop_id' => $shop->id,
                    'shop' => $shop->name,
                    'totals' => [
                        'gmv' => $gmv,
                        'gmv_live' => round($days->sum('gmv_live'), 2),
                        'gmv_video' => round($days->sum('gmv_video'), 2),
                        'gmv_product_card' => round($days->sum('gmv_product_card'), 2),
                        'orders' => $orders,
                        'units_sold' => (int) $days->sum('units_sold'),
                        'refunds' => round($days->sum('refunds'), 2),
                        'avg_order_value' => $orders > 0 ? round($gmv / $orders, 2) : 0,
                        'days_with_data' => $days->count(),
                    ],
                    'daily' => $days,
                ];
            })->values(),
        ];

        $payload['all_shops_gmv'] = round(collect($payload['shops'])->sum('totals.gmv'), 2);

        if (collect($payload['shops'])->sum('totals.days_with_data') === 0) {
            $payload['note'] = 'No daily data stored for this window yet. Data syncs hourly; TikTok only publishes completed days.';
        }

        return Response::json($payload);
    }

    /**
     * @return \Illuminate\Support\Collection<int, PlatformAccount>
     */
    private function shops(): \Illuminate\Support\Collection
    {
        $platformId = Platform::where('slug', 'tiktok-shop')->value('id');

        return PlatformAccount::query()
            ->where('platform_id', $platformId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function range(string $period, ?string $start, ?string $end): array
    {
        return match ($period) {
            'yesterday' => [now()->subDay(), now()->subDay()],
            '30d' => [now()->subDays(30), now()->subDay()],
            'this_month' => [now()->startOfMonth(), now()],
            'last_month' => [now()->subMonthNoOverflow()->startOfMonth(), now()->subMonthNoOverflow()->endOfMonth()],
            'custom' => [Carbon::parse((string) $start), Carbon::parse((string) $end)],
            default => [now()->subDays(7), now()->subDay()],
        };
    }

    /**
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'shop' => $schema->string()->description('TikTok shop name (partial ok, e.g. "Ustaz Amar") or id. Omit for every shop.'),
            'period' => $schema->string()->enum(self::PERIODS)->description('Default 7d (last 7 completed days). Use custom with start_date/end_date.'),
            'start_date' => $schema->string()->description('YYYY-MM-DD, required when period=custom.'),
            'end_date' => $schema->string()->description('YYYY-MM-DD, required when period=custom.'),
        ];
    }
}
