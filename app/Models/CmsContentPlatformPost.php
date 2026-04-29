<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CmsContentPlatformPost extends Model
{
    /** @use HasFactory<\Database\Factories\CmsContentPlatformPostFactory> */
    use HasFactory;

    protected $table = 'cms_content_platform_posts';

    protected $fillable = [
        'content_id',
        'platform_id',
        'status',
        'post_url',
        'posted_at',
        'assignee_id',
        'caption_variant',
        'external_post_id',
        'sync_status',
        'stats',
    ];

    protected function casts(): array
    {
        return [
            'posted_at' => 'datetime',
            'stats' => 'array',
        ];
    }

    public function content(): BelongsTo
    {
        return $this->belongsTo(Content::class);
    }

    public function platform(): BelongsTo
    {
        return $this->belongsTo(CmsPlatform::class, 'platform_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'assignee_id');
    }
}
