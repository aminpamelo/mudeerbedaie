<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ItTicketCategory extends Model
{
    /** @use HasFactory<\Database\Factories\ItTicketCategoryFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'color',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(ItTicket::class, 'category_id');
    }
}
