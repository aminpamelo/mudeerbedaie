<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LiveSessionVerificationEvent extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = [
        'live_session_id', 'actual_live_record_id', 'action',
        'user_id', 'gmv_snapshot', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'gmv_snapshot' => 'decimal:2',
        ];
    }

    public function liveSession(): BelongsTo
    {
        return $this->belongsTo(LiveSession::class);
    }

    public function actualLiveRecord(): BelongsTo
    {
        return $this->belongsTo(ActualLiveRecord::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
