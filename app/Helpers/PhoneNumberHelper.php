<?php

namespace App\Helpers;

class PhoneNumberHelper
{
    /**
     * Check if a phone number is masked (contains asterisks)
     * TikTok and other platforms mask phone numbers like: (+60)112*****40, 60148****10
     */
    public static function isMasked(?string $phoneNumber): bool
    {
        if (empty($phoneNumber)) {
            return false;
        }

        return str_contains($phoneNumber, '*');
    }

    /**
     * Normalize phone number to a consistent format for comparison and storage
     * Handles formats like:
     * - 60148271110 -> 60148271110
     * - +60148271110 -> 60148271110
     * - (+60)148271110 -> 60148271110
     * - 0148271110 -> 60148271110 (assumes Malaysia if starts with 0)
     *
     * @param  string|null  $phoneNumber  The phone number to normalize
     * @param  string  $defaultCountryCode  Default country code if not present (default: 60 for Malaysia)
     * @return string|null Normalized phone number or null if invalid/masked
     */
    public static function normalize(?string $phoneNumber, string $defaultCountryCode = '60'): ?string
    {
        if (empty($phoneNumber)) {
            return null;
        }

        // Check if phone number is masked - don't normalize masked numbers
        if (self::isMasked($phoneNumber)) {
            return null;
        }

        // Remove all non-numeric characters (spaces, dashes, parentheses, plus signs)
        $cleaned = preg_replace('/[^0-9]/', '', $phoneNumber);

        if (empty($cleaned)) {
            return null;
        }

        // Handle Malaysian phone numbers starting with 0 (e.g., 0148271110)
        if (str_starts_with($cleaned, '0') && strlen($cleaned) >= 10) {
            $cleaned = $defaultCountryCode.substr($cleaned, 1);
        }

        // If number doesn't start with country code, add default
        if (! str_starts_with($cleaned, $defaultCountryCode) && strlen($cleaned) < 11) {
            $cleaned = $defaultCountryCode.$cleaned;
        }

        // Validate minimum length (country code + phone number)
        if (strlen($cleaned) < 10) {
            return null;
        }

        return $cleaned;
    }

    /**
     * Check if a phone number is valid and unmasked
     */
    public static function isValid(?string $phoneNumber): bool
    {
        if (empty($phoneNumber)) {
            return false;
        }

        // Must not be masked
        if (self::isMasked($phoneNumber)) {
            return false;
        }

        // Must normalize to a valid format
        $normalized = self::normalize($phoneNumber);

        return ! empty($normalized) && strlen($normalized) >= 10;
    }

    /**
     * Format phone number for display
     * 60148271110 -> +60 14-827 1110
     */
    public static function format(?string $phoneNumber): ?string
    {
        $normalized = self::normalize($phoneNumber);

        if (empty($normalized)) {
            return $phoneNumber; // Return original if can't normalize
        }

        // Format Malaysian numbers: +60 XX-XXX XXXX
        if (str_starts_with($normalized, '60') && strlen($normalized) >= 11) {
            return '+60 '.substr($normalized, 2, 2).'-'.substr($normalized, 4, 3).' '.substr($normalized, 7);
        }

        // Generic format: +XX XXXXXXXXX
        return '+'.substr($normalized, 0, 2).' '.substr($normalized, 2);
    }

    /**
     * Compare two phone numbers for equality (handles different formats)
     */
    public static function areEqual(?string $phone1, ?string $phone2): bool
    {
        $normalized1 = self::normalize($phone1);
        $normalized2 = self::normalize($phone2);

        if (empty($normalized1) || empty($normalized2)) {
            return false;
        }

        return $normalized1 === $normalized2;
    }

    /**
     * Extract country code from phone number
     */
    public static function getCountryCode(?string $phoneNumber): ?string
    {
        $normalized = self::normalize($phoneNumber);

        if (empty($normalized) || strlen($normalized) < 2) {
            return null;
        }

        // Extract first 2-3 digits as country code
        return substr($normalized, 0, 2);
    }
}
