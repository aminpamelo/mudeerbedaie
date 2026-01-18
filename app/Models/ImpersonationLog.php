<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImpersonationLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'impersonator_id',
        'impersonated_id',
        'started_at',
        'ended_at',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    public function impersonator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'impersonator_id');
    }

    public function impersonated(): BelongsTo
    {
        return $this->belongsTo(User::class, 'impersonated_id');
    }

    public function scopeActive($query)
    {
        return $query->whereNull('ended_at');
    }

    public function scopeByImpersonator($query, int $userId)
    {
        return $query->where('impersonator_id', $userId);
    }

    public function markAsEnded(): void
    {
        $this->update(['ended_at' => now()]);
    }

    public function isActive(): bool
    {
        return is_null($this->ended_at);
    }

    public function getDurationAttribute(): ?string
    {
        if (! $this->ended_at) {
            return $this->started_at->diffForHumans(now(), ['parts' => 2]);
        }

        return $this->started_at->diffForHumans($this->ended_at, ['parts' => 2]);
    }
}
