<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use InvalidArgumentException;

class UpsellCommissionPayout extends Model
{
    /** @use HasFactory<\Database\Factories\UpsellCommissionPayoutFactory> */
    use HasFactory;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_LOCKED = 'locked';

    public const STATUS_PAID = 'paid';

    protected $fillable = [
        'teacher_user_id',
        'period_start',
        'period_end',
        'total_commission',
        'session_count',
        'status',
        'locked_at',
        'paid_at',
        'payment_reference',
        'paid_by_user_id',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'locked_at' => 'datetime',
            'paid_at' => 'datetime',
            'total_commission' => 'decimal:2',
            'session_count' => 'integer',
        ];
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_user_id');
    }

    public function paidBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by_user_id');
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(UpsellCommissionPayoutSession::class, 'upsell_commission_payout_id');
    }

    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_DRAFT);
    }

    public function scopeLocked(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_LOCKED);
    }

    public function scopePaid(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PAID);
    }

    public function lock(): void
    {
        if ($this->status !== self::STATUS_DRAFT) {
            throw new InvalidArgumentException(
                "Cannot lock payout in status [{$this->status}]; expected [draft]."
            );
        }

        $this->forceFill([
            'status' => self::STATUS_LOCKED,
            'locked_at' => now(),
        ])->save();
    }

    public function markPaid(int $userId, string $reference): void
    {
        if ($this->status !== self::STATUS_LOCKED) {
            throw new InvalidArgumentException(
                "Cannot mark paid in status [{$this->status}]; expected [locked]."
            );
        }

        $this->forceFill([
            'status' => self::STATUS_PAID,
            'paid_at' => now(),
            'paid_by_user_id' => $userId,
            'payment_reference' => $reference,
        ])->save();
    }
}
