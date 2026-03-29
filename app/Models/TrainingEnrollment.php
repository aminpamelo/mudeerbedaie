<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrainingEnrollment extends Model
{
    /** @use HasFactory<\Database\Factories\TrainingEnrollmentFactory> */
    use HasFactory;

    protected $fillable = [
        'training_program_id', 'employee_id', 'enrolled_by', 'status',
        'attendance_confirmed_at', 'feedback', 'feedback_rating', 'certificate_path',
    ];

    protected function casts(): array
    {
        return [
            'attendance_confirmed_at' => 'datetime',
            'feedback_rating' => 'integer',
        ];
    }

    public function trainingProgram(): BelongsTo
    {
        return $this->belongsTo(TrainingProgram::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function enrolledByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'enrolled_by');
    }
}
