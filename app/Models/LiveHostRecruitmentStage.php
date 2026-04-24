<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LiveHostRecruitmentStage extends Model
{
    use HasFactory;

    protected $fillable = ['campaign_id', 'position', 'name', 'description', 'is_final'];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'is_final' => 'boolean',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(LiveHostRecruitmentCampaign::class, 'campaign_id');
    }

    public function applicants(): HasMany
    {
        return $this->hasMany(LiveHostApplicant::class, 'current_stage_id');
    }
}
