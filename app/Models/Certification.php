<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Certification extends Model
{
    /** @use HasFactory<\Database\Factories\CertificationFactory> */
    use HasFactory;

    protected $fillable = ['name', 'issuing_body', 'description', 'validity_months', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'validity_months' => 'integer'];
    }

    public function employeeCertifications(): HasMany
    {
        return $this->hasMany(EmployeeCertification::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
