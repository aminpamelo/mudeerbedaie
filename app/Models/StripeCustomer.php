<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StripeCustomer extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'stripe_customer_id',
        'metadata',
        'last_synced_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'last_synced_at' => 'datetime',
    ];

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function paymentMethods(): HasMany
    {
        return $this->hasMany(PaymentMethod::class, 'stripe_customer_id');
    }

    // Helper methods
    public function updateLastSynced(): void
    {
        $this->update(['last_synced_at' => now()]);
    }

    public function needsSync(): bool
    {
        if (! $this->last_synced_at) {
            return true;
        }

        // Consider sync needed if last synced more than 1 hour ago
        return $this->last_synced_at->lt(now()->subHour());
    }

    public function syncFromStripeData(array $stripeCustomerData): void
    {
        $this->update([
            'metadata' => [
                'email' => $stripeCustomerData['email'] ?? null,
                'name' => $stripeCustomerData['name'] ?? null,
                'phone' => $stripeCustomerData['phone'] ?? null,
                'created' => $stripeCustomerData['created'] ?? null,
                'default_source' => $stripeCustomerData['default_source'] ?? null,
                'default_payment_method' => $stripeCustomerData['default_payment_method'] ?? null,
            ],
            'last_synced_at' => now(),
        ]);
    }

    // Scopes
    public function scopeNeedingSync($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('last_synced_at')
                ->orWhere('last_synced_at', '<', now()->subHour());
        });
    }

    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public static function findByStripeId(string $stripeCustomerId): ?self
    {
        return self::where('stripe_customer_id', $stripeCustomerId)->first();
    }

    public static function createForUser(User $user, string $stripeCustomerId, array $metadata = []): self
    {
        return self::create([
            'user_id' => $user->id,
            'stripe_customer_id' => $stripeCustomerId,
            'metadata' => $metadata,
            'last_synced_at' => now(),
        ]);
    }
}
