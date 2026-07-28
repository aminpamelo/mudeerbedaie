<?php

namespace App\Models;

use Database\Factories\ExternalSystemFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExternalSystem extends Model
{
    /** @use HasFactory<ExternalSystemFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'base_url',
        'provision_path',
        'auth_type',
        'api_key',
        'signing_secret',
        'timeout',
        'is_active',
        'description',
    ];

    protected $hidden = [
        'api_key',
        'signing_secret',
    ];

    protected function casts(): array
    {
        return [
            'api_key' => 'encrypted',
            'signing_secret' => 'encrypted',
            'timeout' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<ExternalProvisioningRequest, $this>
     */
    public function provisioningRequests(): HasMany
    {
        return $this->hasMany(ExternalProvisioningRequest::class);
    }

    /**
     * @param  Builder<ExternalSystem>  $query
     * @return Builder<ExternalSystem>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Build a fully-qualified URL for a path against this system's base URL.
     */
    public function url(string $path): string
    {
        return rtrim($this->base_url, '/').'/'.ltrim($path, '/');
    }

    /**
     * The endpoint the provisioning request is POSTed to.
     */
    public function provisionUrl(): string
    {
        return $this->url($this->provision_path);
    }

    public function usesBearer(): bool
    {
        return in_array($this->auth_type, ['bearer', 'both'], true);
    }

    public function usesSignature(): bool
    {
        return in_array($this->auth_type, ['hmac', 'both'], true);
    }
}
