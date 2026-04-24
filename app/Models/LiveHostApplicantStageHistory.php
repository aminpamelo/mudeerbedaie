<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LiveHostApplicantStageHistory extends Model
{
    protected $table = 'live_host_applicant_stage_history';

    protected $fillable = [
        'applicant_id', 'from_stage_id', 'to_stage_id',
        'action', 'notes', 'changed_by',
    ];

    public function applicant(): BelongsTo
    {
        return $this->belongsTo(LiveHostApplicant::class, 'applicant_id');
    }

    public function fromStage(): BelongsTo
    {
        return $this->belongsTo(LiveHostRecruitmentStage::class, 'from_stage_id');
    }

    public function toStage(): BelongsTo
    {
        return $this->belongsTo(LiveHostRecruitmentStage::class, 'to_stage_id');
    }

    public function changedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
