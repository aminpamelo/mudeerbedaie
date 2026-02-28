<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CertificateLog extends Model
{
    protected $fillable = [
        'certificate_issue_id',
        'action',
        'user_id',
        'ip_address',
        'user_agent',
    ];

    // Relationships

    public function certificateIssue(): BelongsTo
    {
        return $this->belongsTo(CertificateIssue::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Helper methods

    public function getFormattedActionAttribute(): string
    {
        return match ($this->action) {
            'issued' => 'Certificate Issued',
            'viewed' => 'Certificate Viewed',
            'downloaded' => 'Certificate Downloaded',
            'revoked' => 'Certificate Revoked',
            'sent_email' => 'Sent via Email',
            'sent_whatsapp' => 'Sent via WhatsApp',
            default => ucfirst($this->action),
        };
    }

    public function getActionIconAttribute(): string
    {
        return match ($this->action) {
            'issued' => 'check-circle',
            'viewed' => 'eye',
            'downloaded' => 'download',
            'revoked' => 'x-circle',
            'sent_email' => 'envelope',
            'sent_whatsapp' => 'phone',
            default => 'activity',
        };
    }

    public function getActionColorAttribute(): string
    {
        return match ($this->action) {
            'issued' => 'green',
            'viewed' => 'blue',
            'downloaded' => 'indigo',
            'revoked' => 'red',
            'sent_email' => 'blue',
            'sent_whatsapp' => 'emerald',
            default => 'gray',
        };
    }

    // Scopes

    public function scopeForCertificate($query, $certificateIssueId)
    {
        return $query->where('certificate_issue_id', $certificateIssueId);
    }

    public function scopeByAction($query, $action)
    {
        return $query->where('action', $action);
    }

    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeRecent($query, $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }
}
