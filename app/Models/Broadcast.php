<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Broadcast extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'sent_at' => 'datetime',
            'selected_students' => 'array',
            'design_json' => 'array',
        ];
    }

    protected $fillable = [
        'name',
        'type',
        'status',
        'from_name',
        'from_email',
        'reply_to_email',
        'subject',
        'preview_text',
        'content',
        'scheduled_at',
        'sent_at',
        'total_recipients',
        'total_sent',
        'total_failed',
        'selected_students',
        'design_json',
        'html_content',
        'editor_type',
        'email_template_id',
    ];

    public function audiences(): BelongsToMany
    {
        return $this->belongsToMany(Audience::class, 'broadcast_audience')
            ->withTimestamps();
    }

    public function logs(): HasMany
    {
        return $this->hasMany(BroadcastLog::class);
    }

    public function conversions(): HasMany
    {
        return $this->hasMany(BroadcastConversion::class);
    }

    public function getRecipientsAttribute(): \Illuminate\Support\Collection
    {
        // Use selected students if available, otherwise use all students from audiences
        if (! empty($this->selected_students)) {
            return Student::whereIn('id', $this->selected_students)->with('user')->get();
        }
        $studentIds = collect();
        foreach ($this->audiences as $audience) {
            $audienceStudentIds = $audience->students()->pluck('students.id');
            $studentIds = $studentIds->merge($audienceStudentIds);
        }

        return Student::whereIn('id', $studentIds->unique())->with('user')->get();
    }

    public function isVisualEditor(): bool
    {
        return $this->editor_type === 'visual';
    }

    public function getEffectiveContent(): string
    {
        return $this->isVisualEditor() ? ($this->html_content ?? '') : ($this->content ?? '');
    }

    /**
     * The student IDs this broadcast targets — the snapshot taken at build time,
     * falling back to the union of its audiences' members.
     *
     * @return array<int, int>
     */
    public function recipientStudentIds(): array
    {
        if (! empty($this->selected_students)) {
            return $this->selected_students;
        }

        return $this->audiences
            ->flatMap(fn (Audience $audience) => $audience->students()->pluck('students.id')->all())
            ->unique()
            ->values()
            ->all();
    }

    public function isInProgress(): bool
    {
        return in_array($this->status, ['sending', 'paused'], true);
    }

    public function canPause(): bool
    {
        return $this->status === 'sending';
    }

    public function canResume(): bool
    {
        return $this->status === 'paused';
    }

    public function canCancel(): bool
    {
        return in_array($this->status, ['scheduled', 'sending', 'paused'], true);
    }

    public function openedCount(): int
    {
        return $this->logs()->whereNotNull('opened_at')->count();
    }

    public function clickedCount(): int
    {
        return $this->logs()->whereNotNull('clicked_at')->count();
    }
}
