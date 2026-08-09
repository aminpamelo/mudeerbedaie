<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MindpalDocument extends Model
{
    public const STATUS_PROCESSING = 'processing';

    public const STATUS_READY = 'ready';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'title',
        'description',
        'file_path',
        'file_name',
        'file_size',
        'total_pages',
        'total_chunks',
        'status',
        'error_message',
        'uploaded_by',
    ];

    /**
     * @return HasMany<MindpalChunk, $this>
     */
    public function chunks(): HasMany
    {
        return $this->hasMany(MindpalChunk::class, 'document_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by')->withTrashed();
    }

    /**
     * @param  Builder<MindpalDocument>  $query
     * @return Builder<MindpalDocument>
     */
    public function scopeReady(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_READY);
    }
}
