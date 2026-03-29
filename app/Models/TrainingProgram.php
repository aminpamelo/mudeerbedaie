<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TrainingProgram extends Model
{
    /** @use HasFactory<\Database\Factories\TrainingProgramFactory> */
    use HasFactory;

    protected $fillable = [
        'title', 'description', 'type', 'category', 'provider', 'location',
        'start_date', 'end_date', 'start_time', 'end_time',
        'max_participants', 'cost_per_person', 'total_budget', 'status', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'cost_per_person' => 'decimal:2',
            'total_budget' => 'decimal:2',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(TrainingEnrollment::class);
    }

    public function costs(): HasMany
    {
        return $this->hasMany(TrainingCost::class);
    }

    public function scopePlanned(Builder $query): Builder
    {
        return $query->where('status', 'planned');
    }

    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where('start_date', '>', now())->where('status', 'planned');
    }
}
