<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BroadcastLog extends Model
{
    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'opened_at' => 'datetime',
            'clicked_at' => 'datetime',
            'open_count' => 'integer',
            'click_count' => 'integer',
        ];
    }

    protected $fillable = [
        'broadcast_id',
        'student_id',
        'email',
        'status',
        'error_message',
        'sent_at',
        'opened_at',
        'clicked_at',
        'open_count',
        'click_count',
        'tracking_token',
    ];

    public function broadcast(): BelongsTo
    {
        return $this->belongsTo(Broadcast::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function markOpened(): void
    {
        $this->forceFill([
            'opened_at' => $this->opened_at ?? now(),
            'open_count' => $this->open_count + 1,
        ])->save();
    }

    public function markClicked(): void
    {
        $this->forceFill([
            'clicked_at' => $this->clicked_at ?? now(),
            'click_count' => $this->click_count + 1,
            // A click implies an open, even if the tracking pixel was blocked.
            'opened_at' => $this->opened_at ?? now(),
        ])->save();
    }
}
