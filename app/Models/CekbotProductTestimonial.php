<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class CekbotProductTestimonial extends Model
{
    /** @use HasFactory<\Database\Factories\CekbotProductTestimonialFactory> */
    use HasFactory;

    protected $fillable = [
        'cekbot_product_id',
        'author',
        'text',
        'image',
        'sort_order',
    ];

    /**
     * @return BelongsTo<CekbotProduct, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(CekbotProduct::class, 'cekbot_product_id');
    }

    /**
     * Public URL for the testimonial image, or null when there is none.
     */
    public function imageUrl(): ?string
    {
        return $this->image ? Storage::disk('public')->url($this->image) : null;
    }
}
