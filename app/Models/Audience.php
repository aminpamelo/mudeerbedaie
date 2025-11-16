<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Audience extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => 'string',
        ];
    }

    public function students(): BelongsToMany
    {
        return $this->belongsToMany(Student::class, 'audience_student')
            ->withPivot('subscribed_at')
            ->withTimestamps();
    }

    public function activeStudents(): BelongsToMany
    {
        return $this->belongsToMany(Student::class, 'audience_student')
            ->where('students.status', 'active')
            ->withPivot('subscribed_at')
            ->withTimestamps();
    }

    public function getStudentCountAttribute(): int
    {
        return $this->students()->count();
    }

    public function getSubscribedStudentCountAttribute(): int
    {
        return $this->students()->count();
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function broadcasts(): BelongsToMany
    {
        return $this->belongsToMany(Broadcast::class, 'broadcast_audience')
            ->withTimestamps();
    }
}
