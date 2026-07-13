<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LiveHostMenteeVideoComment extends Model
{
    protected $table = 'live_host_mentee_video_comments';

    protected $fillable = [
        'video_id', 'user_id', 'author_role', 'body',
    ];

    public function video(): BelongsTo
    {
        return $this->belongsTo(LiveHostMenteeDailyVideo::class, 'video_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** True when the comment was written by the host (mentee) rather than staff. */
    public function isFromHost(): bool
    {
        return $this->author_role === 'host';
    }
}
