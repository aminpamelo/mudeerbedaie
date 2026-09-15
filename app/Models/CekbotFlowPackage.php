<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One package/offer a {@see CekbotFlow} presents in its numbered menu, optionally
 * linked to a {@see CekbotProduct} (which may in turn link to a catalogue Product).
 */
class CekbotFlowPackage extends Model
{
    /** @use HasFactory<\Database\Factories\CekbotFlowPackageFactory> */
    use HasFactory;

    protected $fillable = [
        'cekbot_flow_id',
        'cekbot_product_id',
        'product_id',
        'label',
        'price',
        'currency',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<CekbotFlow, $this>
     */
    public function flow(): BelongsTo
    {
        return $this->belongsTo(CekbotFlow::class, 'cekbot_flow_id');
    }

    /**
     * The Cekbot product knowledge backing this package (optional).
     *
     * @return BelongsTo<CekbotProduct, $this>
     */
    public function cekbotProduct(): BelongsTo
    {
        return $this->belongsTo(CekbotProduct::class, 'cekbot_product_id');
    }

    /**
     * The catalogue (shop) product this package sells (optional).
     *
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    /**
     * Effective selling price — the package override, else the linked catalogue
     * product's price, else the Cekbot product's price, else 0.
     */
    public function effectivePrice(): float
    {
        if ($this->price !== null) {
            return (float) $this->price;
        }

        if ($this->product_id && $this->product) {
            return (float) ($this->product->price ?? 0);
        }

        return (float) ($this->cekbotProduct?->price ?? 0);
    }

    /**
     * Currency to bill in — the package's, else the Cekbot product's, else RM.
     */
    public function effectiveCurrency(): string
    {
        return $this->currency
            ?: ($this->cekbotProduct?->currency ?? 'RM');
    }

    /**
     * The catalogue product id to record on the order line — a direct catalogue
     * link takes precedence, else the one behind the Cekbot product.
     */
    public function orderProductId(): ?int
    {
        return $this->product_id ?? $this->cekbotProduct?->product_id;
    }
}
