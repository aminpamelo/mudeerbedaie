<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LiveHostMenteeChecklistItem extends Model
{
    use HasFactory;

    protected $table = 'live_host_mentee_checklist_items';

    protected $fillable = [
        'mentee_id', 'title', 'description', 'is_required',
        'status', 'position', 'due_at', 'completed_at', 'completed_by',
    ];

    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
            'position' => 'integer',
            'due_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function mentee(): BelongsTo
    {
        return $this->belongsTo(LiveHostMentee::class, 'mentee_id');
    }

    public function completedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }
}
