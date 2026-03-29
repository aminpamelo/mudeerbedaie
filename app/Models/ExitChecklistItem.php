<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExitChecklistItem extends Model
{
    /** @use HasFactory<\Database\Factories\ExitChecklistItemFactory> */
    use HasFactory;

    protected $fillable = [
        'exit_checklist_id',
        'title',
        'category',
        'assigned_to',
        'status',
        'completed_at',
        'completed_by',
        'notes',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'completed_at' => 'datetime',
            'sort_order' => 'integer',
        ];
    }

    public function exitChecklist(): BelongsTo
    {
        return $this->belongsTo(ExitChecklist::class);
    }

    public function assignedEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'assigned_to');
    }

    public function completedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }
}
