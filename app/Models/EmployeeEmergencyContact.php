<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeEmergencyContact extends Model
{
    /** @use HasFactory<\Database\Factories\EmployeeEmergencyContactFactory> */
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'name',
        'relationship',
        'phone',
        'address',
    ];

    /**
     * Get the employee this contact belongs to
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
