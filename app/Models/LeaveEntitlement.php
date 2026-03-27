<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveEntitlement extends Model
{
    /** @use HasFactory<\Database\Factories\LeaveEntitlementFactory> */
    use HasFactory;

    protected $fillable = [
        'leave_type_id',
        'employment_type',
        'min_service_months',
        'max_service_months',
        'days_per_year',
        'is_prorated',
        'carry_forward_max',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'days_per_year' => 'decimal:1',
            'is_prorated' => 'boolean',
            'min_service_months' => 'integer',
            'max_service_months' => 'integer',
            'carry_forward_max' => 'integer',
        ];
    }

    /**
     * Get the leave type for this entitlement.
     */
    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }
}
