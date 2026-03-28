<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MeetingRecording extends Model
{
    use HasFactory;

    protected $fillable = [
        'meeting_id',
        'file_name',
        'file_path',
        'file_size',
        'file_type',
        'duration_seconds',
        'source',
        'uploaded_by',
    ];

    /**
     * Get the meeting this recording belongs to.
     */
    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    /**
     * Get the employee who uploaded this recording.
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'uploaded_by');
    }

    /**
     * Get the transcripts generated from this recording.
     */
    public function transcripts(): HasMany
    {
        return $this->hasMany(MeetingTranscript::class, 'recording_id');
    }
}
