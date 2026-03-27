<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalaryComponent extends Model
{
    /** @use HasFactory<\Database\Factories\SalaryComponentFactory> */
    use HasFactory;

    protected $fillable = [
        'name', 'code', 'type', 'category',
        'is_taxable', 'is_epf_applicable', 'is_socso_applicable', 'is_eis_applicable',
        'is_system', 'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_taxable' => 'boolean',
            'is_epf_applicable' => 'boolean',
            'is_socso_applicable' => 'boolean',
            'is_eis_applicable' => 'boolean',
            'is_system' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function employeeSalaries(): HasMany
    {
        return $this->hasMany(EmployeeSalary::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeEarnings($query)
    {
        return $query->where('type', 'earning');
    }

    public function scopeDeductions($query)
    {
        return $query->where('type', 'deduction');
    }

    public function scopeSystem($query)
    {
        return $query->where('is_system', true);
    }
}
