<?php

namespace App\Support;

/**
 * Normalization and validation helpers for Philippine mobile numbers.
 *
 * Accepted input formats are the familiar local format (09XXXXXXXXX) and
 * the international format (+639XXXXXXXXX), with optional spaces, dashes,
 * dots, or parentheses used as separators. Canonical storage format is the
 * 11-digit local form: 09XXXXXXXXX.
 */
final class PhilippineMobile
{
    /**
     * Return the canonical 09XXXXXXXXX representation of the given value,
     * or null when the value is not a valid Philippine mobile number.
     */
    public static function normalize(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $digits = preg_replace('/[\s\-().]/', '', trim($value));
        if (!is_string($digits) || $digits === '') {
            return null;
        }

        if (preg_match('/^09\d{9}$/', $digits) === 1) {
            return $digits;
        }

        if (preg_match('/^\+639\d{9}$/', $digits) === 1) {
            return '0' . substr($digits, 3);
        }

        if (preg_match('/^639\d{9}$/', $digits) === 1) {
            return '0' . substr($digits, 2);
        }

        return null;
    }

    public static function isValid(mixed $value): bool
    {
        return self::normalize($value) !== null;
    }
}
