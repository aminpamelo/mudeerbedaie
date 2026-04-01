<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OfficeExitPermission extends Model
{
    /** @use HasFactory<\Database\Factories\OfficeExitPermissionFactory> */
    use HasFactory;

    protected $fillable = [
        'permission_number',
        'employee_id',
        'exit_date',
        'exit_time',
        'return_time',
        'errand_type',
        'purpose',
        'addressed_to',
        'status',
        'approved_by',
        'approved_at',
        'rejection_reason',
        'cc_notified_at',
        'attendance_note_created',
    ];

    protected function casts(): array
    {
        return [
            'exit_date' => 'date',
            'approved_at' => 'datetime',
            'cc_notified_at' => 'datetime',
            'attendance_note_created' => 'boolean',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (OfficeExitPermission $permission): void {
            if (empty($permission->permission_number)) {
                $permission->permission_number = static::generatePermissionNumber();
            }
        });
    }

    public static function generatePermissionNumber(): string
    {
        $prefix = 'OEP-'.now()->format('Ym').'-';
        $last = static::where('permission_number', 'like', $prefix.'%')
            ->orderByDesc('permission_number')
            ->value('permission_number');

        $next = $last ? (int) substr($last, -4) + 1 : 1;

        return $prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }
}
