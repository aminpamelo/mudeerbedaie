<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetAssignment extends Model
{
    /** @use HasFactory<\Database\Factories\AssetAssignmentFactory> */
    use HasFactory;

    protected $fillable = [
        'asset_id',
        'employee_id',
        'assigned_by',
        'assigned_date',
        'expected_return_date',
        'returned_date',
        'returned_condition',
        'return_notes',
        'status',
        'notes',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'assigned_date' => 'date',
            'expected_return_date' => 'date',
            'returned_date' => 'date',
        ];
    }

    /**
     * Get the asset for this assignment.
     */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    /**
     * Get the employee this asset is assigned to.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Get the employee who assigned the asset.
     */
    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'assigned_by');
    }

    /**
     * Scope to filter active assignments.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope to filter returned assignments.
     */
    public function scopeReturned(Builder $query): Builder
    {
        return $query->where('status', 'returned');
    }

    /**
     * Process the return of this asset assignment.
     */
    public function processReturn(string $returnedCondition, ?string $returnNotes = null): void
    {
        $this->update([
            'status' => 'returned',
            'returned_date' => now()->toDateString(),
            'returned_condition' => $returnedCondition,
            'return_notes' => $returnNotes,
        ]);

        $this->asset()->update(['status' => 'available']);
    }
}
