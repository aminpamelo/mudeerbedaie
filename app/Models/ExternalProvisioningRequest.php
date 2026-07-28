<?php

namespace App\Models;

use Database\Factories\ExternalProvisioningRequestFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExternalProvisioningRequest extends Model
{
    /** @use HasFactory<ExternalProvisioningRequestFactory> */
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_SUCCEEDED = 'succeeded';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'external_system_id',
        'product_order_id',
        'funnel_product_id',
        'idempotency_key',
        'status',
        'request_payload',
        'response_payload',
        'external_user_id',
        'login_url',
        'credentials',
        'attempts',
        'last_error',
        'provisioned_at',
    ];

    protected $hidden = [
        'credentials',
    ];

    protected function casts(): array
    {
        return [
            'request_payload' => 'array',
            'response_payload' => 'array',
            'credentials' => 'encrypted:array',
            'attempts' => 'integer',
            'provisioned_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<ExternalSystem, $this>
     */
    public function externalSystem(): BelongsTo
    {
        return $this->belongsTo(ExternalSystem::class);
    }

    /**
     * @return BelongsTo<ProductOrder, $this>
     */
    public function productOrder(): BelongsTo
    {
        return $this->belongsTo(ProductOrder::class);
    }

    /**
     * @return BelongsTo<FunnelProduct, $this>
     */
    public function funnelProduct(): BelongsTo
    {
        return $this->belongsTo(FunnelProduct::class);
    }

    /**
     * @param  Builder<ExternalProvisioningRequest>  $query
     * @return Builder<ExternalProvisioningRequest>
     */
    public function scopeSucceeded(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_SUCCEEDED);
    }

    public function isSucceeded(): bool
    {
        return $this->status === self::STATUS_SUCCEEDED;
    }

    public function markProcessing(): void
    {
        $this->forceFill([
            'status' => self::STATUS_PROCESSING,
            'attempts' => $this->attempts + 1,
        ])->save();
    }

    /**
     * @param  array<string, mixed>  $response
     * @param  array<string, mixed>  $credentials
     */
    public function markSucceeded(array $response, ?string $externalUserId, ?string $loginUrl, array $credentials): void
    {
        $this->forceFill([
            'status' => self::STATUS_SUCCEEDED,
            'response_payload' => $response,
            'external_user_id' => $externalUserId,
            'login_url' => $loginUrl,
            'credentials' => $credentials ?: null,
            'last_error' => null,
            'provisioned_at' => now(),
        ])->save();
    }

    public function markFailed(string $error): void
    {
        $this->forceFill([
            'status' => self::STATUS_FAILED,
            'last_error' => $error,
        ])->save();
    }
}
