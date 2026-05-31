<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LiveHostMentoringStage extends Model
{
    use HasFactory;

    protected $fillable = ['program_id', 'position', 'name', 'description', 'is_final'];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'is_final' => 'boolean',
        ];
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(LiveHostMentoringProgram::class, 'program_id');
    }

    public function mentees(): HasMany
    {
        return $this->hasMany(LiveHostMentee::class, 'current_stage_id');
    }
}
