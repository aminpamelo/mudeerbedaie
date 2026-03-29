<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DisciplinaryInquiry extends Model
{
    /** @use HasFactory<\Database\Factories\DisciplinaryInquiryFactory> */
    use HasFactory;

    protected $fillable = [
        'disciplinary_action_id',
        'hearing_date',
        'hearing_time',
        'location',
        'panel_members',
        'minutes',
        'findings',
        'decision',
        'penalty',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'hearing_date' => 'date',
            'panel_members' => 'array',
        ];
    }

    public function disciplinaryAction(): BelongsTo
    {
        return $this->belongsTo(DisciplinaryAction::class);
    }
}
