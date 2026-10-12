<?php

namespace App\Services\Funnel;

use App\Models\FacebookAdAccount;
use App\Models\FacebookAdInsight;
use App\Models\FacebookAdRule;
use App\Models\FacebookAdStrategy;
use App\Models\FacebookCampaignStrategy;
use App\Models\FunnelOrder;
use App\Models\User;

/**
 * Ad strategies ("skills"): starter library, tagging campaigns, turning a
 * strategy's bundled rules into real (dry-run) rules, and per-strategy
 * performance from synced insights + UTM-attributed funnel revenue.
 */
class FacebookAdStrategyService
{
    /**
     * Opinionated starter playbooks for a team that has none yet.
     *
     * @return array<int, array<string, mixed>>
     */
    public function starterStrategies(): array
    {
        return [
            [
                'name' => 'Broad Testing',
                'color' => 'blue',
                'description' => 'Find winning creatives fast with a wide audience and a small campaign budget.',
                'playbook' => "1. One campaign (CBO), Advantage+ audience, country-wide, age 18–65.\n2. Budget RM30–50/day. Load 3–5 different creatives (hooks), not 5 copies of one.\n3. Don't touch it for 3 days — let Facebook learn.\n4. Kill any ad that spent ~RM40 with zero purchases.\n5. A creative with funnel ROAS > 2 after RM100 spend is a winner → move it to CBO Scaling.",
                'launch_preset' => ['objective' => 'OUTCOME_SALES', 'daily_budget' => 40, 'age_min' => 18, 'age_max' => 65, 'countries' => 'MY', 'advantage_audience' => true, 'call_to_action' => 'SHOP_NOW'],
                'rule_presets' => [
                    ['name' => 'Kill no-sale ads', 'level' => 'ad', 'metric' => 'purchases', 'operator' => 'lt', 'threshold' => 1, 'min_spend' => 40, 'lookback_days' => 3, 'action' => 'pause', 'cooldown_hours' => 24],
                ],
            ],
            [
                'name' => 'CBO Scaling',
                'color' => 'green',
                'description' => 'Scale proven winners gradually so Facebook keeps its learning.',
                'playbook' => "1. Only proven creatives (funnel ROAS > 2 in testing).\n2. Start at 2× the testing budget.\n3. Raise budget max +20% every 48h while funnel ROAS stays above 3.\n4. If funnel ROAS drops below 1.2 for 3 days, cut budget 30%.\n5. Never jump the budget more than 30% at once — it resets learning.",
                'launch_preset' => ['objective' => 'OUTCOME_SALES', 'daily_budget' => 100, 'age_min' => 18, 'age_max' => 65, 'countries' => 'MY', 'advantage_audience' => true, 'call_to_action' => 'SHOP_NOW'],
                'rule_presets' => [
                    ['name' => 'Scale winners +20%', 'level' => 'campaign', 'metric' => 'funnel_roas', 'operator' => 'gt', 'threshold' => 3, 'min_spend' => 100, 'lookback_days' => 3, 'action' => 'increase_budget', 'action_value' => 20, 'max_daily_budget' => 500, 'cooldown_hours' => 48],
                    ['name' => 'Cut weak budget −30%', 'level' => 'campaign', 'metric' => 'funnel_roas', 'operator' => 'lt', 'threshold' => 1.2, 'min_spend' => 100, 'lookback_days' => 3, 'action' => 'decrease_budget', 'action_value' => 30, 'min_daily_budget' => 30, 'cooldown_hours' => 48],
                ],
            ],
            [
                'name' => 'Retargeting',
                'color' => 'purple',
                'description' => 'Bring back visitors who saw the funnel but did not buy.',
                'playbook' => "1. Audience: website visitors 7–30 days, exclude purchasers (set in Facebook Ads Manager).\n2. Small budget RM15–30/day — the audience is small.\n3. Use testimonial / offer / urgency creatives, not the cold-traffic hook.\n4. Expect higher ROAS (3–6). Pause if frequency gets above ~4 and ROAS falls under 1.5.",
                'launch_preset' => ['objective' => 'OUTCOME_SALES', 'daily_budget' => 20, 'age_min' => 18, 'age_max' => 65, 'countries' => 'MY', 'advantage_audience' => false, 'call_to_action' => 'ORDER_NOW'],
                'rule_presets' => [
                    ['name' => 'Pause tired retargeting', 'level' => 'adset', 'metric' => 'funnel_roas', 'operator' => 'lt', 'threshold' => 1.5, 'min_spend' => 60, 'lookback_days' => 7, 'action' => 'pause', 'cooldown_hours' => 72],
                ],
            ],
            [
                'name' => 'Traffic Warm-up',
                'color' => 'yellow',
                'description' => 'Cheap landing-page views to warm a new pixel and build retargeting audiences.',
                'playbook' => "1. Objective Traffic (landing page views) — only for new pixels / new funnels.\n2. RM20–30/day for 5–7 days.\n3. Watch CPC: pause ads above RM1.50.\n4. Once the pixel has ~50 purchases, switch to Broad Testing (Sales).",
                'launch_preset' => ['objective' => 'OUTCOME_TRAFFIC', 'daily_budget' => 25, 'age_min' => 18, 'age_max' => 65, 'countries' => 'MY', 'advantage_audience' => true, 'call_to_action' => 'LEARN_MORE'],
                'rule_presets' => [
                    ['name' => 'Pause expensive clicks', 'level' => 'ad', 'metric' => 'cpc', 'operator' => 'gt', 'threshold' => 1.5, 'min_spend' => 20, 'lookback_days' => 3, 'action' => 'pause', 'cooldown_hours' => 24],
                ],
            ],
        ];
    }

