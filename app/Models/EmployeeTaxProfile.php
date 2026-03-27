<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeTaxProfile extends Model
{
    /** @use HasFactory<\Database\Factories\EmployeeTaxProfileFactory> */
    use HasFactory;

    protected $fillable = [
        'employee_id', 'tax_number', 'marital_status', 'num_children',
        'num_children_studying', 'disabled_individual', 'disabled_spouse',
        'is_pcb_manual', 'manual_pcb_amount',
    ];

    protected function casts(): array
    {
        return [
            'disabled_individual' => 'boolean',
            'disabled_spouse' => 'boolean',
            'is_pcb_manual' => 'boolean',
            'manual_pcb_amount' => 'decimal:2',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
