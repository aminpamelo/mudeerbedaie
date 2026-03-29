<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Applicant extends Model
{
    use HasFactory;

    protected $fillable = [
        'job_posting_id', 'applicant_number', 'full_name', 'email', 'phone',
        'ic_number', 'resume_path', 'cover_letter', 'source', 'current_stage',
        'rating', 'notes', 'applied_at',
    ];

    protected function casts(): array
    {
        return ['applied_at' => 'datetime'];
    }

    public function jobPosting(): BelongsTo
    {
        return $this->belongsTo(JobPosting::class);
    }

    public function stages(): HasMany
    {
        return $this->hasMany(ApplicantStage::class);
    }

    public function interviews(): HasMany
    {
        return $this->hasMany(Interview::class);
    }

    public function offerLetter(): HasOne
    {
        return $this->hasOne(OfferLetter::class);
    }

    public function scopeAtStage(Builder $query, string $stage): Builder
    {
        return $query->where('current_stage', $stage);
    }

    public static function generateApplicantNumber(): string
    {
        $yearMonth = now()->format('Ym');
        $prefix = "APP-{$yearMonth}-";
        $last = static::query()->where('applicant_number', 'like', $prefix.'%')->orderByDesc('applicant_number')->first();
        $nextNumber = $last ? (int) substr($last->applicant_number, -4) + 1 : 1;

        return $prefix.str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
    }
}
