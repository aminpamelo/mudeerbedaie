<?php

namespace App\Models;

use App\Services\LiveHost\CommissionCalculator;
use Carbon\Carbon;
use Database\Factories\LiveSessionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class LiveSession extends Model
{
    /** @use HasFactory<LiveSessionFactory> */
    use HasFactory;

    protected $fillable = [
        'platform_account_id',
        'live_host_platform_account_id',
        'live_account_id',
        'live_schedule_id',
        'live_schedule_assignment_id',
        'live_host_id',
        'title',
        'description',
        'status',
        'scheduled_start_at',
        'reminder_15m_sent_at',
        'recap_reminder_sent_at',
        'actual_start_at',
        'actual_end_at',
        'duration_minutes',
        'gmv_amount',
        'gmv_adjustment',
        'gmv_source',
        'gmv_locked_at',
        'commission_snapshot_json',
        'image_path',
        'remarks',
        'uploaded_at',
        'uploaded_by',
        'missed_reason_code',
        'missed_reason_note',
        'verification_status',
        'verified_by',
        'verified_at',
        'verification_notes',
        'auto_verified',
        'matched_actual_live_record_id',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_start_at' => 'datetime',
            'reminder_15m_sent_at' => 'datetime',
            'recap_reminder_sent_at' => 'datetime',
            'actual_start_at' => 'datetime',
            'actual_end_at' => 'datetime',
            'uploaded_at' => 'datetime',
            'verified_at' => 'datetime',
            'gmv_amount' => 'decimal:2',
            'gmv_adjustment' => 'decimal:2',
            'gmv_locked_at' => 'datetime',
            'commission_snapshot_json' => 'array',
            'auto_verified' => 'boolean',
        ];
    }

    public function platformAccount(): BelongsTo
    {
        return $this->belongsTo(PlatformAccount::class);
    }

    public function liveHostPlatformAccount(): BelongsTo
    {
        return $this->belongsTo(LiveHostPlatformAccount::class, 'live_host_platform_account_id');
    }

    public function liveAccount(): BelongsTo
    {
        return $this->belongsTo(LiveAccount::class, 'live_account_id');
    }

    public function gmvAdjustments(): HasMany
    {
        return $this->hasMany(LiveSessionGmvAdjustment::class);
    }

    /**
     * Recompute the cached `gmv_adjustment` aggregate from approved
     * adjustments and, if the session is already verified (gmv_locked_at set),
     * re-snapshot `commission_snapshot_json` so the audit trail reflects the
     * new net_gmv. Only `approved` rows contribute.
     */
    public function recalcCachedGmvAdjustment(?User $actor = null): void
    {
        $this->gmv_adjustment = (float) $this->gmvAdjustments()->approved()->sum('amount_myr');

        if ($this->gmv_locked_at !== null) {
            $this->commission_snapshot_json = app(CommissionCalculator::class)->snapshot($this, $actor);
        }

        $this->save();
    }

    /**
     * True when a locked payroll run already covers this session's end date.
     * Locked periods are snapshotted, so their feeding numbers must not drift —
     * callers use this to skip auto-deductions that would break the contract.
     */
    public function isInLockedPayrollPeriod(): bool
    {
        if ($this->actual_end_at === null) {
            return false;
        }

        return LiveHostPayrollRun::query()
            ->where('status', 'locked')
            ->where('period_start', '<=', $this->actual_end_at)
            ->where('period_end', '>=', $this->actual_end_at)
            ->exists();
    }

    public function liveSchedule(): BelongsTo
    {
        return $this->belongsTo(LiveSchedule::class);
    }

    public function liveScheduleAssignment(): BelongsTo
    {
        return $this->belongsTo(LiveScheduleAssignment::class);
    }

    public function liveHost(): BelongsTo
    {
        return $this->belongsTo(User::class, 'live_host_id');
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function matchedActualLiveRecord(): BelongsTo
    {
        return $this->belongsTo(ActualLiveRecord::class, 'matched_actual_live_record_id');
    }

    /**
     * All TikTok records attributed to this session (a split live that blipped
     * shows up as 2+ back-to-back records). session.gmv_amount is the SUM of
     * these records' live_attributed_gmv_myr. matchedActualLiveRecord() is kept
     * as the denormalized "primary" pointer for backward compatibility.
     */
    public function actualLiveRecords(): BelongsToMany
    {
        return $this->belongsToMany(ActualLiveRecord::class, 'live_session_actual_live_record')
            ->withPivot(['is_primary', 'live_attributed_gmv_myr', 'linked_by', 'linked_at'])
            ->withTimestamps();
    }

    public function verificationEvents(): HasMany
    {
        return $this->hasMany(LiveSessionVerificationEvent::class);
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
        return $query->whereIn('status', ['ended', 'cancelled', 'missed'])
            ->orderByDesc('scheduled_start_at');
    }

    public function scopeUploaded(Builder $query): Builder
    {
        return $query->whereNotNull('uploaded_at');
    }

    public function scopeNotUploaded(Builder $query): Builder
    {
        return $query->whereNull('uploaded_at')
            ->where('status', 'ended');
    }

    public function scopeForHost(Builder $query, int $hostId): Builder
    {
        return $query->where('live_host_id', $hostId);
    }

    public function scopeNeedsUpload(Builder $query): Builder
    {
        return $query->where('status', 'ended')
            ->whereNull('uploaded_at')
            ->where('scheduled_start_at', '<', now());
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

    public function uploadDetails(array $data): void
    {
        $actualStart = Carbon::parse($data['actual_start_at']);
        $actualEnd = Carbon::parse($data['actual_end_at']);

        $this->update([
            'actual_start_at' => $actualStart,
            'actual_end_at' => $actualEnd,
            'duration_minutes' => $actualStart->diffInMinutes($actualEnd),
            'image_path' => $data['image_path'] ?? null,
            'remarks' => $data['remarks'] ?? null,
            'uploaded_at' => now(),
            'uploaded_by' => auth()->id(),
        ]);
    }

    public function isUploaded(): bool
    {
        return $this->uploaded_at !== null;
    }

    public function canUpload(): bool
    {
        return $this->status === 'ended' && ! $this->isUploaded();
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

    public function isMissed(): bool
    {
        return $this->status === 'missed';
    }

    /**
     * True when the host may submit a recap for this session. Returns true for
     * already-ended or already-missed sessions (hosts may correct a mistake or
     * add more proof — re-submission is explicitly allowed by design) and for
     * scheduled sessions whose start time has passed. Future scheduled sessions
     * return false.
     */
    public function canRecap(): bool
    {
        if (in_array($this->status, ['ended', 'missed'], true)) {
            return true;
        }

        return $this->status === 'scheduled'
            && $this->scheduled_start_at !== null
            && $this->scheduled_start_at->lte(now());
    }

    /**
     * A session sourced from TikTok's own live data is already recorded by the
     * platform — the host has no manual proof to upload. Only manual ("Go Live")
     * sessions still owe a proof upload, so the "PERLU UPLOAD" nag (Pocket
     * schedule/recap list + overdue reminders) skips auto-recorded sessions.
     */
    public function isAutoRecorded(): bool
    {
        return $this->gmv_source === 'tiktok_actual';
    }

    /**
     * Nothing left for the host to do on this session: it's already been
     * verified / GMV-locked by the PIC, or it's auto-recorded from TikTok.
     * Either way there is no manual proof to upload and no recap to submit, so
     * every host-facing recap surface shows it as done rather than nagging.
     */
    public function isRecapSettled(): bool
    {
        return $this->gmv_locked_at !== null || $this->isAutoRecorded();
    }

    /**
     * Proof of live: at least one image or video attachment exists for this
     * session. Used by SaveRecapRequest when went_live=true to block the
     * status transition until the host has uploaded visible evidence.
     */
    public function hasVisualProof(): bool
    {
        return $this->attachments()
            ->where(function ($q) {
                $q->where('file_type', 'like', 'image/%')
                    ->orWhere('file_type', 'like', 'video/%');
            })
            ->exists();
    }

    /**
     * GMV proof: at least one attachment tagged
     * `tiktok_shop_screenshot` exists. Used by SaveRecapRequest when
     * `went_live=true` so the self-reported GMV is always accompanied by a
     * backend screenshot the PIC can cross-check during verification.
     */
    public function hasTikTokShopScreenshot(): bool
    {
        return $this->attachments()
            ->where('attachment_type', LiveSessionAttachment::TYPE_TIKTOK_SHOP_SCREENSHOT)
            ->exists();
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
            'missed' => 'amber',
            default => 'gray',
        };
    }
}
