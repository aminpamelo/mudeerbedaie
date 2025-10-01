<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportJob extends Model
{
    use HasFactory;

    protected $fillable = [
        'platform_id',
        'platform_account_id',
        'user_id',
        'file_name',
        'file_path',
        'file_hash',
        'file_size',
        'import_type',
        'total_rows',
        'processed_rows',
        'successful_rows',
        'failed_rows',
        'skipped_rows',
        'status',
        'status_message',
        'progress_percentage',
        'field_mapping',
        'import_settings',
        'validation_rules',
        'errors',
        'warnings',
        'summary',
        'log_file_path',
        'started_at',
        'completed_at',
        'duration_seconds',
        'batch_size',
        'current_batch',
        'total_batches',
    ];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
            'total_rows' => 'integer',
            'processed_rows' => 'integer',
            'successful_rows' => 'integer',
            'failed_rows' => 'integer',
            'skipped_rows' => 'integer',
            'progress_percentage' => 'integer',
            'field_mapping' => 'array',
            'import_settings' => 'array',
            'validation_rules' => 'array',
            'errors' => 'array',
            'warnings' => 'array',
            'summary' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'duration_seconds' => 'integer',
            'batch_size' => 'integer',
            'current_batch' => 'integer',
            'total_batches' => 'integer',
        ];
    }

    // Relationships
    public function platform(): BelongsTo
    {
        return $this->belongsTo(Platform::class);
    }

    public function platformAccount(): BelongsTo
    {
        return $this->belongsTo(PlatformAccount::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Helper methods
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isProcessing(): bool
    {
        return $this->status === 'processing';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function hasErrors(): bool
    {
        return ! empty($this->errors) || $this->failed_rows > 0;
    }

    public function hasWarnings(): bool
    {
        return ! empty($this->warnings);
    }

    public function getSuccessRateAttribute(): float
    {
        if ($this->total_rows <= 0) {
            return 0;
        }

        return round(($this->successful_rows / $this->total_rows) * 100, 2);
    }

    public function getFormattedDurationAttribute(): string
    {
        if (! $this->duration_seconds) {
            return 'N/A';
        }

        $minutes = floor($this->duration_seconds / 60);
        $seconds = $this->duration_seconds % 60;

        if ($minutes > 0) {
            return "{$minutes}m {$seconds}s";
        }

        return "{$seconds}s";
    }

    public function getFormattedFileSizeAttribute(): string
    {
        if (! $this->file_size) {
            return 'N/A';
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $size = $this->file_size;
        $unitIndex = 0;

        while ($size >= 1024 && $unitIndex < count($units) - 1) {
            $size /= 1024;
            $unitIndex++;
        }

        return round($size, 2).' '.$units[$unitIndex];
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'pending' => 'gray',
            'processing' => 'blue',
            'completed' => $this->hasErrors() ? 'amber' : 'green',
            'failed' => 'red',
            'cancelled' => 'gray',
            default => 'gray'
        };
    }

    public function markAsStarted(): void
    {
        $this->update([
            'status' => 'processing',
            'started_at' => now(),
            'progress_percentage' => 0,
        ]);
    }

    public function markAsCompleted(): void
    {
        $duration = $this->started_at ? now()->diffInSeconds($this->started_at) : null;

        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
            'duration_seconds' => $duration,
            'progress_percentage' => 100,
        ]);
    }

    public function markAsFailed(string $message): void
    {
        $duration = $this->started_at ? now()->diffInSeconds($this->started_at) : null;

        $this->update([
            'status' => 'failed',
            'status_message' => $message,
            'completed_at' => now(),
            'duration_seconds' => $duration,
        ]);
    }

    public function markAsCancelled(): void
    {
        $duration = $this->started_at ? now()->diffInSeconds($this->started_at) : null;

        $this->update([
            'status' => 'cancelled',
            'completed_at' => now(),
            'duration_seconds' => $duration,
        ]);
    }

    public function updateProgress(int $processedRows): void
    {
        $percentage = $this->total_rows > 0
            ? round(($processedRows / $this->total_rows) * 100)
            : 0;

        $this->update([
            'processed_rows' => $processedRows,
            'progress_percentage' => $percentage,
        ]);
    }

    public function addError(string $error, ?int $rowNumber = null): void
    {
        $errors = $this->errors ?: [];
        $errorEntry = [
            'message' => $error,
            'timestamp' => now()->toISOString(),
        ];

        if ($rowNumber) {
            $errorEntry['row'] = $rowNumber;
        }

        $errors[] = $errorEntry;

        $this->update(['errors' => $errors]);
        $this->increment('failed_rows');
    }

    public function addWarning(string $warning, ?int $rowNumber = null): void
    {
        $warnings = $this->warnings ?: [];
        $warningEntry = [
            'message' => $warning,
            'timestamp' => now()->toISOString(),
        ];

        if ($rowNumber) {
            $warningEntry['row'] = $rowNumber;
        }

        $warnings[] = $warningEntry;

        $this->update(['warnings' => $warnings]);
    }

    public function incrementSuccessful(): void
    {
        $this->increment('successful_rows');
    }

    public function incrementSkipped(): void
    {
        $this->increment('skipped_rows');
    }

    // Scopes
    public function scopeByStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    public function scopeByPlatform(Builder $query, int $platformId): Builder
    {
        return $query->where('platform_id', $platformId);
    }

    public function scopeByPlatformAccount(Builder $query, int $platformAccountId): Builder
    {
        return $query->where('platform_account_id', $platformAccountId);
    }

    public function scopeByImportType(Builder $query, string $importType): Builder
    {
        return $query->where('import_type', $importType);
    }

    public function scopeByUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function scopeRecent(Builder $query, int $days = 30): Builder
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', 'completed');
    }

    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', 'failed');
    }

    public function scopeProcessing(Builder $query): Builder
    {
        return $query->where('status', 'processing');
    }

    public function scopeRecentFirst(Builder $query): Builder
    {
        return $query->orderBy('created_at', 'desc');
    }

    // Static methods
    public static function createFromUpload(array $data): self
    {
        return static::create([
            'platform_id' => $data['platform_id'],
            'platform_account_id' => $data['platform_account_id'],
            'user_id' => $data['user_id'],
            'file_name' => $data['file_name'],
            'file_path' => $data['file_path'],
            'file_hash' => $data['file_hash'] ?? null,
            'file_size' => $data['file_size'] ?? null,
            'import_type' => $data['import_type'] ?? 'orders',
            'field_mapping' => $data['field_mapping'] ?? null,
            'import_settings' => $data['import_settings'] ?? null,
            'batch_size' => $data['batch_size'] ?? 100,
        ]);
    }
}
