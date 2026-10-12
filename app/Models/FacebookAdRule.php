<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An automated optimisation rule, e.g. "pause any ad set whose funnel ROAS
 * is below 1.5 after RM50 spend over the last 3 days". Evaluated hourly by
 * `facebook:run-ad-rules`; dry_run rules only log what they would do.
 */
class FacebookAdRule extends Model
{
    /** @use HasFactory<\Database\Factories\FacebookAdRuleFactory> */
    use HasFactory;

    public const LEVELS = ['campaign', 'adset', 'ad'];

    public const METRICS = ['spend', 'fb_roas', 'funnel_roas', 'cpc', 'ctr', 'cpa', 'purchases'];

    public const OPERATORS = ['lt', 'gt'];

    public const ACTIONS = ['pause', 'activate', 'increase_budget', 'decrease_budget'];

    protected $fillable = [
        'facebook_ad_account_id',
        'facebook_ad_strategy_id',
        'created_by',
        'name',
        'level',
        'metric',
        'operator',
        'threshold',
        'min_spend',
        'lookback_days',
        'action',
        'action_value',
        'max_daily_budget',
        'min_daily_budget',
        'cooldown_hours',
        'is_active',
        'dry_run',
        'last_run_at',
        'last_run_summary',
    ];

    protected function casts(): array
    {
        return [
            'threshold' => 'float',
            'min_spend' => 'float',
            'action_value' => 'float',
            'max_daily_budget' => 'float',
            'min_daily_budget' => 'float',
            'is_active' => 'boolean',
            'dry_run' => 'boolean',
            'last_run_at' => 'datetime',
        ];
    }

    public function adAccount(): BelongsTo
    {
        return $this->belongsTo(FacebookAdAccount::class, 'facebook_ad_account_id');
    }

    public function strategy(): BelongsTo
    {
        return $this->belongsTo(FacebookAdStrategy::class, 'facebook_ad_strategy_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function actions(): HasMany
    {
        return $this->hasMany(FacebookAdAction::class);
    }

    public function isBudgetAction(): bool
    {
        return in_array($this->action, ['increase_budget', 'decrease_budget'], true);
    }

    /**
     * Whether a metric value satisfies this rule's condition.
     */
    public function matches(?float $value): bool
    {
        if ($value === null) {
            return false;
        }

        return $this->operator === 'lt' ? $value < $this->threshold : $value > $this->threshold;
    }

    /**
     * Human sentence, e.g. "Funnel ROAS < 1.5 (3d, min RM50 spend) → Pause".
     */
    public function describe(): string
    {
        $metric = [
            'spend' => 'Spend',
            'fb_roas' => 'FB ROAS',
            'funnel_roas' => 'Funnel ROAS',
            'cpc' => 'CPC',
            'ctr' => 'CTR %',
            'cpa' => 'Cost / purchase',
            'purchases' => 'Purchases',
        ][$this->metric] ?? $this->metric;

        $action = match ($this->action) {
            'pause' => 'Pause',
            'activate' => 'Activate',
            'increase_budget' => 'Budget +'.rtrim(rtrim(number_format((float) $this->action_value, 2), '0'), '.').'%',
            'decrease_budget' => 'Budget −'.rtrim(rtrim(number_format((float) $this->action_value, 2), '0'), '.').'%',
            default => $this->action,
        };

        $op = $this->operator === 'lt' ? '<' : '>';
        $guard = $this->min_spend > 0 ? ', min RM'.rtrim(rtrim(number_format($this->min_spend, 2), '0'), '.').' spend' : '';

        return "{$metric} {$op} ".rtrim(rtrim(number_format($this->threshold, 4), '0'), '.')." ({$this->lookback_days}d{$guard}) → {$action}";
    }
}
