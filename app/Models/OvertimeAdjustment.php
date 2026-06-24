<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OvertimeAdjustment extends Model
{
    protected $fillable = [
        'employee_id',
        'hours',
        'effective_date',
        'reason',
        'adjusted_by',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'hours' => 'decimal:1',
            'effective_date' => 'date',
        ];
    }

    /**
     * The employee this adjustment applies to.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * The admin user who recorded this adjustment.
     */
    public function adjuster(): BelongsTo
    {
        return $this->belongsTo(User::class, 'adjusted_by');
    }
}
