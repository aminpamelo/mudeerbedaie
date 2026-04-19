<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LiveSessionGmvAdjustment extends Model
{
    protected $fillable = [
        'live_session_id',
        'amount_myr',
        'reason',
        'adjusted_by',
        'adjusted_at',
    ];

    protected function casts(): array
    {
        return [
            'amount_myr' => 'decimal:2',
            'adjusted_at' => 'datetime',
        ];
    }

    public function liveSession(): BelongsTo
    {
        return $this->belongsTo(LiveSession::class);
    }

    public function adjustedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'adjusted_by');
    }
}
