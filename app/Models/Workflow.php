<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Workflow extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'name',
        'description',
        'type',
        'status',
        'trigger_type',
        'trigger_config',
        'canvas_data',
        'settings',
        'stats',
        'created_by',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'trigger_config' => 'array',
            'canvas_data' => 'array',
            'settings' => 'array',
            'stats' => 'array',
            'published_at' => 'datetime',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($workflow) {
            if (empty($workflow->uuid)) {
                $workflow->uuid = (string) Str::uuid();
            }
        });
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function steps(): HasMany
    {
        return $this->hasMany(WorkflowStep::class);
    }

    public function connections(): HasMany
    {
        return $this->hasMany(WorkflowConnection::class);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(WorkflowEnrollment::class);
    }

    public function activeEnrollments(): HasMany
    {
        return $this->hasMany(WorkflowEnrollment::class)->where('status', 'active');
    }

    public function triggerStep(): ?WorkflowStep
    {
        return $this->steps()->where('type', 'trigger')->first();
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function publish(): void
    {
        $this->update([
            'status' => 'active',
            'published_at' => now(),
        ]);
    }

    public function pause(): void
    {
        $this->update(['status' => 'paused']);
    }

    public function archive(): void
    {
        $this->update(['status' => 'archived']);
    }
}
