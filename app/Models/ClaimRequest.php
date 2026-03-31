<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ClaimRequest extends Model
{
    /** @use HasFactory<\Database\Factories\ClaimRequestFactory> */
    use HasFactory;

    protected $fillable = [
        'claim_number',
        'employee_id',
        'claim_type_id',
        'vehicle_rate_id',
        'amount',
        'approved_amount',
        'claim_date',
        'description',
        'receipt_path',
        'status',
        'submitted_at',
        'approved_by',
        'approved_at',
        'rejected_reason',
        'paid_at',
        'paid_reference',
        'distance_km',
        'origin',
        'destination',
        'trip_purpose',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'approved_amount' => 'decimal:2',
            'claim_date' => 'date',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'paid_at' => 'datetime',
            'distance_km' => 'decimal:2',
        ];
    }

    protected $appends = ['receipt_url'];

    /**
     * Get the full URL for the receipt file.
     */
    protected function receiptUrl(): Attribute
    {
        return Attribute::get(function () {
            if (! $this->receipt_path) {
                return null;
            }

            return Storage::disk('public')->url($this->receipt_path);
        });
    }

    /**
     * Get the employee who submitted this claim.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Get the vehicle rate for this claim request.
     */
    public function vehicleRate(): BelongsTo
    {
        return $this->belongsTo(ClaimTypeVehicleRate::class);
    }

    /**
     * Get the claim type for this request.
     */
    public function claimType(): BelongsTo
    {
        return $this->belongsTo(ClaimType::class);
    }

    /**
     * Get the employee who approved this claim.
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'approved_by');
    }

    /**
     * Scope to filter pending claim requests.
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope to filter approved claim requests.
     */
    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', 'approved');
    }

    /**
     * Scope to filter paid claim requests.
     */
    public function scopePaid(Builder $query): Builder
    {
        return $query->where('status', 'paid');
    }

    /**
     * Generate the next claim number in CLM-YYYYMM-0001 format.
     */
    public static function generateClaimNumber(): string
    {
        $yearMonth = now()->format('Ym');
        $prefix = "CLM-{$yearMonth}-";

        $lastClaim = static::query()
            ->where('claim_number', 'like', $prefix.'%')
            ->orderByDesc('claim_number')
            ->first();

        if ($lastClaim) {
            $lastNumber = (int) substr($lastClaim->claim_number, -4);
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 1;
        }

        return $prefix.str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
    }
}
