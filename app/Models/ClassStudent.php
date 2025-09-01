<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClassStudent extends Model
{
    protected $fillable = [
        'class_id',
        'student_id',
        'enrolled_at',
        'left_at',
        'status',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'enrolled_at' => 'datetime',
            'left_at' => 'datetime',
        ];
    }

    public function class(): BelongsTo
    {
        return $this->belongsTo(ClassModel::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function hasLeft(): bool
    {
        return in_array($this->status, ['transferred', 'quit', 'completed']);
    }

    public function markAsTransferred(?string $reason = null): void
    {
        $this->update([
            'status' => 'transferred',
            'left_at' => now(),
            'reason' => $reason,
        ]);
    }

    public function markAsQuit(?string $reason = null): void
    {
        $this->update([
            'status' => 'quit',
            'left_at' => now(),
            'reason' => $reason,
        ]);
    }

    public function markAsCompleted(): void
    {
        $this->update([
            'status' => 'completed',
            'left_at' => now(),
        ]);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeForClass($query, $classId)
    {
        return $query->where('class_id', $classId);
    }

    public function scopeActiveOnDate($query, $date)
    {
        return $query->where('status', 'active')
            ->where('enrolled_at', '<=', $date)
            ->where(function ($q) use ($date) {
                $q->whereNull('left_at')
                    ->orWhere('left_at', '>', $date);
            });
    }
}
