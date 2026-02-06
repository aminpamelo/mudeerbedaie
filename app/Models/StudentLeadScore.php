<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StudentLeadScore extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'student_id',
        'total_score',
        'engagement_score',
        'purchase_score',
        'activity_score',
        'last_activity_at',
        'grade',
    ];

    protected function casts(): array
    {
        return [
            'total_score' => 'integer',
            'engagement_score' => 'integer',
            'purchase_score' => 'integer',
            'activity_score' => 'integer',
            'last_activity_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function history(): HasMany
    {
        return $this->hasMany(LeadScoreHistory::class, 'student_id', 'student_id');
    }

    public function addPoints(int $points, string $category = 'activity'): void
    {
        $categoryField = "{$category}_score";

        if (property_exists($this, $categoryField) || in_array($categoryField, $this->fillable)) {
            $this->increment($categoryField, $points);
        }

        $this->increment('total_score', $points);
        $this->update([
            'last_activity_at' => now(),
            'grade' => $this->calculateGrade($this->total_score + $points),
        ]);
    }

    public function calculateGrade(int $score): string
    {
        return match (true) {
            $score >= 100 => 'hot',
            $score >= 50 => 'warm',
            $score >= 20 => 'cold',
            default => 'inactive',
        };
    }

    public function recalculateGrade(): void
    {
        $this->update(['grade' => $this->calculateGrade($this->total_score)]);
    }

    public function isHot(): bool
    {
        return $this->grade === 'hot';
    }

    public function isWarm(): bool
    {
        return $this->grade === 'warm';
    }

    public function isCold(): bool
    {
        return $this->grade === 'cold';
    }

    public function isInactive(): bool
    {
        return $this->grade === 'inactive';
    }
}
