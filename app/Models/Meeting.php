<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Meeting extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'meeting_series_id',
        'title',
        'description',
        'location',
        'meeting_date',
        'start_time',
        'end_time',
        'status',
        'organizer_id',
        'note_taker_id',
        'created_by',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'meeting_date' => 'date',
        ];
    }

    /**
     * Get the series this meeting belongs to.
     */
    public function series(): BelongsTo
    {
        return $this->belongsTo(MeetingSeries::class, 'meeting_series_id');
    }

    /**
     * Get the organizer of this meeting.
     */
    public function organizer(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'organizer_id');
    }

    /**
     * Get the note taker for this meeting.
     */
    public function noteTaker(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'note_taker_id');
    }

    /**
     * Get the user who created this meeting.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the attendees for this meeting.
     */
    public function attendees(): HasMany
    {
        return $this->hasMany(MeetingAttendee::class);
    }

    /**
     * Get the agenda items for this meeting.
     */
    public function agendaItems(): HasMany
    {
        return $this->hasMany(MeetingAgendaItem::class);
    }

    /**
     * Get the decisions made in this meeting.
     */
    public function decisions(): HasMany
    {
        return $this->hasMany(MeetingDecision::class);
    }

    /**
     * Get the attachments for this meeting.
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(MeetingAttachment::class);
    }

    /**
     * Get the recordings for this meeting.
     */
    public function recordings(): HasMany
    {
        return $this->hasMany(MeetingRecording::class);
    }

    /**
     * Get the transcripts for this meeting.
     */
    public function transcripts(): HasMany
    {
        return $this->hasMany(MeetingTranscript::class);
    }

    /**
     * Get the AI summaries for this meeting.
     */
    public function aiSummaries(): HasMany
    {
        return $this->hasMany(MeetingAiSummary::class);
    }

    /**
     * Get the tasks associated with this meeting.
     */
    public function tasks(): MorphMany
    {
        return $this->morphMany(Task::class, 'taskable');
    }
}
