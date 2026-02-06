<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Segment extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'type',
        'conditions',
        'contact_count',
        'last_calculated_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'conditions' => 'array',
            'contact_count' => 'integer',
            'last_calculated_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isStatic(): bool
    {
        return $this->type === 'static';
    }

    public function isDynamic(): bool
    {
        return $this->type === 'dynamic';
    }

    public function needsRecalculation(): bool
    {
        if ($this->isStatic()) {
            return false;
        }

        if ($this->last_calculated_at === null) {
            return true;
        }

        return $this->last_calculated_at->addHours(1)->isPast();
    }

    public function updateCount(int $count): void
    {
        $this->update([
            'contact_count' => $count,
            'last_calculated_at' => now(),
        ]);
    }
}
