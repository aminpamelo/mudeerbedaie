<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class FunnelAffiliate extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'name',
        'phone',
        'email',
        'ref_code',
        'status',
        'metadata',
        'last_login_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'last_login_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (FunnelAffiliate $affiliate) {
            if (empty($affiliate->uuid)) {
                $affiliate->uuid = (string) Str::uuid();
            }
            if (empty($affiliate->ref_code)) {
                $affiliate->ref_code = static::generateRefCode();
            }
        });
    }

    // Relationships
    public function funnels(): BelongsToMany
    {
        return $this->belongsToMany(Funnel::class, 'funnel_affiliate_funnels', 'affiliate_id', 'funnel_id')
            ->withPivot('status', 'joined_at')
            ->withTimestamps();
    }

    public function commissions(): HasMany
    {
        return $this->hasMany(FunnelAffiliateCommission::class, 'affiliate_id');
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(FunnelSession::class, 'affiliate_id');
    }

    // Helpers
    public static function generateRefCode(): string
    {
        do {
            $code = 'AF'.strtoupper(Str::random(6));
        } while (static::where('ref_code', $code)->exists());

        return $code;
    }

    public function getAffiliateUrl(Funnel $funnel): string
    {
        return url("/f/{$funnel->slug}?ref={$this->ref_code}");
    }

    public function getAffiliatePathUrl(Funnel $funnel): string
    {
        return url("/f/{$funnel->slug}/ref/{$this->ref_code}");
    }

    public function getAffiliateCustomUrl(Funnel $funnel): ?string
    {
        if (empty($funnel->affiliate_custom_url)) {
            return null;
        }

        $url = $funnel->affiliate_custom_url;
        $separator = str_contains($url, '?') ? '&' : '?';

        return $url.$separator.'ref='.$this->ref_code;
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isInactive(): bool
    {
        return $this->status === 'inactive';
    }

    public function isBanned(): bool
    {
        return $this->status === 'banned';
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeByPhone($query, string $phone)
    {
        return $query->where('phone', $phone);
    }
}
