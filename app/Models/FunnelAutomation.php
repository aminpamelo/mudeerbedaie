<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class FunnelAutomation extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'name',
        'funnel_id',
        'trigger_type',
        'trigger_config',
        'is_active',
        'priority',
    ];

    protected function casts(): array
    {
        return [
            'trigger_config' => 'array',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (FunnelAutomation $automation) {
            $automation->uuid = $automation->uuid ?? Str::uuid()->toString();
        });
    }

    // Relationships
    public function funnel(): BelongsTo
    {
        return $this->belongsTo(Funnel::class);
    }

    public function actions(): HasMany
    {
        return $this->hasMany(FunnelAutomationAction::class, 'automation_id')->orderBy('sort_order');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(FunnelAutomationLog::class, 'automation_id');
    }

    // Trigger type helpers
    public function isCartAbandonmentTrigger(): bool
    {
        return $this->trigger_type === 'cart_abandonment';
    }

    public function isPurchaseTrigger(): bool
    {
        return $this->trigger_type === 'purchase';
    }

    public function isOptinTrigger(): bool
    {
        return $this->trigger_type === 'optin';
    }

    public function isUpsellAcceptedTrigger(): bool
    {
        return $this->trigger_type === 'upsell_accepted';
    }

    public function isUpsellDeclinedTrigger(): bool
    {
        return $this->trigger_type === 'upsell_declined';
    }

    public function isPageViewTrigger(): bool
    {
        return $this->trigger_type === 'page_view';
    }

    public function isTimeTrigger(): bool
    {
        return $this->trigger_type === 'time_delay';
    }

    // Status helpers
    public function activate(): void
    {
        $this->update(['is_active' => true]);
    }

    public function deactivate(): void
    {
        $this->update(['is_active' => false]);
    }

    // Trigger matching
    public function matchesTrigger(string $eventType, array $eventData = []): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->trigger_type !== $eventType) {
            return false;
        }

        // Check trigger conditions
        $config = $this->trigger_config ?? [];

        if (isset($config['conditions'])) {
            foreach ($config['conditions'] as $condition) {
                if (! $this->evaluateCondition($condition, $eventData)) {
                    return false;
                }
            }
        }

        return true;
    }

    protected function evaluateCondition(array $condition, array $eventData): bool
    {
        $field = $condition['field'] ?? null;
        $operator = $condition['operator'] ?? 'equals';
        $value = $condition['value'] ?? null;

        if (! $field || ! isset($eventData[$field])) {
            return true; // Skip if field not specified or not in data
        }

        $fieldValue = $eventData[$field];

        return match ($operator) {
            'equals' => $fieldValue == $value,
            'not_equals' => $fieldValue != $value,
            'contains' => str_contains($fieldValue, $value),
            'greater_than' => $fieldValue > $value,
            'less_than' => $fieldValue < $value,
            default => true,
        };
    }

    // Stats
    public function getExecutionCount(): int
    {
        return $this->logs()->where('status', 'executed')->count();
    }

    public function getFailedCount(): int
    {
        return $this->logs()->where('status', 'failed')->count();
    }

    public function getSuccessRate(): float
    {
        $total = $this->logs()->whereIn('status', ['executed', 'failed'])->count();
        if ($total === 0) {
            return 0;
        }

        return round(($this->getExecutionCount() / $total) * 100, 2);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByTrigger($query, string $triggerType)
    {
        return $query->where('trigger_type', $triggerType);
    }

    public function scopeForFunnel($query, int $funnelId)
    {
        return $query->where('funnel_id', $funnelId);
    }

    public function scopeGlobal($query)
    {
        return $query->whereNull('funnel_id');
    }
}
