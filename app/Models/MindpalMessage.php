<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MindpalMessage extends Model
{
    protected $fillable = [
        'conversation_id',
        'role',
        'content',
        'sources',
        'tokens_used',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sources' => 'array',
        ];
    }

    /**
     * @return BelongsTo<MindpalConversation, $this>
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(MindpalConversation::class, 'conversation_id');
    }
}
