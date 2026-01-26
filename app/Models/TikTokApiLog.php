<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TikTokApiLog extends Model
{
    protected $table = 'tiktok_api_logs';

    protected $fillable = [
        'platform_account_id',
        'endpoint',
        'method',
        'request_payload',
        'response_payload',
        'status_code',
        'error_message',
        'duration_ms',
    ];

    protected function casts(): array
    {
        return [
            'request_payload' => 'array',
            'response_payload' => 'array',
        ];
    }

    public function platformAccount(): BelongsTo
    {
        return $this->belongsTo(PlatformAccount::class);
    }

    public function scopeByAccount(Builder $query, int $accountId): Builder
    {
        return $query->where('platform_account_id', $accountId);
    }

    public function scopeErrors(Builder $query): Builder
    {
        return $query->where(function ($q) {
            $q->where('status_code', '>=', 400)
                ->orWhereNotNull('error_message');
        });
    }

    public function scopeSuccessful(Builder $query): Builder
    {
        return $query->where('status_code', '<', 400)
            ->whereNull('error_message');
    }

    public function scopeRecent(Builder $query, int $hours = 24): Builder
    {
        return $query->where('created_at', '>=', now()->subHours($hours));
    }

    public function isError(): bool
    {
        return $this->status_code >= 400 || ! empty($this->error_message);
    }

    /**
     * Create a log entry for an API call.
     */
    public static function logApiCall(
        int $accountId,
        string $endpoint,
        string $method,
        ?array $request,
        ?array $response,
        ?int $statusCode,
        ?string $error,
        ?int $durationMs
    ): self {
        return self::create([
            'platform_account_id' => $accountId,
            'endpoint' => $endpoint,
            'method' => $method,
            'request_payload' => $request,
            'response_payload' => $response,
            'status_code' => $statusCode,
            'error_message' => $error,
            'duration_ms' => $durationMs,
        ]);
    }
}
