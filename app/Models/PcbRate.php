<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PcbRate extends Model
{
    /** @use HasFactory<\Database\Factories\PcbRateFactory> */
    use HasFactory;

    protected $fillable = [
        'category', 'num_children', 'min_monthly_income', 'max_monthly_income',
        'pcb_amount', 'year',
    ];

    protected function casts(): array
    {
        return [
            'min_monthly_income' => 'decimal:2',
            'max_monthly_income' => 'decimal:2',
            'pcb_amount' => 'decimal:2',
        ];
    }

    public function scopeForYear($query, int $year)
    {
        return $query->where('year', $year);
    }

    public function scopeForCategory($query, string $category)
    {
        return $query->where('category', $category);
    }
}
