<?php

namespace App\Services;

use App\Models\Order;
use App\Models\ProductOrder;
use Illuminate\Support\Facades\Log;
use Webimpian\BayarcashSdk\Bayarcash;

class BayarcashService
{
    private ?Bayarcash $bayarcash = null;

    private SettingsService $settingsService;

    public function __construct(SettingsService $settingsService)
    {
        $this->settingsService = $settingsService;
    }

    /**
     * Initialize the Bayarcash SDK with stored settings.
     */
    private function initializeBayarcash(): void
    {
        if ($this->bayarcash !== null) {
            return;
        }

        $apiToken = $this->settingsService->get('bayarcash_api_token');

        if (! $apiToken) {
            throw new \Exception('Bayarcash API token not configured. Please configure Bayarcash settings first.');
        }

        $this->bayarcash = new Bayarcash($apiToken);

        // Set sandbox mode based on settings
        if ($this->settingsService->get('bayarcash_sandbox', true)) {
            $this->bayarcash->useSandbox();
        }

        // Set API version
        $this->bayarcash->setApiVersion('v3');
    }

    /**
     * Get the Bayarcash SDK instance.
     */
    public function getBayarcash(): Bayarcash
    {
        $this->initializeBayarcash();

        return $this->bayarcash;
    }

    /**
     * Check if Bayarcash is properly configured.
     */
    public function isConfigured(): bool
    {
        $apiToken = $this->settingsService->get('bayarcash_api_token');
        $secretKey = $this->settingsService->get('bayarcash_api_secret_key');
        $portalKey = $this->settingsService->get('bayarcash_portal_key');

        return ! empty($apiToken) && ! empty($secretKey) && ! empty($portalKey);
    }

    /**
     * Check if Bayarcash payments are enabled.
     */
    public function isEnabled(): bool
    {
        return $this->isConfigured()
            && (bool) $this->settingsService->get('enable_bayarcash_payments', false);
    }

    /**
     * Check if using sandbox mode.
     */
    public function isSandbox(): bool
    {
        return (bool) $this->settingsService->get('bayarcash_sandbox', true);
    }

    /**
     * Get available portals from Bayarcash.
     *
     * @return array<mixed>
     */
    public function getPortals(): array
    {
        try {
            // Force re-initialization to get fresh settings
            $this->bayarcash = null;
            $this->initializeBayarcash();

            return $this->bayarcash->getPortals();
        } catch (\TypeError $e) {
            // The Bayarcash SDK has a bug where it expects string but API returns null
            Log::warning('Bayarcash SDK TypeError - API returned null for expected string field', [
                'error' => $e->getMessage(),
            ]);

            return [];
        } catch (\Exception $e) {
            Log::error('Failed to get Bayarcash portals', [
                'error' => $e->getMessage(),
                'sandbox_mode' => $this->isSandbox(),
            ]);

            return [];
        }
    }

