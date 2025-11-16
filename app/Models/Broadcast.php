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
    ];
    public function audiences(): BelongsToMany
        return $this->belongsToMany(Audience::class, 'broadcast_audience')
            ->withTimestamps();
    public function logs(): HasMany
        return $this->hasMany(BroadcastLog::class);
    public function getRecipientsAttribute(): \Illuminate\Support\Collection
        // Use selected students if available, otherwise use all students from audiences
        if (! empty($this->selected_students)) {
            return Student::whereIn('id', $this->selected_students)->with('user')->get();
        }
        $studentIds = collect();
        foreach ($this->audiences as $audience) {
            $audienceStudentIds = $audience->students()->pluck('students.id');
            $studentIds = $studentIds->merge($audienceStudentIds);
        return Student::whereIn('id', $studentIds->unique())->with('user')->get();
}
