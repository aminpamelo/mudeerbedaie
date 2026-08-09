<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MindpalChunk extends Model
{
    protected $fillable = [
        'document_id',
        'content',
        'page_number',
        'chunk_index',
        'embedding',
        'token_count',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'embedding' => 'array',
        ];
    }

    /**
     * @return BelongsTo<MindpalDocument, $this>
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(MindpalDocument::class, 'document_id');
    }
}