    /**
     * Get available payment channels for the configured portal.
     *
     * @return array<mixed>
     */
    public function getAvailableChannels(): array
    {
        try {
            $this->initializeBayarcash();
            $portalKey = $this->settingsService->get('bayarcash_portal_key');

            if (! $portalKey) {
                return [];
            }

            return $this->bayarcash->getChannels($portalKey);
        } catch (\Exception $e) {
            Log::error('Failed to get Bayarcash channels', [
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Get list of available FPX banks.
     *
     * @return array<mixed>
     */
    public function getFpxBanks(): array
    {
        try {
            $this->initializeBayarcash();

            return $this->bayarcash->fpxBanksList();
        } catch (\Exception $e) {
            Log::error('Failed to get FPX banks list', [
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Create a payment intent and return the checkout URL.
     *
     * @param  array{
     *     order_number: string,
     *     amount: float,
     *     payer_name: string,
     *     payer_email: string,
     *     payer_phone?: string,
     *     payment_channel?: int,
     *     bank_code?: string
     * }  $data
     */
    public function createPaymentIntent(array $data): object
    {
        $this->initializeBayarcash();

        $secretKey = $this->settingsService->get('bayarcash_api_secret_key');
        $portalKey = $this->settingsService->get('bayarcash_portal_key');

        // Bayarcash expects amount in actual currency (RM), not cents
        $amount = round($data['amount'], 2);

        $paymentData = [
            'portal_key' => $portalKey,
            'order_number' => $data['order_number'],
            'amount' => $amount,
            'payer_name' => $data['payer_name'],
            'payer_email' => $data['payer_email'],
            'payer_telephone_number' => $data['payer_phone'] ?? '',
            'payment_channel' => $data['payment_channel'] ?? Bayarcash::FPX,
            'callback_url' => $this->getCallbackUrl(),
            'return_url' => $this->getReturnUrl($data['order_number']),
        ];

        // Add bank code for FPX payments if provided
        if (! empty($data['bank_code'])) {
            $paymentData['buyer_bank_code'] = $data['bank_code'];
        }

        // Generate checksum for security
        $checksum = $this->bayarcash->createPaymentIntenChecksumValue($secretKey, $paymentData);
        $paymentData['checksum'] = $checksum;

        Log::info('Creating Bayarcash payment intent', [
            'order_number' => $data['order_number'],
            'amount' => $amount,
            'payment_channel' => $paymentData['payment_channel'],
        ]);

        return $this->bayarcash->createPaymentIntent($paymentData);
    }

    /**
     * Verify transaction callback data.
     *
     * @param  array<string, mixed>  $data
     */
    public function verifyTransactionCallback(array $data): bool
    {
        try {
            $secretKey = $this->settingsService->get('bayarcash_api_secret_key');
            $this->initializeBayarcash();

            return $this->bayarcash->verifyTransactionCallbackData($data, $secretKey);
        } catch (\Exception $e) {
            Log::error('Failed to verify Bayarcash transaction callback', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);

            return false;
        }
    }

    /**
     * Verify return URL callback data.
     *
     * @param  array<string, mixed>  $data
     */
    public function verifyReturnCallback(array $data): bool
    {
        try {
            $secretKey = $this->settingsService->get('bayarcash_api_secret_key');
            $this->initializeBayarcash();

            // Normalize field names - Bayarcash sometimes uses different field names
            $normalizedData = $data;

            // Map exchange_transaction_id to transaction_id if missing
            if (! isset($normalizedData['transaction_id']) && isset($normalizedData['exchange_transaction_id'])) {
                $normalizedData['transaction_id'] = $normalizedData['exchange_transaction_id'];
            }

            // Try SDK verification first
            try {
                return $this->bayarcash->verifyReturnUrlCallbackData($normalizedData, $secretKey);
            } catch (\Exception $sdkException) {
                // If SDK fails, try manual checksum verification
                Log::info('SDK verification failed, trying manual checksum verification', [
                    'sdk_error' => $sdkException->getMessage(),
                ]);

                return $this->verifyChecksumManually($data, $secretKey);
            }
        } catch (\Exception $e) {
            Log::error('Failed to verify Bayarcash return callback', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);

            return false;
        }
    }

    /**
     * Manually verify the checksum for return callbacks.
     * This is a fallback when the SDK verification fails due to field name differences.
     *
     * @param  array<string, mixed>  $data
     */
    private function verifyChecksumManually(array $data, string $secretKey): bool
    {
        if (! isset($data['checksum'])) {
            return false;
        }

        // Build the checksum string based on Bayarcash's expected format
        // Order: order_number, currency, amount, status, checksum
        $checksumFields = [
            $data['order_number'] ?? '',
            $data['currency'] ?? 'MYR',
            $data['amount'] ?? '',
            $data['status'] ?? '',
        ];

        $checksumString = implode('|', $checksumFields).'|'.$secretKey;
        $calculatedChecksum = hash('sha256', $checksumString);

        $isValid = hash_equals($data['checksum'], $calculatedChecksum);

        if (! $isValid) {
            Log::warning('Manual checksum verification failed', [
                'provided_checksum' => $data['checksum'],
                'calculated_checksum' => $calculatedChecksum,
                'checksum_fields' => $checksumFields,
            ]);
        }

        return $isValid;
    }

    /**
     * Get a transaction by ID.
     */
    public function getTransaction(string $transactionId): ?object
    {
        try {
            $this->initializeBayarcash();

            return $this->bayarcash->getTransaction($transactionId);
        } catch (\Exception $e) {
            Log::error('Failed to get Bayarcash transaction', [
                'transaction_id' => $transactionId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Get a transaction by order number.
     */
    public function getTransactionByOrderNumber(string $orderNumber): ?object
    {
        try {
            $this->initializeBayarcash();

            return $this->bayarcash->getTransactionByOrderNumber($orderNumber);
        } catch (\Exception $e) {
            Log::error('Failed to get Bayarcash transaction by order number', [
                'order_number' => $orderNumber,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Process successful payment.
     *
     * @param  array<string, mixed>  $callbackData
     */
    public function processSuccessfulPayment(ProductOrder|Order $order, array $callbackData): void
    {
        $order->update([
            'status' => $order instanceof ProductOrder ? 'processing' : 'paid',
            'payment_method' => 'fpx',
            'bayarcash_transaction_id' => $callbackData['transaction_id'] ?? null,
            'bayarcash_payment_channel' => $callbackData['payment_channel'] ?? 'FPX',
            'bayarcash_response' => $callbackData,
            'paid_at' => now(),
            'paid_time' => now(),
        ]);

        Log::info('Bayarcash payment successful', [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'transaction_id' => $callbackData['transaction_id'] ?? null,
        ]);
    }

    /**
     * Process failed payment.
     *
     * @param  array<string, mixed>  $callbackData
     */
    public function processFailedPayment(ProductOrder|Order $order, array $callbackData): void
    {
        $order->update([
            'status' => 'payment_failed',
            'bayarcash_response' => $callbackData,
        ]);

        Log::warning('Bayarcash payment failed', [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'callback_data' => $callbackData,
        ]);
    }

    /**
     * Process pending payment.
     *
     * @param  array<string, mixed>  $callbackData
     */
    public function processPendingPayment(ProductOrder|Order $order, array $callbackData): void
    {
        $order->update([
            'status' => 'pending',
            'bayarcash_transaction_id' => $callbackData['transaction_id'] ?? null,
            'bayarcash_response' => $callbackData,
        ]);

        Log::info('Bayarcash payment pending', [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'callback_data' => $callbackData,
        ]);
    }

    /**
     * Get the callback URL for Bayarcash notifications.
     */
    private function getCallbackUrl(): string
    {
        return url('/bayarcash/callback');
    }

    /**
     * Get the return URL for after payment completion.
     */
    private function getReturnUrl(string $orderNumber): string
    {
        return url("/bayarcash/return?order={$orderNumber}");
    }

    /**
     * Get payment channel constant from string.
     */
    public function getPaymentChannelConstant(string $channel): int
    {
        return match ($channel) {
            'fpx' => Bayarcash::FPX,
            'duitnow_qr' => Bayarcash::DUITNOW_QR,
            'duitnow_obw' => Bayarcash::DUITNOW_OBW,
            'boost' => Bayarcash::BOOST,
            'grabpay' => Bayarcash::GRABPAY,
            'shopee_pay' => Bayarcash::SHOPEE_PAY,
            'tng' => Bayarcash::TNG,
            'card' => Bayarcash::CARD,
            default => Bayarcash::FPX,
        };
    }

    /**
     * Get human-readable payment channel name.
     */
    public function getPaymentChannelName(string|int $channel): string
    {
        if (is_int($channel)) {
            return match ($channel) {
                Bayarcash::FPX => 'FPX Online Banking',
                Bayarcash::DUITNOW_QR => 'DuitNow QR',
                Bayarcash::DUITNOW_OBW => 'DuitNow Online Banking',
                Bayarcash::BOOST => 'Boost',
                Bayarcash::GRABPAY => 'GrabPay',
                Bayarcash::SHOPEE_PAY => 'ShopeePay',
                Bayarcash::TNG => "Touch 'n Go",
                Bayarcash::CARD => 'Credit/Debit Card',
                default => 'Unknown',
            };
        }

        return match ($channel) {
            'fpx', 'FPX' => 'FPX Online Banking',
            'duitnow_qr' => 'DuitNow QR',
            'duitnow_obw' => 'DuitNow Online Banking',
            'boost', 'BOOST' => 'Boost',
            'grabpay', 'GRABPAY' => 'GrabPay',
            'shopee_pay', 'SHOPEE_PAY' => 'ShopeePay',
            'tng', 'TNG' => "Touch 'n Go",
            'card', 'CARD' => 'Credit/Debit Card',
            default => $channel,
        };
    }
}
