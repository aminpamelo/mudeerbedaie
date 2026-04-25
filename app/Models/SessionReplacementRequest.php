<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SessionReplacementRequest extends Model
{
    use HasFactory, SoftDeletes;

    public const SCOPE_ONE_DATE = 'one_date';

    public const SCOPE_PERMANENT = 'permanent';

    public const STATUS_PENDING = 'pending';

    public const STATUS_ASSIGNED = 'assigned';

    public const STATUS_WITHDRAWN = 'withdrawn';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_REJECTED = 'rejected';

    public const REASON_CATEGORIES = ['sick', 'family', 'personal', 'other'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'target_date' => 'date:Y-m-d',
            'requested_at' => 'datetime',
            'assigned_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(LiveScheduleAssignment::class, 'live_schedule_assignment_id');
    }

    public function originalHost(): BelongsTo
    {
        return $this->belongsTo(User::class, 'original_host_id');
    }

    public function replacementHost(): BelongsTo
    {
        return $this->belongsTo(User::class, 'replacement_host_id');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by_id');
    }

    public function liveSession(): BelongsTo
    {
        return $this->belongsTo(LiveSession::class);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }
}
