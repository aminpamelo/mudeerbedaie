<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FunnelAutomationAction extends Model
{
    use HasFactory;

    protected $fillable = [
        'automation_id',
        'action_type',
        'action_config',
        'delay_minutes',
        'sort_order',
        'conditions',
    ];

    protected function casts(): array
    {
        return [
            'action_config' => 'array',
            'conditions' => 'array',
        ];
    }

    // Relationships
    public function automation(): BelongsTo
    {
        return $this->belongsTo(FunnelAutomation::class, 'automation_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(FunnelAutomationLog::class, 'action_id');
    }

    // Action type helpers
    public function isSendEmail(): bool
    {
        return $this->action_type === 'send_email';
    }

    public function isSendWhatsApp(): bool
    {
        return $this->action_type === 'send_whatsapp';
    }

    public function isSendSms(): bool
    {
        return $this->action_type === 'send_sms';
    }

    public function isAddTag(): bool
    {
        return $this->action_type === 'add_tag';
    }

    public function isRemoveTag(): bool
    {
        return $this->action_type === 'remove_tag';
    }

    public function isWebhook(): bool
    {
        return $this->action_type === 'webhook';
    }

    public function isWait(): bool
    {
        return $this->action_type === 'wait';
    }

    public function isCondition(): bool
    {
        return $this->action_type === 'condition';
    }

    // Delay helpers
    public function hasDelay(): bool
    {
        return $this->delay_minutes > 0;
    }

    public function getDelayFormatted(): string
    {
        $minutes = $this->delay_minutes;

        if ($minutes < 60) {
            return $minutes.' min';
        }

        if ($minutes < 1440) {
            $hours = floor($minutes / 60);

            return $hours.' hour'.($hours > 1 ? 's' : '');
        }

        $days = floor($minutes / 1440);

        return $days.' day'.($days > 1 ? 's' : '');
    }

    public function getScheduledTime(): \Carbon\Carbon
    {
        return now()->addMinutes($this->delay_minutes);
    }

    // Config helpers
    public function getEmailTemplate(): ?string
    {
        return $this->action_config['template'] ?? null;
    }

    public function getEmailSubject(): ?string
    {
        return $this->action_config['subject'] ?? null;
    }

    public function getWhatsAppTemplate(): ?string
    {
        return $this->action_config['template'] ?? null;
    }

    public function getWebhookUrl(): ?string
    {
        return $this->action_config['url'] ?? null;
    }

    public function getWebhookMethod(): string
    {
        return $this->action_config['method'] ?? 'POST';
    }

    // Condition helpers
    public function hasConditions(): bool
    {
        return ! empty($this->conditions);
    }

    public function evaluateConditions(array $context): bool
    {
        if (! $this->hasConditions()) {
            return true;
        }

        foreach ($this->conditions as $condition) {
            $field = $condition['field'] ?? null;
            $operator = $condition['operator'] ?? 'equals';
            $value = $condition['value'] ?? null;

            if (! $field || ! isset($context[$field])) {
                continue;
            }

            $contextValue = $context[$field];

            $result = match ($operator) {
                'equals' => $contextValue == $value,
                'not_equals' => $contextValue != $value,
                'contains' => str_contains($contextValue, $value),
                'greater_than' => $contextValue > $value,
                'less_than' => $contextValue < $value,
                'is_empty' => empty($contextValue),
                'is_not_empty' => ! empty($contextValue),
                default => true,
            };

            if (! $result) {
                return false;
            }
        }

        return true;
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
}
