<?php

namespace App\Models;

use Database\Factories\BlogSubscriberFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A newsletter sign-up captured from an article. Source of truth for blog
 * subscribers; linking one into a CRM Audience is an explicit admin action
 * (audiences are student-backed, so it creates records that shouldn't be
 * conjured silently from an anonymous email).
 */
class BlogSubscriber extends Model
{
    /** @use HasFactory<BlogSubscriberFactory> */
    use HasFactory;

    protected $fillable = [
        'email',
        'name',
        'locale',
        'blog_post_id',
        'source',
        'audience_id',
        'confirmed_at',
        'unsubscribed_at',
        'token',
        'ip_address',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'confirmed_at' => 'datetime',
            'unsubscribed_at' => 'datetime',
        ];
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(BlogPost::class, 'blog_post_id');
    }

    public function audience(): BelongsTo
    {
        return $this->belongsTo(Audience::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('unsubscribed_at');
    }

    public function scopeUnsubscribed(Builder $query): Builder
    {
        return $query->whereNotNull('unsubscribed_at');
    }

    public function getIsActiveAttribute(): bool
    {
        return $this->unsubscribed_at === null;
    }
}
