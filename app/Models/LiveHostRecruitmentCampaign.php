<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LiveHostRecruitmentCampaign extends Model
{
    use HasFactory;

    protected $fillable = [
        'title', 'slug', 'description', 'status', 'target_count',
        'opens_at', 'closes_at', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'opens_at' => 'datetime',
            'closes_at' => 'datetime',
            'target_count' => 'integer',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function stages(): HasMany
    {
        return $this->hasMany(LiveHostRecruitmentStage::class, 'campaign_id')->orderBy('position');
    }

    public function applicants(): HasMany
    {
        return $this->hasMany(LiveHostApplicant::class, 'campaign_id');
    }

    public function isAcceptingApplications(): bool
    {
        if ($this->status !== 'open') {
            return false;
        }
        if ($this->closes_at !== null && $this->closes_at->isPast()) {
            return false;
        }

        return true;
    }
}
