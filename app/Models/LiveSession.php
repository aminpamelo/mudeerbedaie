<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class LiveSession extends Model
{
    /** @use HasFactory<\Database\Factories\LiveSessionFactory> */
    use HasFactory;

    protected $fillable = [
        'platform_account_id',
        'live_schedule_id',
        'live_host_id',
        'title',
        'description',
        'status',
        'scheduled_start_at',
        'actual_start_at',
        'actual_end_at',
        'duration_minutes',
        'image_path',
        'video_link',
        'remarks',
        'uploaded_at',
        'uploaded_by',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_start_at' => 'datetime',
            'actual_start_at' => 'datetime',
            'actual_end_at' => 'datetime',
            'uploaded_at' => 'datetime',
        ];
    }

    public function platformAccount(): BelongsTo
    {
        return $this->belongsTo(PlatformAccount::class);
    }

    public function liveSchedule(): BelongsTo
    {
        return $this->belongsTo(LiveSchedule::class);
    }

    public function liveHost(): BelongsTo
    {
        return $this->belongsTo(User::class, 'live_host_id');
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function analytics(): HasOne
    {
        return $this->hasOne(LiveAnalytics::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(LiveSessionAttachment::class);
    }

    public function scopeScheduled(Builder $query): Builder
    {
        return $query->where('status', 'scheduled');
    }

    public function scopeLive(Builder $query): Builder
    {
        return $query->where('status', 'live');
    }

    public function scopeEnded(Builder $query): Builder
    {
        return $query->where('status', 'ended');
    }

    public function scopeCancelled(Builder $query): Builder
    {
        return $query->where('status', 'cancelled');
    }

    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where('status', 'scheduled')
            ->where('scheduled_start_at', '>', now())
            ->orderBy('scheduled_start_at');
    }

    public function scopePast(Builder $query): Builder
    {
        return $query->whereIn('status', ['ended', 'cancelled'])
            ->orderByDesc('scheduled_start_at');
    }

    public function startLive(): void
    {
        $this->update([
            'status' => 'live',
            'actual_start_at' => now(),
        ]);
    }

    public function endLive(): void
    {
        $this->update([
            'status' => 'ended',
            'actual_end_at' => now(),
        ]);
    }

    public function cancel(): void
    {
        $this->update(['status' => 'cancelled']);
    }

    public function isScheduled(): bool
    {
        return $this->status === 'scheduled';
    }

    public function isLive(): bool
    {
        return $this->status === 'live';
    }

    public function isEnded(): bool
    {
        return $this->status === 'ended';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function getDurationAttribute(): ?int
    {
        if ($this->actual_start_at && $this->actual_end_at) {
            return $this->actual_start_at->diffInMinutes($this->actual_end_at);
        }

        return null;
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'scheduled' => 'blue',
            'live' => 'green',
            'ended' => 'gray',
            'cancelled' => 'red',
            default => 'gray',
        };
    }

    /**
     * Check if session was created from an admin-assigned schedule
     */
    public function isAdminAssigned(): bool
    {
        // If no schedule, check if it was manually created (not admin)
        if (! $this->live_schedule_id) {
            return false; // No schedule = manually created by someone
        }

        // Load schedule if not loaded
        $schedule = $this->liveSchedule ?? $this->load('liveSchedule')->liveSchedule;

        if (! $schedule) {
            return false;
        }

        return $schedule->isAdminAssigned();
    }

    public function isUploaded(): bool
    {
        return $this->uploaded_at !== null;
    }

    public function uploadDetails(array $data): void
    {
        $actualStart = isset($data['actual_start_at'])
            ? \Carbon\Carbon::parse($data['actual_start_at'])
            : $this->actual_start_at;

        $actualEnd = isset($data['actual_end_at'])
            ? \Carbon\Carbon::parse($data['actual_end_at'])
            : $this->actual_end_at;

        $durationMinutes = ($actualStart && $actualEnd)
            ? $actualStart->diffInMinutes($actualEnd)
            : null;

        $this->update([
            'actual_start_at' => $actualStart,
            'actual_end_at' => $actualEnd,
            'duration_minutes' => $durationMinutes,
            'image_path' => $data['image_path'] ?? $this->image_path,
            'video_link' => $data['video_link'] ?? $this->video_link,
            'remarks' => $data['remarks'] ?? $this->remarks,
            'uploaded_at' => now(),
            'uploaded_by' => auth()->id(),
        ]);
    }
}
