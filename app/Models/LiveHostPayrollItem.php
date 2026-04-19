<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LiveHostPayrollItem extends Model
{
    protected $fillable = [
        'payroll_run_id',
        'user_id',
        'base_salary_myr',
        'sessions_count',
        'total_per_live_myr',
        'total_gmv_myr',
        'total_gmv_adjustment_myr',
        'net_gmv_myr',
        'gmv_commission_myr',
        'override_l1_myr',
        'override_l2_myr',
        'gross_total_myr',
        'deductions_myr',
        'net_payout_myr',
        'calculation_breakdown_json',
    ];

    protected function casts(): array
    {
        return [
            'base_salary_myr' => 'decimal:2',
            'total_per_live_myr' => 'decimal:2',
            'total_gmv_myr' => 'decimal:2',
            'total_gmv_adjustment_myr' => 'decimal:2',
            'net_gmv_myr' => 'decimal:2',
            'gmv_commission_myr' => 'decimal:2',
            'override_l1_myr' => 'decimal:2',
            'override_l2_myr' => 'decimal:2',
            'gross_total_myr' => 'decimal:2',
            'deductions_myr' => 'decimal:2',
            'net_payout_myr' => 'decimal:2',
            'calculation_breakdown_json' => 'array',
        ];
    }

    public function payrollRun(): BelongsTo
    {
        return $this->belongsTo(LiveHostPayrollRun::class, 'payroll_run_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
