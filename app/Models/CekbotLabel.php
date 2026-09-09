<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CekbotLabel extends Model
{
    /** @use HasFactory<\Database\Factories\CekbotLabelFactory> */
    use HasFactory;

    protected $fillable = [
        'key',
        'name',
        'color',
        'sort_order',
    ];

    /**
     * @param  Builder<CekbotLabel>  $query
     * @return Builder<CekbotLabel>
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Labels shaped for the front-end (filters, inbox tagging, broadcast, manager).
     *
     * @return array<int, array{id: int, key: string, name: string, color: string}>
     */
    public static function options(): array
    {
        return static::query()->ordered()->get(['id', 'key', 'name', 'color'])
            ->map(fn (CekbotLabel $l): array => [
                'id' => $l->id,
                'key' => $l->key,
                'name' => $l->name,
                'color' => $l->color,
            ])->all();
    }

    /**
     * Keys a conversation is allowed to be tagged with (for validation).
     *
     * @return array<int, string>
     */
    public static function allowedKeys(): array
    {
        return static::query()->pluck('key')->all();
    }
}
