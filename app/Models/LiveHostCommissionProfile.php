<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LiveHostCommissionProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'base_salary_myr',
        'per_live_rate_myr',
        'upline_user_id',
        'override_rate_l1_percent',
        'override_rate_l2_percent',
        'effective_from',
        'effective_to',
        'is_active',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'base_salary_myr' => 'decimal:2',
            'per_live_rate_myr' => 'decimal:2',
            'override_rate_l1_percent' => 'decimal:2',
            'override_rate_l2_percent' => 'decimal:2',
            'effective_from' => 'datetime',
            'effective_to' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function upline(): BelongsTo
    {
        return $this->belongsTo(User::class, 'upline_user_id');
    }
}
