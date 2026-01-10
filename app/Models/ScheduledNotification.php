<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ScheduledNotification extends Model
{
    use HasFactory;

    protected $fillable = [
        'class_id',
        'session_id',
        'scheduled_session_date',
        'scheduled_session_time',
        'class_notification_setting_id',
        'status',
        'scheduled_at',
        'sent_at',
        'total_recipients',
        'total_sent',
        'total_failed',
        'failure_reason',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'sent_at' => 'datetime',
            'scheduled_session_date' => 'date',
            // Note: scheduled_session_time is stored as string (e.g., '09:00:00')
            // Do NOT cast as datetime to avoid double-date parsing issues
        ];
    }

    public function class(): BelongsTo
    {
        return $this->belongsTo(ClassModel::class, 'class_id');
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(ClassSession::class, 'session_id');
    }

    public function setting(): BelongsTo
    {
        return $this->belongsTo(ClassNotificationSetting::class, 'class_notification_setting_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(NotificationLog::class, 'scheduled_notification_id');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isProcessing(): bool
    {
        return $this->status === 'processing';
    }

    public function isSent(): bool
    {
        return $this->status === 'sent';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function markAsProcessing(): void
    {
        $this->update(['status' => 'processing']);
    }

    public function markAsSent(): void
    {
        $this->update([
            'status' => 'sent',
            'sent_at' => now(),
        ]);
    }

    public function markAsFailed(string $reason): void
    {
        $this->update([
            'status' => 'failed',
            'failure_reason' => $reason,
        ]);
    }

    public function cancel(): void
    {
        $this->update(['status' => 'cancelled']);
    }

    public function updateStats(): void
    {
        $this->update([
            'total_sent' => $this->logs()->where('status', 'sent')->count(),
            'total_failed' => $this->logs()->where('status', 'failed')->count(),
        ]);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeProcessing($query)
    {
        return $query->where('status', 'processing');
    }

    public function scopeSent($query)
    {
        return $query->where('status', 'sent');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function scopeReadyToSend($query)
    {
        return $query->where('status', 'pending')
            ->where('scheduled_at', '<=', now());
    }

    public function scopeForClass($query, int $classId)
    {
        return $query->where('class_id', $classId);
    }

    public function scopeForSession($query, int $sessionId)
    {
        return $query->where('session_id', $sessionId);
    }

    public function scopeUpcoming($query)
    {
        return $query->where('status', 'pending')
            ->where('scheduled_at', '>', now())
            ->orderBy('scheduled_at');
    }

    public function getStatusBadgeColorAttribute(): string
    {
        return match ($this->status) {
            'pending' => 'zinc',
            'processing' => 'blue',
            'sent' => 'green',
            'failed' => 'red',
            'cancelled' => 'yellow',
            default => 'zinc',
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'pending' => 'Menunggu',
            'processing' => 'Sedang Diproses',
            'sent' => 'Dihantar',
            'failed' => 'Gagal',
            'cancelled' => 'Dibatalkan',
            default => $this->status,
        };
    }
}
