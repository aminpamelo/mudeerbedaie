<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LiveHostMenteeStageHistory extends Model
{
    use HasFactory;

    protected $table = 'live_host_mentee_stage_history';

    protected $fillable = [
        'mentee_id', 'from_stage_id', 'to_stage_id',
        'action', 'notes', 'changed_by',
    ];

    public function mentee(): BelongsTo
    {
        return $this->belongsTo(LiveHostMentee::class, 'mentee_id');
    }

    public function fromStage(): BelongsTo
    {
        return $this->belongsTo(LiveHostMentoringStage::class, 'from_stage_id');
    }

    public function toStage(): BelongsTo
    {
        return $this->belongsTo(LiveHostMentoringStage::class, 'to_stage_id');
    }

    public function changedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
