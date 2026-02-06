<?php

declare(strict_types=1);

namespace App\Services\MergeTag\DataProviders;

use App\Models\FunnelSession;
use App\Models\ProductOrder;
use App\Models\Student;
use App\Services\MergeTag\DataProviderInterface;

class ContactDataProvider implements DataProviderInterface
{
    public function getValue(string $field, array $context): ?string
    {
        // Try to get contact info from various sources in priority order
        $name = null;
        $firstName = null;
        $lastName = null;
        $email = null;
        $phone = null;

        // 1. From Student model (if available)
        $student = $context['student'] ?? null;
        if ($student instanceof Student) {
            $name = $student->name ?? $student->user?->name;
            $email = $student->email ?? $student->user?->email;
            $phone = $student->phone;
        }

        // 2. From ProductOrder model
        $order = $context['product_order'] ?? $context['order'] ?? null;
        if ($order instanceof ProductOrder) {
            $name = $name ?? $order->customer_name;
            $email = $email ?? $order->email;
            $phone = $phone ?? $order->customer_phone;
        }

        // 3. From FunnelSession model
        $session = $context['funnel_session'] ?? $context['session'] ?? null;
        if ($session instanceof FunnelSession) {
            $email = $email ?? $session->email;
            $phone = $phone ?? $session->phone;
        }

        // 4. From direct context values
        $name = $name ?? $context['customer_name'] ?? $context['name'] ?? null;
        $email = $email ?? $context['email'] ?? null;
        $phone = $phone ?? $context['phone'] ?? $context['customer_phone'] ?? null;

        // Parse first/last name from full name
        if ($name && ! $firstName) {
            $nameParts = explode(' ', trim($name), 2);
            $firstName = $nameParts[0] ?? null;
            $lastName = $nameParts[1] ?? null;
        }

        return match ($field) {
            'name' => $name,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'phone' => $this->formatPhone($phone),
            default => null,
        };
    }

    /**
     * Format phone number for display.
     */
    protected function formatPhone(?string $phone): ?string
    {
        if (! $phone) {
            return null;
        }

        // Clean up the phone number
        $phone = preg_replace('/[^0-9+]/', '', $phone);

        // Add Malaysian prefix if needed
        if (str_starts_with($phone, '0')) {
            $phone = '+60'.substr($phone, 1);
        } elseif (str_starts_with($phone, '60') && ! str_starts_with($phone, '+60')) {
            $phone = '+'.$phone;
        }

        return $phone;
    }
}
