<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A purchase attributed back to an email broadcast. `attribution` records the
 * confidence: 'clicked' (the recipient clicked the email, or completed on the
 * same browser session) vs 'assisted' (a recipient bought within the window
 * without a recorded click).
 */
class BroadcastConversion extends Model
{
    public const ATTR_CLICKED = 'clicked';

    public const ATTR_ASSISTED = 'assisted';

    protected $fillable = [
        'broadcast_id',
        'broadcast_log_id',
        'student_id',
        'order_type',
        'order_id',
        'amount',
        'attribution',
        'converted_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'converted_at' => 'datetime',
        ];
    }

    public function broadcast(): BelongsTo
    {
        return $this->belongsTo(Broadcast::class);
    }

    public function broadcastLog(): BelongsTo
    {
        return $this->belongsTo(BroadcastLog::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
