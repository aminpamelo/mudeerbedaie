<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrainingBudget extends Model
{
    /** @use HasFactory<\Database\Factories\TrainingBudgetFactory> */
    use HasFactory;

    protected $fillable = ['department_id', 'year', 'allocated_amount', 'spent_amount'];

    protected function casts(): array
    {
        return ['year' => 'integer', 'allocated_amount' => 'decimal:2', 'spent_amount' => 'decimal:2'];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function getUtilizationPercentageAttribute(): float
    {
        if ($this->allocated_amount <= 0) {
            return 0;
        }

        return round(($this->spent_amount / $this->allocated_amount) * 100, 1);
    }
}
