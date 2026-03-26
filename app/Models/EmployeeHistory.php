<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeHistory extends Model
{
    /** @use HasFactory<\Database\Factories\EmployeeHistoryFactory> */
    use HasFactory;

    const UPDATED_AT = null;

    protected $fillable = [
        'employee_id',
        'change_type',
        'field_name',
        'old_value',
        'new_value',
        'effective_date',
        'remarks',
        'changed_by',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'effective_date' => 'date',
        ];
    }

    /**
     * Get the employee this history belongs to
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Get the user who made this change
     */
    public function changedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
