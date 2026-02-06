<?php

declare(strict_types=1);

namespace App\Services\MergeTag\DataProviders;

use App\Models\FunnelOrder;
use App\Models\ProductOrder;
use App\Services\MergeTag\DataProviderInterface;
use Carbon\Carbon;

class PaymentDataProvider implements DataProviderInterface
{
    public function getValue(string $field, array $context): ?string
    {
        // Get order from context to extract payment info
        $order = $context['product_order'] ?? $context['order'] ?? $context['funnel_order'] ?? null;

        if (! $order instanceof ProductOrder && ! $order instanceof FunnelOrder) {
            // Try to get payment info directly from context
            return $this->getFromDirectContext($field, $context);
        }

        return match ($field) {
            'method' => $this->getPaymentMethod($order, $context),
            'reference' => $this->getPaymentReference($order, $context),
            'status' => $this->getPaymentStatus($order, $context),
            'paid_at' => $this->getPaymentDate($order, $context),
            'bank' => $this->getBankName($order, $context),
            'transaction_id' => $this->getTransactionId($order, $context),
            default => null,
        };
    }

    protected function getFromDirectContext(string $field, array $context): ?string
    {
        return match ($field) {
            'method' => $context['payment_method'] ?? null,
            'reference' => $context['payment_reference'] ?? $context['transaction_reference'] ?? null,
            'status' => $context['payment_status'] ?? null,
            'paid_at' => isset($context['paid_at']) ? $this->formatDateTime($context['paid_at']) : null,
            'bank' => $context['bank_name'] ?? $context['bank'] ?? null,
            'transaction_id' => $context['transaction_id'] ?? null,
            default => null,
        };
    }

    protected function getPaymentMethod(ProductOrder|FunnelOrder $order, array $context): ?string
    {
        // Check direct context first
        if (isset($context['payment_method'])) {
            return $context['payment_method'];
        }

        // From order
        $method = $order->payment_method ?? $order->payment_gateway ?? null;

        if ($method) {
            return $this->formatPaymentMethod($method);
        }

        // Try to detect from payment reference
        $reference = $order->payment_reference ?? $order->transaction_reference ?? null;
        if ($reference) {
            if (str_starts_with($reference, 'BC-') || str_starts_with($reference, 'bc_')) {
                return 'Bayarcash';
            }
            if (str_starts_with($reference, 'pi_') || str_starts_with($reference, 'ch_')) {
                return 'Stripe';
            }
        }

        return null;
    }

    protected function formatPaymentMethod(string $method): string
    {
        return match (strtolower($method)) {
            'fpx', 'fpx_b2c' => 'FPX',
            'fpx_b2b' => 'FPX Business',
            'card', 'credit_card', 'creditcard' => 'Credit/Debit Card',
            'ewallet', 'e-wallet' => 'E-Wallet',
            'bayarcash' => 'Bayarcash',
            'stripe' => 'Stripe',
            'manual', 'bank_transfer' => 'Bank Transfer',
            default => ucfirst($method),
        };
    }

    protected function getPaymentReference(ProductOrder|FunnelOrder $order, array $context): ?string
    {
        return $context['payment_reference']
            ?? $order->payment_reference
            ?? $order->transaction_reference
            ?? $order->bayarcash_order_no
            ?? null;
    }

    protected function getPaymentStatus(ProductOrder|FunnelOrder $order, array $context): ?string
    {
        $status = $context['payment_status'] ?? $order->payment_status ?? $order->status ?? null;

        if (! $status) {
            return null;
        }

        return match (strtolower($status)) {
            'paid', 'completed', 'success', 'successful' => 'completed',
            'pending', 'processing' => 'pending',
            'failed', 'declined', 'rejected' => 'failed',
            'refunded' => 'refunded',
            'cancelled', 'canceled' => 'cancelled',
            default => $status,
        };
    }

    protected function getPaymentDate(ProductOrder|FunnelOrder $order, array $context): ?string
    {
        $date = $context['paid_at'] ?? $order->paid_at ?? null;

        // If no paid_at, check if order is paid and use updated_at
        if (! $date) {
            $status = $this->getPaymentStatus($order, $context);
            if ($status === 'completed') {
                $date = $order->updated_at;
            }
        }

        return $date ? $this->formatDateTime($date) : null;
    }

    protected function getBankName(ProductOrder|FunnelOrder $order, array $context): ?string
    {
        return $context['bank_name']
            ?? $context['bank']
            ?? $order->bank_name
            ?? $order->fpx_bank
            ?? $this->extractBankFromMetadata($order)
            ?? null;
    }

    protected function extractBankFromMetadata(ProductOrder|FunnelOrder $order): ?string
    {
        $metadata = $order->payment_metadata ?? $order->metadata ?? null;

        if (is_string($metadata)) {
            $metadata = json_decode($metadata, true);
        }

        if (is_array($metadata)) {
            return $metadata['bank_name'] ?? $metadata['bank'] ?? $metadata['fpx_bank'] ?? null;
        }

        return null;
    }

    protected function getTransactionId(ProductOrder|FunnelOrder $order, array $context): ?string
    {
        return $context['transaction_id']
            ?? $order->transaction_id
            ?? $order->payment_intent_id
            ?? $order->stripe_payment_intent
            ?? null;
    }

    protected function formatDateTime(mixed $date): string
    {
        if ($date instanceof Carbon) {
            return $date->format('d M Y, h:i A');
        }

        return Carbon::parse($date)->format('d M Y, h:i A');
    }
}
