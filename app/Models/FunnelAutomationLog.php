<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FunnelAutomationLog extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'automation_id',
        'action_id',
        'session_id',
        'contact_email',
        'status',
        'result',
        'scheduled_at',
        'executed_at',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'result' => 'array',
            'scheduled_at' => 'datetime',
            'executed_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (FunnelAutomationLog $log) {
            $log->created_at = $log->created_at ?? now();
        });
    }

    // Relationships
    public function automation(): BelongsTo
    {
        return $this->belongsTo(FunnelAutomation::class, 'automation_id');
    }

    public function action(): BelongsTo
    {
        return $this->belongsTo(FunnelAutomationAction::class, 'action_id');
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(FunnelSession::class, 'session_id');
    }

    // Status helpers
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isExecuted(): bool
    {
        return $this->status === 'executed';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function isSkipped(): bool
    {
        return $this->status === 'skipped';
    }

    // Status updates
    public function markAsExecuted(array $result = []): void
    {
        $this->update([
            'status' => 'executed',
            'executed_at' => now(),
            'result' => $result,
        ]);
    }

    public function markAsFailed(array $error = []): void
    {
        $this->update([
            'status' => 'failed',
            'executed_at' => now(),
            'result' => $error,
        ]);
    }

    public function markAsSkipped(string $reason = ''): void
    {
        $this->update([
            'status' => 'skipped',
            'executed_at' => now(),
            'result' => ['reason' => $reason],
        ]);
    }

    // Schedule helpers
    public function isScheduled(): bool
    {
        return $this->scheduled_at !== null && $this->isPending();
    }

    public function isDue(): bool
    {
        if (! $this->isScheduled()) {
            return false;
        }

        return now()->gte($this->scheduled_at);
    }

    public function getTimeUntilScheduled(): ?int
    {
        if (! $this->isScheduled()) {
            return null;
        }

        return now()->diffInMinutes($this->scheduled_at, false);
    }

    // Result helpers
    public function getErrorMessage(): ?string
    {
        if (! $this->isFailed()) {
            return null;
        }

        return $this->result['error'] ?? $this->result['message'] ?? 'Unknown error';
    }

    public function getSuccessMessage(): ?string
    {
        if (! $this->isExecuted()) {
            return null;
        }

        return $this->result['message'] ?? 'Executed successfully';
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeExecuted($query)
    {
        return $query->where('status', 'executed');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function scopeDue($query)
    {
        return $query->where('status', 'pending')
            ->where(function ($q) {
                $q->whereNull('scheduled_at')
                    ->orWhere('scheduled_at', '<=', now());
            });
    }

    public function scopeForAutomation($query, int $automationId)
    {
        return $query->where('automation_id', $automationId);
    }
}
