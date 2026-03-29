<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class DisciplinaryAction extends Model
{
    /** @use HasFactory<\Database\Factories\DisciplinaryActionFactory> */
    use HasFactory;

    protected $fillable = [
        'reference_number',
        'employee_id',
        'type',
        'reason',
        'incident_date',
        'issued_date',
        'issued_by',
        'response_required',
        'response_deadline',
        'employee_response',
        'responded_at',
        'outcome',
        'letter_pdf_path',
        'status',
        'previous_action_id',
    ];

    protected function casts(): array
    {
        return [
            'incident_date' => 'date',
            'issued_date' => 'date',
            'response_required' => 'boolean',
            'response_deadline' => 'date',
            'responded_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'issued_by');
    }

    public function previousAction(): BelongsTo
    {
        return $this->belongsTo(DisciplinaryAction::class, 'previous_action_id');
    }

    public function inquiry(): HasOne
    {
        return $this->hasOne(DisciplinaryInquiry::class);
    }

    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', 'draft');
    }

    public function scopeIssued(Builder $query): Builder
    {
        return $query->where('status', 'issued');
    }

    public function scopePendingResponse(Builder $query): Builder
    {
        return $query->where('status', 'pending_response');
    }

    public static function generateReferenceNumber(): string
    {
        $prefix = 'DA-'.now()->format('Ym').'-';
        $lastAction = static::where('reference_number', 'like', $prefix.'%')
            ->orderByDesc('reference_number')
            ->first();

        if ($lastAction) {
            $lastNumber = (int) substr($lastAction->reference_number, -4);
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 1;
        }

        return $prefix.str_pad((string) $nextNumber, 4, '0', STR_PAD_LEFT);
    }
}
