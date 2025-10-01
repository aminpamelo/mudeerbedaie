<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductOrderAddress extends Model
{
    protected $fillable = [
        'order_id',
        'type',
        'first_name',
        'last_name',
        'company',
        'address_line_1',
        'address_line_2',
        'city',
        'state',
        'postal_code',
        'country',
        'phone',
        'email',
        'is_default',
        'delivery_instructions',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    // Relationships
    public function order(): BelongsTo
    {
        return $this->belongsTo(ProductOrder::class, 'order_id');
    }

    // Helper methods
    public function getFullName(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }

    public function getFullAddress(): string
    {
        $addressParts = array_filter([
            $this->address_line_1,
            $this->address_line_2,
            $this->city,
            $this->state,
            $this->postal_code,
            $this->country,
        ]);

        return implode(', ', $addressParts);
    }

    public function getFormattedAddress(): array
    {
        return [
            'name' => $this->getFullName(),
            'company' => $this->company,
            'line1' => $this->address_line_1,
            'line2' => $this->address_line_2,
            'city' => $this->city,
            'state' => $this->state,
            'postal_code' => $this->postal_code,
            'country' => $this->country,
            'phone' => $this->phone,
            'email' => $this->email,
        ];
    }

    public function isBillingAddress(): bool
    {
        return $this->type === 'billing';
    }

    public function isShippingAddress(): bool
    {
        return $this->type === 'shipping';
    }

    public function isComplete(): bool
    {
        return ! empty($this->first_name) &&
               ! empty($this->last_name) &&
               ! empty($this->address_line_1) &&
               ! empty($this->city) &&
               ! empty($this->state) &&
               ! empty($this->postal_code) &&
               ! empty($this->country);
    }
}
