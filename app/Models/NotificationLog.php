<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'scheduled_notification_id',
        'recipient_type',
        'recipient_id',
        'channel',
        'destination',
        'status',
        'message_id',
        'error_message',
        'sent_at',
        'delivered_at',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    public function scheduledNotification(): BelongsTo
    {
        return $this->belongsTo(ScheduledNotification::class, 'scheduled_notification_id');
    }

    public function recipient(): BelongsTo
    {
        return match ($this->recipient_type) {
            'student' => $this->belongsTo(Student::class, 'recipient_id'),
            'teacher' => $this->belongsTo(Teacher::class, 'recipient_id'),
            default => $this->belongsTo(Student::class, 'recipient_id'),
        };
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'recipient_id');
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class, 'recipient_id');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isSent(): bool
    {
        return $this->status === 'sent';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function isDelivered(): bool
    {
        return $this->status === 'delivered';
    }

    public function markAsSent(?string $messageId = null): void
    {
        $this->update([
            'status' => 'sent',
            'sent_at' => now(),
            'message_id' => $messageId,
        ]);
    }

    public function markAsFailed(string $errorMessage): void
    {
        $this->update([
            'status' => 'failed',
            'error_message' => $errorMessage,
        ]);
    }

    public function markAsDelivered(): void
    {
        $this->update([
            'status' => 'delivered',
            'delivered_at' => now(),
        ]);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeSent($query)
    {
        return $query->where('status', 'sent');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function scopeDelivered($query)
    {
        return $query->where('status', 'delivered');
    }

    public function scopeForChannel($query, string $channel)
    {
        return $query->where('channel', $channel);
    }

    public function scopeForRecipientType($query, string $type)
    {
        return $query->where('recipient_type', $type);
    }

    public function getStatusBadgeColorAttribute(): string
    {
        return match ($this->status) {
            'pending' => 'zinc',
            'sent' => 'blue',
            'delivered' => 'green',
            'failed' => 'red',
            'bounced' => 'yellow',
            default => 'zinc',
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'pending' => 'Menunggu',
            'sent' => 'Dihantar',
            'delivered' => 'Diterima',
            'failed' => 'Gagal',
            'bounced' => 'Ditolak',
            default => $this->status,
        };
    }

    public function getRecipientNameAttribute(): string
    {
        return match ($this->recipient_type) {
            'student' => $this->student?->user?->name ?? 'Pelajar Tidak Dikenali',
            'teacher' => $this->teacher?->user?->name ?? 'Guru Tidak Dikenali',
            default => 'Tidak Dikenali',
        };
    }

    public function getChannelLabelAttribute(): string
    {
        return match ($this->channel) {
            'email' => 'E-mel',
            'whatsapp' => 'WhatsApp',
            'sms' => 'SMS',
            default => $this->channel,
        };
    }
}
