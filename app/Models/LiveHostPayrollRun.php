<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LiveHostPayrollRun extends Model
{
    protected $fillable = [
        'period_start',
        'period_end',
        'cutoff_date',
        'status',
        'locked_at',
        'locked_by',
        'paid_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'cutoff_date' => 'date',
            'locked_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    public function lockedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'locked_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(LiveHostPayrollItem::class, 'payroll_run_id');
    }
}
