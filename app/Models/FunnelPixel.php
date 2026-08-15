<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A reusable tracking pixel stored in the Pixel Library.
 *
 * One row is one pixel for one platform ('facebook' or 'google'); the
 * platform-specific credentials live in the `settings` JSON. Funnels that
 * apply a library pixel keep a copy of the credentials in their own
 * settings.pixel_settings, plus a `library_pixel_id` back-reference so the
 * UI can show which library entry it came from.
 */
class FunnelPixel extends Model
{
    public const PLATFORMS = ['facebook', 'google'];

    protected $fillable = [
        'user_id',
        'name',
        'platform',
        'group_name',
        'settings',
        'last_test_status',
        'last_test_message',
        'last_tested_at',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'last_tested_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    /**
     * Fighters only see their own pixels; admin/employee see the whole library
     * (mirrors funnel visibility in FunnelController::index).
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isFighter()) {
            return $query->where('user_id', $user->id);
        }

        return $query;
    }

    /**
     * Count funnels whose pixel settings reference this library entry.
     */
    public function linkedFunnelsCount(): int
    {
        return Funnel::query()
            ->where("settings->pixel_settings->{$this->platform}->library_pixel_id", $this->id)
            ->count();
    }
}
