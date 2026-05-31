<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LiveHostMentoringProgram extends Model
{
    use HasFactory;

    protected $fillable = [
        'title', 'slug', 'description', 'status',
        'leader_user_id', 'starts_at', 'ends_at', 'created_by',
        'checklist_template',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'checklist_template' => 'array',
        ];
    }

    /**
     * Sensible default task checklist seeded onto each mentee at enrolment.
     * Fully editable per program. Each item: {title, is_required}.
     *
     * @return array<int, array{title: string, is_required: bool}>
     */
    public static function defaultChecklistTemplate(): array
    {
        return [
            ['title' => 'Complete onboarding orientation', 'is_required' => true],
            ['title' => 'Join first group coaching session', 'is_required' => true],
            ['title' => "Shadow a senior host's live", 'is_required' => true],
            ['title' => 'Complete product knowledge training', 'is_required' => true],
            ['title' => 'Run first solo live session', 'is_required' => true],
            ['title' => '1:1 performance review with mentor', 'is_required' => false],
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function leader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'leader_user_id');
    }

    public function stages(): HasMany
    {
        return $this->hasMany(LiveHostMentoringStage::class, 'program_id')->orderBy('position');
    }

    public function mentees(): HasMany
    {
        return $this->hasMany(LiveHostMentee::class, 'program_id');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(LiveHostMentoringActivity::class, 'program_id');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    protected static function booted(): void
    {
        static::creating(function (self $program) {
            if (empty($program->checklist_template)) {
                $program->checklist_template = self::defaultChecklistTemplate();
            }
        });

        static::created(function (self $program) {
            $program->stages()->createMany([
                ['position' => 1, 'name' => 'Onboarding', 'is_final' => false],
                ['position' => 2, 'name' => 'Coaching', 'is_final' => false],
                ['position' => 3, 'name' => 'Training', 'is_final' => false],
                ['position' => 4, 'name' => 'Evaluation', 'is_final' => false],
                ['position' => 5, 'name' => 'Graduated', 'is_final' => true],
            ]);
        });
    }
}
