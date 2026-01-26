<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadScoreHistory extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $table = 'lead_score_history';

    protected $fillable = [
        'student_id',
        'rule_id',
        'event_type',
        'points',
        'reason',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'points' => 'integer',
            'expires_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(LeadScoringRule::class, 'rule_id');
    }

    public function scopeActive($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('expires_at')
                ->orWhere('expires_at', '>', now());
        });
    }

    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<=', now());
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }
}
