<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MeetingTranscript extends Model
{
    use HasFactory;

    protected $fillable = [
        'meeting_id',
        'recording_id',
        'content',
        'language',
        'status',
        'processed_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'processed_at' => 'datetime',
        ];
    }

    /**
     * Get the meeting this transcript belongs to.
     */
    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    /**
     * Get the recording this transcript was generated from.
     */
    public function recording(): BelongsTo
    {
        return $this->belongsTo(MeetingRecording::class, 'recording_id');
    }

    /**
     * Get the AI summaries generated from this transcript.
     */
    public function aiSummaries(): HasMany
    {
        return $this->hasMany(MeetingAiSummary::class, 'transcript_id');
    }
}
