<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MindpalConversation extends Model
{
    protected $fillable = [
        'user_id',
        'title',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    /**
     * @return HasMany<MindpalMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(MindpalMessage::class, 'conversation_id');
    }

    /**
     * @param  Builder<MindpalConversation>  $query
     * @return Builder<MindpalConversation>
     */
    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }
}
