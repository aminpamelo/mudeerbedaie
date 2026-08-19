<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LiveTimeSlot extends Model
{
    use HasFactory;

    protected $fillable = [
        'platform_account_id',
        'override_id',
        'day_of_week',
        'start_time',
        'end_time',
        'duration_minutes',
        'is_active',
        'is_ad_hoc',
        'sort_order',
        'created_by',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_ad_hoc' => 'boolean',
            'day_of_week' => 'integer',
        ];
    }

    public function platformAccount(): BelongsTo
    {
        return $this->belongsTo(PlatformAccount::class);
    }

    public function override(): BelongsTo
    {
        return $this->belongsTo(LiveTimeSlotOverride::class, 'override_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scheduleAssignments(): HasMany
    {
        return $this->hasMany(LiveScheduleAssignment::class, 'time_slot_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('start_time');
    }

    public function scopeForPlatform(Builder $query, int $platformAccountId): Builder
    {
        return $query->where('platform_account_id', $platformAccountId);
    }

    public function scopeForDay(Builder $query, int $dayOfWeek): Builder
    {
        return $query->where(function ($q) use ($dayOfWeek) {
            $q->where('day_of_week', $dayOfWeek)
                ->orWhereNull('day_of_week');
        });
    }

    public function scopeGlobal(Builder $query): Builder
    {
        return $query->whereNull('platform_account_id');
    }

    /**
     * Perpetual (reusable) slots only — the ones offered as calendar scaffolds,
     * modal presets and on the Time Slots page. Excludes one-off ad-hoc windows
     * created for a single assignment's custom time.
     */
    public function scopePerpetual(Builder $query): Builder
    {
        return $query->where('is_ad_hoc', false);
    }

    /**
     * Resolve the time_slot_id an assignment should point to from an optional
     * custom start/end time.
     *
     * - No custom time given → keep the preset the caller already chose.
     * - Preset chosen and its window is unchanged → keep the preset (no ad-hoc row).
     * - Otherwise → reuse an existing hidden ad-hoc slot with the same window, or
     *   create one. Ad-hoc slots are global (day/platform-agnostic) and deduped by
     *   window so a bespoke time never adds a recurring scaffold.
     */
    public static function resolveAssignmentTimeSlotId(
        ?string $startTime,
        ?string $endTime,
        ?int $presetId,
        ?int $createdBy = null,
    ): ?int {
        $start = self::normaliseTimeHm($startTime);
        $end = self::normaliseTimeHm($endTime);

        if ($start === null || $end === null) {
            return $presetId;
        }

        if ($presetId !== null) {
            $preset = self::find($presetId);
            if ($preset
                && substr((string) $preset->start_time, 0, 5) === $start
                && substr((string) $preset->end_time, 0, 5) === $end
            ) {
                return $presetId;
            }
        }

        $existing = self::query()
            ->where('is_ad_hoc', true)
            ->whereNull('override_id')
            ->where('start_time', "{$start}:00")
            ->where('end_time', "{$end}:00")
            ->first();

        if ($existing) {
            return $existing->id;
        }

        return self::create([
            'platform_account_id' => null,
            'day_of_week' => null,
            'start_time' => "{$start}:00",
            'end_time' => "{$end}:00",
            'is_active' => true,
            'is_ad_hoc' => true,
            'sort_order' => 0,
            'created_by' => $createdBy,
            'status' => 'active',
        ])->id;
    }

    /**
     * Normalise a "H:i" / "H:i:s" time string to "H:i", or null when blank.
     */
    private static function normaliseTimeHm(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        return substr(trim($value), 0, 5);
    }

    public function getDayNameAttribute(): ?string
    {
        if ($this->day_of_week === null) {
            return 'All Days';
        }

        return match ($this->day_of_week) {
            0 => 'Sunday',
            1 => 'Monday',
            2 => 'Tuesday',
            3 => 'Wednesday',
            4 => 'Thursday',
            5 => 'Friday',
            6 => 'Saturday',
            default => 'Unknown',
        };
    }

    public function getDayNameMsAttribute(): ?string
    {
        if ($this->day_of_week === null) {
            return 'Semua Hari';
        }

        return match ($this->day_of_week) {
            0 => 'Ahad',
            1 => 'Isnin',
            2 => 'Selasa',
            3 => 'Rabu',
            4 => 'Khamis',
            5 => 'Jumaat',
            6 => 'Sabtu',
            default => 'Unknown',
        };
    }

    public function getTimeRangeAttribute(): string
    {
        $start = Carbon::parse($this->start_time)->format('g:ia');
        $end = Carbon::parse($this->end_time)->format('g:ia');

        return "{$start} - {$end}";
    }

    public function getFormattedStartTimeAttribute(): string
    {
        return Carbon::parse($this->start_time)->format('g:ia');
    }

    public function getFormattedEndTimeAttribute(): string
    {
        return Carbon::parse($this->end_time)->format('g:ia');
    }

    protected static function booted(): void
    {
        static::creating(function (LiveTimeSlot $slot) {
            if (empty($slot->duration_minutes)) {
                $start = Carbon::parse($slot->start_time);
                $end = Carbon::parse($slot->end_time);
                $slot->duration_minutes = $start->diffInMinutes($end);
            }
        });
    }
}
