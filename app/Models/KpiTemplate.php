<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KpiTemplate extends Model
{
    /** @use HasFactory<\Database\Factories\KpiTemplateFactory> */
    use HasFactory;

    protected $fillable = [
        'position_id', 'department_id', 'title', 'description',
        'target', 'weight', 'category', 'is_active',
    ];

    protected function casts(): array
    {
        return ['weight' => 'decimal:2', 'is_active' => 'boolean'];
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }
}
