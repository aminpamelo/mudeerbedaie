<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentMilestone extends Model
{
    use HasFactory;

    protected $fillable = ['class_student_id', 'title', 'achieved_at', 'awarded_by', 'type'];

    protected function casts(): array
    {
        return ['achieved_at' => 'datetime'];
    }

    public function classStudent(): BelongsTo
    {
        return $this->belongsTo(ClassStudent::class);
    }

    public function awardedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'awarded_by');
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }
}
