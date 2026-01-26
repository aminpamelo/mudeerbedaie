<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FunnelStepContent extends Model
{
    use HasFactory;

    protected $fillable = [
        'funnel_step_id',
        'template_id',
        'content',
        'custom_css',
        'custom_js',
        'meta_title',
        'meta_description',
        'og_image',
        'version',
        'is_published',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'content' => 'array',
            'is_published' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    public function step(): BelongsTo
    {
        return $this->belongsTo(FunnelStep::class, 'funnel_step_id');
    }

    public function publish(): void
    {
        $this->update([
            'is_published' => true,
            'published_at' => now(),
            'version' => $this->version + 1,
        ]);
    }

    public function unpublish(): void
    {
        $this->update(['is_published' => false]);
    }

    public function getMetaTitle(): string
    {
        return $this->meta_title ?? $this->step->name ?? '';
    }

    public function getMetaDescription(): string
    {
        return $this->meta_description ?? '';
    }

    public function hasCustomCss(): bool
    {
        return ! empty($this->custom_css);
    }

    public function hasCustomJs(): bool
    {
        return ! empty($this->custom_js);
    }
}
