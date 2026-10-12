<?php

namespace App\Services\Funnel;

use App\Models\FacebookAdAction;
use App\Models\FacebookAdRule;
use RuntimeException;

/**
 * Evaluates one FacebookAdRule against live insights and applies (or, in
 * dry-run mode, only logs) its action on every matching object.
 *
 * Guards: min-spend before judging, per-object cooldown, budget floor/cap,
 * and a hard cap on actions per run so a bad rule can't sweep an account.
 * Strategy-scoped rules only touch campaigns tagged with that strategy.
 */
class FacebookAdRuleEngine
{
    public const MAX_ACTIONS_PER_RUN = 25;

    public function __construct(
        protected FacebookAdsManagerService $manager,
        protected FacebookAdStrategyService $strategies,
    ) {}

    /**
     * @return array{matched: int, applied: int, skipped: int, errors: int, summary: string}
     */
    public function run(FacebookAdRule $rule): array
    {
        $account = $rule->adAccount()->with('connection')->first();
        $result = ['matched' => 0, 'applied' => 0, 'skipped' => 0, 'errors' => 0, 'summary' => ''];

        if (! $account) {
            return $this->finish($rule, $result, 'Ad account no longer exists.');
        }

        if (! $rule->dry_run && ! $account->canManageAds()) {
            return $this->finish($rule, $result, 'Skipped: token is read-only (needs ads_management).');
        }

        $since = now()->subDays(max(1, $rule->lookback_days) - 1)->startOfDay();
        $statuses = $rule->action === 'activate' ? ['PAUSED'] : ['ACTIVE'];

        try {
            $rows = $this->manager->levelInsights($account, $rule->level, $since, $statuses);
        } catch (RuntimeException $e) {
            $result['errors']++;

            return $this->finish($rule, $result, 'Insights failed: '.$e->getMessage());
        }

        if ($rule->facebook_ad_strategy_id) {
            $strategyCampaigns = array_flip($this->strategies->campaignIds($rule->facebook_ad_strategy_id));
            $rows = array_values(array_filter($rows, fn (array $row) => isset($strategyCampaigns[(string) ($row['campaign_id'] ?? '')])));
        }

        $ids = collect($rows)->pluck("{$rule->level}_id")->filter()->map(fn ($id) => (string) $id)->all();
        $funnel = $rule->metric === 'funnel_roas' ? $this->manager->funnelRevenue($rule->level, $ids, $since) : [];

        foreach ($rows as $row) {
            $objectId = (string) ($row["{$rule->level}_id"] ?? '');
            $name = $row["{$rule->level}_name"] ?? $objectId;

            if ($objectId === '') {
                continue;
            }

            $metrics = $this->manager->normaliseInsight($row);
            $metrics['funnel_roas'] = $metrics['spend'] > 0 ? round(((float) ($funnel[$objectId]['revenue'] ?? 0)) / $metrics['spend'], 2) : null;

            if ($rule->metric !== 'spend' && $metrics['spend'] < $rule->min_spend) {
                continue;
            }

            if (! $rule->matches($this->metricValue($rule->metric, $metrics))) {
                continue;
            }

            $result['matched']++;

            if ($this->inCooldown($rule, $objectId)) {
                $result['skipped']++;

                continue;
            }

            if ($result['applied'] >= self::MAX_ACTIONS_PER_RUN) {
                $result['skipped']++;

                continue;
            }

            $outcome = $this->apply($rule, $account, $objectId, $name);

            if ($outcome === null) {
                $result['skipped']++;
            } elseif ($outcome['success']) {
                $result['applied']++;
            } else {
                $result['errors']++;
            }
        }

        $prefix = $rule->dry_run ? '[Dry run] ' : '';

        return $this->finish($rule, $result, "{$prefix}{$result['matched']} matched, {$result['applied']} actioned, {$result['skipped']} skipped, {$result['errors']} errors.");
    }

    /**
     * @param  array<string, mixed>  $metrics
     */
    protected function metricValue(string $metric, array $metrics): ?float
    {
        $value = $metrics[$metric] ?? null;

        return $value === null ? null : (float) $value;
    }

    protected function inCooldown(FacebookAdRule $rule, string $objectId): bool
    {
        return FacebookAdAction::query()
            ->where('facebook_ad_rule_id', $rule->id)
            ->where('object_id', $objectId)
            ->where('success', true)
            ->where('created_at', '>=', now()->subHours(max(1, $rule->cooldown_hours)))
            ->exists();
    }

    /**
     * @return array{success: bool, message: string}|null null = nothing to do
     */
    protected function apply(FacebookAdRule $rule, \App\Models\FacebookAdAccount $account, string $objectId, string $name): ?array
    {
        $context = ['source' => 'rule', 'rule_id' => $rule->id, 'dry_run' => $rule->dry_run];

        if (in_array($rule->action, ['pause', 'activate'], true)) {
            return $this->manager->updateStatus($account, $rule->level, $objectId, $rule->action === 'pause' ? 'PAUSED' : 'ACTIVE', null, $name, $context);
        }

        if ($rule->level === 'ad') {
            return null;
        }

        $current = $this->manager->currentDailyBudget($account, $objectId);
        if ($current === null) {
            return null;
        }

        $pct = (float) $rule->action_value / 100;
        $target = $rule->action === 'increase_budget' ? $current * (1 + $pct) : $current * (1 - $pct);

        if ($rule->max_daily_budget) {
            $target = min($target, $rule->max_daily_budget);
        }
        if ($rule->min_daily_budget) {
            $target = max($target, $rule->min_daily_budget);
        }

        $target = round(max(1, $target), 2);

        if (abs($target - $current) < 0.01) {
            return null;
        }

        return $this->manager->updateDailyBudget($account, $rule->level, $objectId, $target, null, $name, $current, $context);
    }

    /**
     * @param  array{matched: int, applied: int, skipped: int, errors: int, summary: string}  $result
     * @return array{matched: int, applied: int, skipped: int, errors: int, summary: string}
     */
    protected function finish(FacebookAdRule $rule, array $result, string $summary): array
    {
        $rule->forceFill(['last_run_at' => now(), 'last_run_summary' => $summary])->save();
        $result['summary'] = $summary;

        return $result;
    }
}
