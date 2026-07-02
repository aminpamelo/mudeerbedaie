<?php

namespace App\Models;

use Database\Factories\LiveHostMenteeDailyVideoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LiveHostMenteeDailyVideo extends Model
{
    /** @use HasFactory<LiveHostMenteeDailyVideoFactory> */
    use HasFactory;

    protected $table = 'live_host_mentee_daily_videos';

    protected $fillable = [
        'mentee_id', 'video_date', 'title', 'link', 'logged_by',
    ];

    protected function casts(): array
    {
        return [
            'video_date' => 'date',
        ];
    }

    public function mentee(): BelongsTo
    {
        return $this->belongsTo(LiveHostMentee::class, 'mentee_id');
    }

    public function loggedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'logged_by');
    }
}
