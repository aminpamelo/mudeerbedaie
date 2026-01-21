<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

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
        'tracking_id',
        'sent_at',
        'opened_at',
        'open_count',
        'clicked_at',
        'click_count',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->tracking_id)) {
                $model->tracking_id = Str::uuid()->toString();
            }
        });
    }

    public function broadcast(): BelongsTo
    {
        return $this->belongsTo(Broadcast::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function markAsOpened(): void
    {
        $this->increment('open_count');

        if (!$this->opened_at) {
            $this->update(['opened_at' => now()]);
        }
    }

    public function markAsClicked(): void
    {
        $this->increment('click_count');

        if (!$this->clicked_at) {
            $this->update(['clicked_at' => now()]);
        }

        if (!$this->opened_at) {
            $this->update(['opened_at' => now()]);
            $this->increment('open_count');
        }
    }

    public function isOpened(): bool
    {
        return $this->opened_at !== null;
    }

    public function isClicked(): bool
    {
        return $this->clicked_at !== null;
    }

    public function scopeOpened($query)
    {
        return $query->whereNotNull('opened_at');
    }

    public function scopeNotOpened($query)
    {
        return $query->whereNull('opened_at');
    }

    public function scopeClicked($query)
    {
        return $query->whereNotNull('clicked_at');
    }

    public static function findByTrackingId(string $trackingId): ?self
    {
        return static::where('tracking_id', $trackingId)->first();
    }
}