    /**
     * Create the starter strategies (skips names that already exist).
     */
    public function seedStarters(?User $user): int
    {
        $created = 0;

        foreach ($this->starterStrategies() as $definition) {
            if (FacebookAdStrategy::query()->where('name', $definition['name'])->exists()) {
                continue;
            }

            FacebookAdStrategy::create(array_merge($definition, ['created_by' => $user?->id]));
            $created++;
        }

        return $created;
    }

    /**
     * Tag (or untag with null) a campaign with a strategy.
     */
    public function tagCampaign(FacebookAdAccount $account, string $campaignId, ?int $strategyId, ?string $campaignName, ?User $user): ?FacebookCampaignStrategy
    {
        if ($strategyId === null) {
            FacebookCampaignStrategy::query()->where('campaign_id', $campaignId)->delete();

            return null;
        }

        return FacebookCampaignStrategy::updateOrCreate(
            ['campaign_id' => $campaignId],
            [
                'facebook_ad_account_id' => $account->id,
                'facebook_ad_strategy_id' => $strategyId,
                'campaign_name' => $campaignName,
                'assigned_by' => $user?->id,
            ]
        );
    }

    /**
     * Strategy badge per campaign id.
     *
     * @param  array<int, string>  $campaignIds
     * @return array<string, array{id: int, name: string, color: string}>
     */
    public function strategiesFor(array $campaignIds): array
    {
        if (empty($campaignIds)) {
            return [];
        }

        return FacebookCampaignStrategy::query()
            ->with('strategy:id,name,color')
            ->whereIn('campaign_id', $campaignIds)
            ->get()
            ->filter(fn (FacebookCampaignStrategy $tag) => $tag->strategy !== null)
            ->mapWithKeys(fn (FacebookCampaignStrategy $tag) => [$tag->campaign_id => [
                'id' => $tag->strategy->id,
                'name' => $tag->strategy->name,
                'color' => $tag->strategy->color,
            ]])
            ->all();
    }

    /**
     * Campaign ids currently tagged with a strategy.
     *
     * @return array<int, string>
     */
    public function campaignIds(int $strategyId): array
    {
        return FacebookCampaignStrategy::query()
            ->where('facebook_ad_strategy_id', $strategyId)
            ->pluck('campaign_id')
            ->map(fn ($id) => (string) $id)
            ->all();
    }

