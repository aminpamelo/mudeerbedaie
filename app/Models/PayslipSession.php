<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayslipSession extends Model
{
    protected $fillable = [
        'payslip_id',
        'session_id',
        'amount',
        'included_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'included_at' => 'datetime',
        ];
    }

    // Relationships
    public function payslip(): BelongsTo
    {
        return $this->belongsTo(Payslip::class);
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(ClassSession::class, 'session_id');
    }

    // Scopes
    public function scopeForPayslip($query, int $payslipId)
    {
        return $query->where('payslip_id', $payslipId);
    }

    public function scopeForSession($query, int $sessionId)
    {
        return $query->where('session_id', $sessionId);
    }

    public function scopeIncludedInRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('included_at', [$startDate, $endDate]);
    }
}
