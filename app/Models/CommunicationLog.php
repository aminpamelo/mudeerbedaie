<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommunicationLog extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'student_id',
        'channel',
        'direction',
        'template_id',
        'workflow_id',
        'step_execution_id',
        'external_id',
        'recipient',
        'subject',
        'content',
        'status',
        'status_details',
        'sent_at',
        'delivered_at',
        'opened_at',
        'clicked_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'status_details' => 'array',
            'metadata' => 'array',
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
            'opened_at' => 'datetime',
            'clicked_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(MessageTemplate::class, 'template_id');
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }

    public function stepExecution(): BelongsTo
    {
        return $this->belongsTo(WorkflowStepExecution::class, 'step_execution_id');
    }

    public function scopeForChannel($query, string $channel)
    {
        return $query->where('channel', $channel);
    }

    public function scopeOutbound($query)
    {
        return $query->where('direction', 'outbound');
    }

    public function scopeInbound($query)
    {
        return $query->where('direction', 'inbound');
    }

    public function scopeSuccessful($query)
    {
        return $query->whereIn('status', ['sent', 'delivered', 'opened', 'clicked']);
    }

    public function scopeFailed($query)
    {
        return $query->whereIn('status', ['failed', 'bounced', 'complained']);
    }

    public function isEmail(): bool
    {
        return $this->channel === 'email';
    }

    public function isWhatsapp(): bool
    {
        return $this->channel === 'whatsapp';
    }

    public function isSms(): bool
    {
        return $this->channel === 'sms';
    }

    public function isOutbound(): bool
    {
        return $this->direction === 'outbound';
    }

    public function isInbound(): bool
    {
        return $this->direction === 'inbound';
    }

    public function markAsSent(): void
    {
        $this->update([
            'status' => 'sent',
            'sent_at' => now(),
        ]);
    }

    public function markAsDelivered(): void
    {
        $this->update([
            'status' => 'delivered',
            'delivered_at' => now(),
        ]);
    }

    public function markAsOpened(): void
    {
        $this->update([
            'status' => 'opened',
            'opened_at' => now(),
        ]);
    }

    public function markAsClicked(): void
    {
        $this->update([
            'status' => 'clicked',
            'clicked_at' => now(),
        ]);
    }

    public function markAsFailed(array $details = []): void
    {
        $this->update([
            'status' => 'failed',
            'status_details' => $details,
        ]);
    }
}
