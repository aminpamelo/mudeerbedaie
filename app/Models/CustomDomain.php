<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class CustomDomain extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'funnel_id',
        'user_id',
        'domain',
        'type',
        'cloudflare_hostname_id',
        'verification_status',
        'ssl_status',
        'verification_errors',
        'verified_at',
        'ssl_active_at',
        'last_checked_at',
    ];

    protected function casts(): array
    {
        return [
            'verification_errors' => 'array',
            'verified_at' => 'datetime',
            'ssl_active_at' => 'datetime',
            'last_checked_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (CustomDomain $domain) {
            $domain->uuid = $domain->uuid ?? Str::uuid()->toString();
        });
    }

    public function funnel(): BelongsTo
    {
        return $this->belongsTo(Funnel::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isActive(): bool
    {
        return $this->verification_status === 'active' && $this->ssl_status === 'active';
    }

    public function isPending(): bool
    {
        return $this->verification_status === 'pending';
    }

    public function isFailed(): bool
    {
        return $this->verification_status === 'failed' || $this->ssl_status === 'failed';
    }

    public function isSubdomain(): bool
    {
        return $this->type === 'subdomain';
    }

    public function getFullDomainAttribute(): string
    {
        if ($this->isSubdomain()) {
            return $this->domain.'.'.config('services.cloudflare.subdomain_base');
        }

        return $this->domain;
    }
}
