<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Funnel extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'user_id',
        'template_id',
        'name',
        'slug',
        'description',
        'type',
        'status',
        'settings',
        'embed_settings',
        'embed_enabled',
        'embed_key',
        'affiliate_enabled',
        'affiliate_custom_url',
        'show_orders_in_admin',
        'disable_shipping',
        'payment_settings',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'embed_settings' => 'array',
            'embed_enabled' => 'boolean',
            'affiliate_enabled' => 'boolean',
            'show_orders_in_admin' => 'boolean',
            'disable_shipping' => 'boolean',
            'payment_settings' => 'array',
            'published_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Funnel $funnel) {
            $funnel->uuid = $funnel->uuid ?? Str::uuid()->toString();
            $funnel->slug = $funnel->slug ?? Str::slug($funnel->name).'-'.Str::random(6);
        });
    }

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(FunnelTemplate::class);
    }

    public function steps(): HasMany
    {
        return $this->hasMany(FunnelStep::class)->orderBy('sort_order');
    }

    public function activeSteps(): HasMany
    {
        return $this->hasMany(FunnelStep::class)->where('is_active', true)->orderBy('sort_order');
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(FunnelSession::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(FunnelOrder::class);
    }

    public function automations(): HasMany
    {
        return $this->hasMany(FunnelAutomation::class);
    }

    public function analytics(): HasMany
    {
        return $this->hasMany(FunnelAnalytics::class);
    }

    public function carts(): HasMany
    {
        return $this->hasMany(FunnelCart::class);
    }

    public function coupons(): HasMany
    {
        return $this->hasMany(FunnelCoupon::class);
    }

    public function affiliates(): BelongsToMany
    {
        return $this->belongsToMany(FunnelAffiliate::class, 'funnel_affiliate_funnels', 'funnel_id', 'affiliate_id')
            ->withPivot('status', 'joined_at')
            ->withTimestamps();
    }

    public function affiliateCommissions(): HasMany
    {
        return $this->hasMany(FunnelAffiliateCommission::class);
    }

    public function affiliateCommissionRules(): HasMany
    {
        return $this->hasMany(FunnelAffiliateCommissionRule::class);
    }

    // Status helpers
    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isArchived(): bool
    {
        return $this->status === 'archived';
    }

    public function publish(): void
    {
        $this->update([
            'status' => 'published',
            'published_at' => now(),
        ]);
    }

    public function unpublish(): void
    {
        $this->update(['status' => 'draft']);
    }

    public function archive(): void
    {
        $this->update(['status' => 'archived']);
    }

    // Step helpers
    public function getEntryStep(): ?FunnelStep
    {
        return $this->activeSteps()->orderBy('sort_order')->first();
    }

    public function getCheckoutStep(): ?FunnelStep
    {
        return $this->steps()->where('type', 'checkout')->first();
    }

    public function getThankYouStep(): ?FunnelStep
    {
        return $this->steps()->where('type', 'thankyou')->first();
    }

    // URL helpers
    public function getPublicUrl(): string
    {
        return url("/f/{$this->slug}");
    }

    public function getBuilderUrl(): string
    {
        return url("/funnel-builder/{$this->uuid}");
    }

    // Analytics helpers
    public function getTotalRevenue(): float
    {
        return $this->orders()->sum('funnel_revenue');
    }

    public function getTotalConversions(): int
    {
        return $this->sessions()->where('status', 'converted')->count();
    }

    public function getTotalVisitors(): int
    {
        return $this->sessions()->count();
    }

    public function getConversionRate(): float
    {
        $totalSessions = $this->getTotalVisitors();
        if ($totalSessions === 0) {
            return 0;
        }

        return round(($this->getTotalConversions() / $totalSessions) * 100, 2);
    }

    public function getAbandonedCartsCount(): int
    {
        return $this->carts()->whereIn('recovery_status', ['pending', 'sent'])->count();
    }

    // Duplication
    public function duplicate(?string $newName = null): self
    {
        $newFunnel = $this->replicate(['uuid', 'slug', 'published_at']);
        $newFunnel->name = $newName ?? $this->name.' (Copy)';
        $newFunnel->status = 'draft';
        $newFunnel->uuid = Str::uuid()->toString();
        $newFunnel->slug = Str::slug($newFunnel->name).'-'.Str::random(6);
        $newFunnel->save();

        // Duplicate steps
        foreach ($this->steps as $step) {
            $newStep = $step->replicate();
            $newStep->funnel_id = $newFunnel->id;
            $newStep->save();

            // Duplicate step content
            if ($step->content) {
                $newContent = $step->content->replicate();
                $newContent->funnel_step_id = $newStep->id;
                $newContent->is_published = false;
                $newContent->published_at = null;
                $newContent->save();
            }

            // Duplicate products
            foreach ($step->products as $product) {
                $newProduct = $product->replicate();
                $newProduct->funnel_step_id = $newStep->id;
                $newProduct->save();
            }

            // Duplicate order bumps
            foreach ($step->orderBumps as $bump) {
                $newBump = $bump->replicate();
                $newBump->funnel_step_id = $newStep->id;
                $newBump->save();
            }
        }

        return $newFunnel;
    }

    // Scopes
    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }

    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeAffiliateEnabled($query)
    {
        return $query->where('affiliate_enabled', true);
    }

    public function isAffiliateEnabled(): bool
    {
        return $this->affiliate_enabled;
    }

    public function shouldShowOrdersInAdmin(): bool
    {
        return $this->show_orders_in_admin;
    }
}
