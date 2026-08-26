<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsAppGroupCollectionItem extends Model
{
    use HasFactory;

    protected $table = 'whatsapp_group_collection_items';

    protected $fillable = [
        'collection_id',
        'class_id',
        'label',
        'description',
        'invite_link',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function collection(): BelongsTo
    {
        return $this->belongsTo(WhatsAppGroupCollection::class, 'collection_id');
    }

    public function class(): BelongsTo
    {
        return $this->belongsTo(ClassModel::class, 'class_id');
    }

    public function getDisplayLabelAttribute(): string
    {
        return $this->label
            ?: $this->class?->title
            ?: 'WhatsApp Group';
    }

    public function getEffectiveLinkAttribute(): ?string
    {
        return $this->invite_link ?: $this->class?->whatsapp_group_link;
    }
}
