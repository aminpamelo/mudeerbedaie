<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * A reusable landing page design in the Funnel Studio Landing Library.
 * Applying it copies the page into a funnel step (copy-on-apply); the step
 * keeps landing_page_template_id so we know which template each funnel uses.
 */
class LandingPageTemplate extends Model
{
    /** @use HasFactory<\Database\Factories\LandingPageTemplateFactory> */
    use HasFactory;

    public const CATEGORIES = ['sales', 'lead', 'webinar', 'product', 'course', 'donation', 'thankyou', 'other'];

    protected $fillable = [
        'uuid',
        'created_by',
        'source_funnel_step_id',
        'name',
        'description',
        'category',
        'content',
        'custom_css',
        'custom_js',
        'usage_count',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'content' => 'array',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (LandingPageTemplate $template) {
            $template->uuid ??= (string) Str::uuid();
        });
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function sourceStep(): BelongsTo
    {
        return $this->belongsTo(FunnelStep::class, 'source_funnel_step_id');
    }

    public function steps(): HasMany
    {
        return $this->hasMany(FunnelStep::class);
    }
}
