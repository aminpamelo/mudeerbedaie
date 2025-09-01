<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentMethod extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'stripe_customer_id',
        'type',
        'stripe_payment_method_id',
        'card_details',
        'bank_details',
        'is_default',
        'is_active',
    ];

    protected $casts = [
        'card_details' => 'array',
        'bank_details' => 'array',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];

    // Payment method types
    public const TYPE_STRIPE_CARD = 'stripe_card';

    public const TYPE_BANK_TRANSFER = 'bank_transfer';

    public static function getTypes(): array
    {
        return [
            self::TYPE_STRIPE_CARD => 'Stripe Card',
            self::TYPE_BANK_TRANSFER => 'Bank Transfer',
        ];
    }

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function stripeCustomer(): BelongsTo
    {
        return $this->belongsTo(StripeCustomer::class, 'stripe_customer_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    // Type check methods
    public function isStripeCard(): bool
    {
        return $this->type === self::TYPE_STRIPE_CARD;
    }

    public function isBankTransfer(): bool
    {
        return $this->type === self::TYPE_BANK_TRANSFER;
    }

    // Accessors
    protected function typeLabel(): Attribute
    {
        return Attribute::make(
            get: fn () => self::getTypes()[$this->type] ?? $this->type
        );
    }

    protected function displayName(): Attribute
    {
        return Attribute::make(
            get: function () {
                if ($this->isStripeCard() && $this->card_details) {
                    $brand = $this->card_details['brand'] ?? 'Card';
                    $last4 = $this->card_details['last4'] ?? '****';

                    return ucfirst($brand).' ending in '.$last4;
                }

                if ($this->isBankTransfer() && $this->bank_details) {
                    $bankName = $this->bank_details['bank_name'] ?? 'Bank';
                    $accountNumber = $this->bank_details['account_number'] ?? '';
                    $maskedAccount = $accountNumber ? ' ('.substr($accountNumber, -4).')' : '';

                    return $bankName.' Transfer'.$maskedAccount;
                }

                return $this->type_label;
            }
        );
    }

    protected function isExpired(): Attribute
    {
        return Attribute::make(
            get: function () {
                if ($this->isStripeCard() && $this->card_details) {
                    $expMonth = $this->card_details['exp_month'] ?? null;
                    $expYear = $this->card_details['exp_year'] ?? null;

                    if ($expMonth && $expYear) {
                        $expiryDate = \Carbon\Carbon::createFromDate($expYear, $expMonth, 1)->endOfMonth();

                        return $expiryDate->isPast();
                    }
                }

                return false;
            }
        );
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    public function scopeStripeCards($query)
    {
        return $query->where('type', self::TYPE_STRIPE_CARD);
    }

    public function scopeBankTransfers($query)
    {
        return $query->where('type', self::TYPE_BANK_TRANSFER);
    }

    // Methods
    public function setAsDefault(): void
    {
        // Remove default flag from other payment methods for this user
        self::where('user_id', $this->user_id)
            ->where('id', '!=', $this->id)
            ->update(['is_default' => false]);

        // Set this as default
        $this->update(['is_default' => true]);
    }

    public function deactivate(): void
    {
        $this->update(['is_active' => false]);

        // If this was the default, set another active method as default
        if ($this->is_default) {
            $newDefault = self::where('user_id', $this->user_id)
                ->where('id', '!=', $this->id)
                ->active()
                ->first();

            if ($newDefault) {
                $newDefault->setAsDefault();
            }
        }
    }
}
