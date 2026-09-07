<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\Concerns\ScopesToMarketer;
use App\Models\FacebookAdAccount;
use App\Models\FacebookAdInsight;
use App\Services\Funnel\FunnelStudioReportService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class FacebookAdsInsightsTool extends Tool
{
    use ScopesToMarketer;

    protected string $name = 'facebook_ads_insights';

    protected string $description = <<<'MARKDOWN'
        Pull Facebook Ads performance for the current marketer's connected ad
        accounts over the last N days (default 30): spend, spend including 8%
        Malaysian SST, impressions, clicks, and CTR — per account and in total.
        Optionally break the totals down by day. Use this to review ad
        performance before planning a landing page.
        MARKDOWN;

    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'days' => 'sometimes|integer|min:1|max:90',
            'group_by_day' => 'sometimes|boolean',
        ]);

        $days = (int) ($validated['days'] ?? 30);
        $groupByDay = (bool) ($validated['group_by_day'] ?? false);
        $sst = FunnelStudioReportService::SST_RATE;
        $from = now()->subDays($days - 1)->startOfDay()->toDateString();

        $user = $request->user();
        $accountIds = $this->adAccountIdsFor($user);

        if (empty($accountIds)) {
            return Response::json([
                'message' => 'No Facebook ad accounts are connected for you yet. Connect a Business Manager in Funnel Studio → Facebook Ads first.',
                'days' => $days,
                'accounts' => [],
                'totals' => ['spend' => 0, 'spend_with_sst' => 0, 'impressions' => 0, 'clicks' => 0],
            ]);
        }

        $perAccount = FacebookAdInsight::query()
            ->whereIn('facebook_ad_account_id', $accountIds)
            ->where('date', '>=', $from)
            ->selectRaw('facebook_ad_account_id, SUM(spend) as spend, SUM(impressions) as impressions, SUM(clicks) as clicks')
            ->groupBy('facebook_ad_account_id')
            ->get()
            ->keyBy('facebook_ad_account_id');

        $accounts = FacebookAdAccount::query()
            ->whereIn('id', $accountIds)
            ->with('connection:id,name')
            ->get(['id', 'account_id', 'name', 'currency', 'facebook_ad_connection_id'])
            ->map(function (FacebookAdAccount $account) use ($perAccount, $sst) {
                $spend = (float) ($perAccount[$account->id]->spend ?? 0);
                $impressions = (int) ($perAccount[$account->id]->impressions ?? 0);
                $clicks = (int) ($perAccount[$account->id]->clicks ?? 0);

                return [
                    'account_id' => $account->account_id,
                    'name' => $account->name,
                    'connection' => $account->connection?->name,
                    'currency' => $account->currency ?? 'MYR',
                    'spend' => round($spend, 2),
                    'spend_with_sst' => round($spend * (1 + $sst), 2),
                    'impressions' => $impressions,
                    'clicks' => $clicks,
                    'ctr' => $impressions > 0 ? round(($clicks / $impressions) * 100, 2) : null,
                ];
            })
            ->sortByDesc('spend')
            ->values();

        $totalSpend = round($accounts->sum('spend'), 2);
        $totalImpr = (int) $accounts->sum('impressions');
        $totalClicks = (int) $accounts->sum('clicks');

        $payload = [
            'days' => $days,
            'sst_rate' => $sst,
            'accounts' => $accounts,
            'totals' => [
                'spend' => $totalSpend,
                'spend_with_sst' => round($totalSpend * (1 + $sst), 2),
                'impressions' => $totalImpr,
                'clicks' => $totalClicks,
                'ctr' => $totalImpr > 0 ? round(($totalClicks / $totalImpr) * 100, 2) : null,
            ],
        ];

        if ($groupByDay) {
            $payload['daily'] = FacebookAdInsight::query()
                ->whereIn('facebook_ad_account_id', $accountIds)
                ->where('date', '>=', $from)
                ->selectRaw('DATE(date) as day, SUM(spend) as spend, SUM(impressions) as impressions, SUM(clicks) as clicks')
                ->groupBy('day')
                ->orderByDesc('day')
                ->get()
                ->map(fn ($row) => [
                    'day' => $row->day,
                    'spend' => round((float) $row->spend, 2),
                    'spend_with_sst' => round((float) $row->spend * (1 + $sst), 2),
                    'impressions' => (int) $row->impressions,
                    'clicks' => (int) $row->clicks,
                ]);
        }

        return Response::json($payload);
    }

    /**
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'days' => $schema->integer()
                ->description('How many days back to include (1-90). Defaults to 30.'),
            'group_by_day' => $schema->boolean()
                ->description('Also return a per-day spend breakdown. Defaults to false.'),
        ];
    }
}
