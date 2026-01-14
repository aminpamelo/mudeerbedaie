<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailStarterTemplate extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'category',
        'thumbnail',
        'description',
        'design_json',
        'html_content',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'design_json' => 'array',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByCategory($query, string $category)
    {
        if ($category === 'all') {
            return $query;
        }

        return $query->where('category', $category);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    public static function getCategories(): array
    {
        return [
            'all' => 'Semua',
            'blank' => 'Kosong',
            'reminder' => 'Peringatan',
            'welcome' => 'Selamat Datang',
            'marketing' => 'Pemasaran',
            'followup' => 'Susulan',
        ];
    }
}
