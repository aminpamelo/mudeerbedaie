<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeetingAiSummary extends Model
{
    use HasFactory;

    protected $fillable = [
        'meeting_id',
        'transcript_id',
        'summary',
        'key_points',
        'suggested_tasks',
        'status',
        'reviewed_by',
        'reviewed_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'key_points' => 'array',
            'suggested_tasks' => 'array',
            'reviewed_at' => 'datetime',
        ];
    }

    /**
     * Get the meeting this AI summary belongs to.
     */
    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    /**
     * Get the transcript this summary was generated from.
     */
    public function transcript(): BelongsTo
    {
        return $this->belongsTo(MeetingTranscript::class, 'transcript_id');
    }

    /**
     * Get the employee who reviewed this summary.
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'reviewed_by');
    }
}
