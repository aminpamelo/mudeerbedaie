<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FunnelEmailTemplate extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'subject',
        'content',
        'design_json',
        'html_content',
        'editor_type',
        'category',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'design_json' => 'array',
        ];
    }

    public function isVisualEditor(): bool
    {
        return $this->editor_type === 'visual';
    }

    public function isTextEditor(): bool
    {
        return $this->editor_type === 'text';
    }

    public function getEffectiveContent(): string
    {
        if ($this->isVisualEditor() && $this->html_content) {
            return $this->html_content;
        }

        return $this->content ?? '';
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    public static function getCategories(): array
    {
        return [
            'purchase' => 'Purchase',
            'cart' => 'Cart',
            'welcome' => 'Welcome',
            'followup' => 'Follow-up',
            'upsell' => 'Upsell',
            'general' => 'General',
        ];
    }

    public static function getGroupedPlaceholders(): array
    {
        return [
            'Contact' => [
                '{{contact.name}}' => 'Full contact name',
                '{{contact.first_name}}' => 'Contact first name',
                '{{contact.email}}' => 'Contact email',
                '{{contact.phone}}' => 'Contact phone',
            ],
            'Order' => [
                '{{order.number}}' => 'Order number',
                '{{order.total}}' => 'Order total',
                '{{order.date}}' => 'Order date',
                '{{order.items_list}}' => 'Order items list',
            ],
            'Payment' => [
                '{{payment.method}}' => 'Payment method',
                '{{payment.status}}' => 'Payment status',
            ],
            'Product' => [
                '{{product.name}}' => 'Product name',
                '{{product.price}}' => 'Product price',
                '{{product.description}}' => 'Product description',
                '{{product.image_url}}' => 'Product image URL',
            ],
            'Funnel' => [
                '{{funnel.name}}' => 'Funnel name',
                '{{funnel.url}}' => 'Funnel URL',
            ],
            'General' => [
                '{{current_date}}' => 'Current date',
                '{{current_time}}' => 'Current time',
                '{{company_name}}' => 'Company name',
                '{{company_email}}' => 'Company email',
            ],
        ];
    }

    public static function getAvailablePlaceholders(): array
    {
        $placeholders = [];
        foreach (static::getGroupedPlaceholders() as $group => $items) {
            $placeholders = array_merge($placeholders, $items);
        }

        return $placeholders;
    }
}
