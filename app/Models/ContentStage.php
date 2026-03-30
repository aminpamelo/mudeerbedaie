<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContentStage extends Model
{
    /** @use HasFactory<\Database\Factories\ContentStageFactory> */
    use HasFactory;

    protected $fillable = [
        'content_id',
        'stage',
        'status',
        'due_date',
        'started_at',
        'completed_at',
        'notes',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * Get the content this stage belongs to
     */
    public function content(): BelongsTo
    {
        return $this->belongsTo(Content::class);
    }

    /**
     * Get the assignees for this stage
     */
    public function assignees(): HasMany
    {
        return $this->hasMany(ContentStageAssignee::class);
    }
}
