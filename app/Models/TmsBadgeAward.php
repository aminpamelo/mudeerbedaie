<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TmsBadgeAward extends Model
{
    protected $fillable = ['badge_id', 'user_id', 'awarded_at'];

    protected function casts(): array
    {
        return ['awarded_at' => 'datetime'];
    }

    public function badge(): BelongsTo
    {
        return $this->belongsTo(TmsBadge::class, 'badge_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
