<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContentReference extends Model
{
    protected $fillable = [
        'content_id',
        'referenced_content_id',
        'referenced_url',
        'position',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
        ];
    }

    public function content(): BelongsTo
    {
        return $this->belongsTo(Content::class, 'content_id');
    }

    public function referencedContent(): BelongsTo
    {
        return $this->belongsTo(Content::class, 'referenced_content_id');
    }
}
