<?php

namespace App\Models;

use Database\Factories\LiveHostMenteeDailyCommentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LiveHostMenteeDailyComment extends Model
{
    /** @use HasFactory<LiveHostMenteeDailyCommentFactory> */
    use HasFactory;

    protected $table = 'live_host_mentee_daily_comments';

    protected $fillable = [
        'mentee_id', 'metric_date', 'user_id', 'comment',
    ];

    protected function casts(): array
    {
        return [
            'metric_date' => 'date',
        ];
    }

    public function mentee(): BelongsTo
    {
        return $this->belongsTo(LiveHostMentee::class, 'mentee_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
