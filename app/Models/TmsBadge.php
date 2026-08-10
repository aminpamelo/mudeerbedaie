<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class TmsBadge extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'description', 'icon', 'color', 'criteria_type', 'criteria_value', 'points'];

    public function awardees(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'tms_badge_awards', 'badge_id', 'user_id')
            ->withPivot('awarded_at')->withTimestamps();
    }
}
