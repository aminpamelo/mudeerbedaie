<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class CekbotProduct extends Model
{
    /** @use HasFactory<\Database\Factories\CekbotProductFactory> */
    use HasFactory;

    protected $fillable = [
        'product_id',
        'name',
        'description',
        'price',
        'currency',
        'url',
        'images',
        'is_active',
        'sort_order',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'images' => 'array',
            'price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Optional link to a catalogue product.
     *
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @param  Builder<CekbotProduct>  $query
     * @return Builder<CekbotProduct>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Public URLs for this product's images — own uploads plus any images from
     * a linked catalogue product.
     *
     * @return array<int, string>
     */
    public function imageUrls(): array
    {
        $own = array_map(fn ($path) => Storage::disk('public')->url($path), $this->images ?? []);

        $linked = $this->relationLoaded('product') && $this->product
            ? $this->product->images->pluck('url')->all()
            : [];

        return array_values(array_filter(array_merge($own, $linked)));
    }

    /**
     * A one-line description of this product for the AI sales context.
     */
    public function toContextLine(): string
    {
        $parts = [$this->name];

        if ($this->price !== null) {
            $parts[] = $this->currency.number_format((float) $this->price, 2);
        }

        if (filled($this->description)) {
            $parts[] = \Illuminate\Support\Str::limit(trim($this->description), 300);
        }

        if (filled($this->url)) {
            $parts[] = 'Link: '.$this->url;
        }

        return implode(' — ', $parts);
    }
}
