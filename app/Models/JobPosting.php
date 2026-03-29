<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JobPosting extends Model
{
    use HasFactory;

    protected $fillable = [
        'title', 'department_id', 'position_id', 'description', 'requirements',
        'employment_type', 'salary_range_min', 'salary_range_max', 'show_salary',
        'vacancies', 'status', 'published_at', 'closing_date', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'salary_range_min' => 'decimal:2',
            'salary_range_max' => 'decimal:2',
            'show_salary' => 'boolean',
            'published_at' => 'datetime',
            'closing_date' => 'date',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function applicants(): HasMany
    {
        return $this->hasMany(Applicant::class);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', 'open');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'open')->whereNotNull('published_at');
    }
}
