<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;

class LiveHostMentee extends Model
{
    use HasFactory;

    protected $table = 'live_host_mentees';

    protected $fillable = [
        'program_id', 'mentee_user_id', 'mentor_user_id', 'mentee_number',
        'current_stage_id', 'status', 'level_id', 'level_source',
        'level_assigned_at', 'level_assigned_by', 'rating', 'notes',
        'enrolled_at', 'graduated_at',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'level_assigned_at' => 'datetime',
            'enrolled_at' => 'datetime',
            'graduated_at' => 'datetime',
        ];
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(LiveHostMentoringProgram::class, 'program_id');
    }

    public function menteeUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'mentee_user_id');
    }

    public function mentor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'mentor_user_id');
    }

    public function currentStage(): BelongsTo
    {
        return $this->belongsTo(LiveHostMentoringStage::class, 'current_stage_id');
    }

    public function level(): BelongsTo
    {
        return $this->belongsTo(LiveHostMentoringLevel::class, 'level_id');
    }

    public function levelAssignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'level_assigned_by');
    }

    public function history(): HasMany
    {
        return $this->hasMany(LiveHostMenteeStageHistory::class, 'mentee_id')->latest();
    }

    public function stageRows(): HasMany
    {
        return $this->hasMany(LiveHostMenteeStage::class, 'mentee_id');
    }

    public function currentStageRow(): HasOne
    {
        return $this->hasOne(LiveHostMenteeStage::class, 'mentee_id')
            ->whereNull('exited_at')
            ->latestOfMany('entered_at');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(LiveHostMentoringActivity::class, 'mentee_id')->latest('occurred_at');
    }

    public function checklistItems(): HasMany
    {
        return $this->hasMany(LiveHostMenteeChecklistItem::class, 'mentee_id')->orderBy('position');
    }

    public function monthlyScores(): HasMany
    {
        return $this->hasMany(LiveHostMenteeMonthlyScore::class, 'mentee_id');
    }

    public function dailyMetrics(): HasMany
    {
        return $this->hasMany(LiveHostMenteeDailyMetric::class, 'mentee_id');
    }

    public function disciplinaryRecords(): HasMany
    {
        return $this->hasMany(LiveHostMenteeDisciplinaryRecord::class, 'mentee_id')
            ->orderByDesc('incident_date');
    }

    public function dailyVideos(): HasMany
    {
        return $this->hasMany(LiveHostMenteeDailyVideo::class, 'mentee_id')
            ->orderByDesc('video_date')
            ->orderByDesc('created_at');
    }

    /**
     * The mentor accountable for this mentee — the per-mentee override when set,
     * otherwise the program's leader.
     */
    public function effectiveMentorId(): ?int
    {
        return $this->mentor_user_id ?? $this->program?->leader_user_id;
    }

    public function isMentoredBy(int $userId): bool
    {
        return $this->effectiveMentorId() === $userId;
    }

    /**
     * Mentees a given top host is responsible for: an explicit per-mentee
     * mentor override, or (when none) the program they lead.
     */
    public function scopeMentoredBy(Builder $query, int $userId): Builder
    {
        return $query->where(function (Builder $q) use ($userId) {
            $q->where('mentor_user_id', $userId)
                ->orWhere(function (Builder $q2) use ($userId) {
                    $q2->whereNull('mentor_user_id')
                        ->whereHas('program', fn (Builder $p) => $p->where('leader_user_id', $userId));
                });
        });
    }

    public function getNameAttribute(): ?string
    {
        return $this->menteeUser?->name;
    }

    public static function generateMenteeNumber(): string
    {
        return DB::transaction(function () {
            $yearMonth = now()->format('Ym');
            $prefix = "LHM-{$yearMonth}-";
            $last = static::query()
                ->where('mentee_number', 'like', $prefix.'%')
                ->lockForUpdate()
                ->orderByDesc('mentee_number')
                ->first();
            $next = $last ? ((int) substr($last->mentee_number, -4)) + 1 : 1;

            return $prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
        });
    }
}
