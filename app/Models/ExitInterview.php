<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExitInterview extends Model
{
    /** @use HasFactory<\Database\Factories\ExitInterviewFactory> */
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'conducted_by',
        'interview_date',
        'reason_for_leaving',
        'overall_satisfaction',
        'would_recommend',
        'feedback',
        'improvements',
    ];

    protected function casts(): array
    {
        return [
            'interview_date' => 'date',
            'overall_satisfaction' => 'integer',
            'would_recommend' => 'boolean',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function conductor(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'conducted_by');
    }
}
