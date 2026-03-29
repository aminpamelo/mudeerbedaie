<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OfferLetter extends Model
{
    use HasFactory;

    protected $fillable = [
        'applicant_id', 'position_id', 'offered_salary', 'start_date',
        'employment_type', 'status', 'template_data', 'pdf_path',
        'sent_at', 'responded_at', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'offered_salary' => 'decimal:2',
            'start_date' => 'date',
            'template_data' => 'array',
            'sent_at' => 'datetime',
            'responded_at' => 'datetime',
        ];
    }

    public function applicant(): BelongsTo
    {
        return $this->belongsTo(Applicant::class);
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