    /**
     * Turn the strategy's bundled rule presets into real rules for an ad
     * account, scoped to the strategy's campaigns. New rules start in dry
     * run; presets already applied (same name) are skipped.
     */
    public function applyRules(FacebookAdStrategy $strategy, FacebookAdAccount $account, ?User $user): int
    {
        $created = 0;

        foreach ($strategy->rule_presets ?? [] as $preset) {
            $name = "{$strategy->name}: ".($preset['name'] ?? 'Rule');

            $exists = FacebookAdRule::query()
                ->where('facebook_ad_account_id', $account->id)
                ->where('facebook_ad_strategy_id', $strategy->id)
                ->where('name', $name)
                ->exists();

            if ($exists) {
                continue;
            }

            FacebookAdRule::create([
                'facebook_ad_account_id' => $account->id,
                'facebook_ad_strategy_id' => $strategy->id,
                'created_by' => $user?->id,
                'name' => $name,
                'level' => $preset['level'] ?? 'adset',
                'metric' => $preset['metric'] ?? 'funnel_roas',
                'operator' => $preset['operator'] ?? 'lt',
                'threshold' => (float) ($preset['threshold'] ?? 1),
                'min_spend' => (float) ($preset['min_spend'] ?? 0),
                'lookback_days' => (int) ($preset['lookback_days'] ?? 3),
                'action' => $preset['action'] ?? 'pause',
                'action_value' => isset($preset['action_value']) ? (float) $preset['action_value'] : null,
                'max_daily_budget' => isset($preset['max_daily_budget']) ? (float) $preset['max_daily_budget'] : null,
                'min_daily_budget' => isset($preset['min_daily_budget']) ? (float) $preset['min_daily_budget'] : null,
                'cooldown_hours' => (int) ($preset['cooldown_hours'] ?? 24),
                'is_active' => true,
                'dry_run' => true,
            ]);
            $created++;
        }

        return $created;
    }

    /**
     * Per-strategy campaigns / spend / funnel revenue over the last N days.
     *
     * @return array<int, array{campaigns: int, spend: float, funnel_revenue: float, funnel_orders: int, funnel_roas: ?float}>
     */
    public function performance(int $days = 30): array
    {
        $since = now()->subDays($days)->startOfDay();

        $tags = FacebookCampaignStrategy::query()->get(['facebook_ad_strategy_id', 'campaign_id']);
        $strategyByCampaign = $tags->pluck('facebook_ad_strategy_id', 'campaign_id');

        if ($tags->isEmpty()) {
            return [];
        }

        $spend = FacebookAdInsight::query()
            ->whereIn('campaign_id', $strategyByCampaign->keys())
            ->where('date', '>=', $since)
            ->selectRaw('campaign_id, SUM(spend) as spend')
            ->groupBy('campaign_id')
            ->pluck('spend', 'campaign_id');

        $revenue = FunnelOrder::query()
            ->join('funnel_sessions', 'funnel_sessions.id', '=', 'funnel_orders.session_id')
            ->whereIn('funnel_sessions.utm_campaign', $strategyByCampaign->keys())
            ->where('funnel_orders.created_at', '>=', $since)
            ->selectRaw('funnel_sessions.utm_campaign as campaign_id, SUM(funnel_orders.funnel_revenue) as revenue, COUNT(*) as orders')
            ->groupBy('funnel_sessions.utm_campaign')
            ->get()
            ->keyBy('campaign_id');

        $result = [];

        foreach ($strategyByCampaign as $campaignId => $strategyId) {
            $row = $result[$strategyId] ?? ['campaigns' => 0, 'spend' => 0.0, 'funnel_revenue' => 0.0, 'funnel_orders' => 0, 'funnel_roas' => null];
            $row['campaigns']++;
            $row['spend'] += (float) ($spend[$campaignId] ?? 0);
            $row['funnel_revenue'] += (float) ($revenue[$campaignId]->revenue ?? 0);
            $row['funnel_orders'] += (int) ($revenue[$campaignId]->orders ?? 0);
            $result[$strategyId] = $row;
        }

        foreach ($result as $strategyId => $row) {
            $result[$strategyId]['funnel_roas'] = $row['spend'] > 0 ? round($row['funnel_revenue'] / $row['spend'], 2) : null;
            $result[$strategyId]['spend'] = round($row['spend'], 2);
        }

        return $result;
    }
}
