<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LiveHostApplicant extends Model
{
    use HasFactory;

    protected $fillable = [
        'campaign_id', 'applicant_number', 'full_name', 'email', 'phone',
        'ic_number', 'location', 'platforms', 'experience_summary', 'motivation',
        'resume_path', 'source', 'current_stage_id', 'status', 'rating', 'notes',
        'applied_at', 'hired_at', 'hired_user_id',
    ];

    protected function casts(): array
    {
        return [
            'platforms' => 'array',
            'rating' => 'integer',
            'applied_at' => 'datetime',
            'hired_at' => 'datetime',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(LiveHostRecruitmentCampaign::class, 'campaign_id');
    }

    public function currentStage(): BelongsTo
    {
        return $this->belongsTo(LiveHostRecruitmentStage::class, 'current_stage_id');
    }

    public function hiredUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'hired_user_id');
    }

    public function history(): HasMany
    {
        return $this->hasMany(LiveHostApplicantStageHistory::class, 'applicant_id')->latest();
    }

    public static function generateApplicantNumber(): string
    {
        $yearMonth = now()->format('Ym');
        $prefix = "LHA-{$yearMonth}-";
        $last = static::query()
            ->where('applicant_number', 'like', $prefix.'%')
            ->orderByDesc('applicant_number')
            ->first();
        $next = $last ? ((int) substr($last->applicant_number, -4)) + 1 : 1;

        return $prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }
}
