<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrainingCost extends Model
{
    /** @use HasFactory<\Database\Factories\TrainingCostFactory> */
    use HasFactory;

    protected $fillable = ['training_program_id', 'description', 'amount', 'receipt_path'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }

    public function trainingProgram(): BelongsTo
    {
        return $this->belongsTo(TrainingProgram::class);
    }
}
