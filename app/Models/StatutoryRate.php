<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StatutoryRate extends Model
{
    /** @use HasFactory<\Database\Factories\StatutoryRateFactory> */
    use HasFactory;

    protected $fillable = [
        'type', 'min_salary', 'max_salary', 'rate_percentage', 'fixed_amount',
        'effective_from', 'effective_to',
    ];

    protected function casts(): array
    {
        return [
            'min_salary' => 'decimal:2',
            'max_salary' => 'decimal:2',
            'rate_percentage' => 'decimal:2',
            'fixed_amount' => 'decimal:2',
            'effective_from' => 'date',
            'effective_to' => 'date',
        ];
    }

    public function scopeForType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeCurrent($query)
    {
        return $query->where('effective_from', '<=', now())
            ->where(function ($q) {
                $q->whereNull('effective_to')
                    ->orWhere('effective_to', '>=', now());
            });
    }
}
