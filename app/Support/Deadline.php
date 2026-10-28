<?php

namespace App\Support;

use DateTimeInterface;
use Illuminate\Support\Carbon;

/**
 * Verification deadlines fail closed.
 *
 * A verification deadline is only meaningful while it is explicitly set AND in the
 * future. An unset (NULL) deadline authorises nobody - it is not treated as "never
 * expires" and not treated as "ignore the check". Callers must use this instead of
 * calling isFuture() on a column that the schema allows to be NULL, because a bare
 * ->isFuture() on NULL is a fatal error and can never authorise.
 *
 * The underlying columns must never carry an implicit ON UPDATE CURRENT_TIMESTAMP.
 * On this server explicit_defaults_for_timestamp is OFF, so MariaDB silently gives
 * a non-first timestamp column DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP.
 * Any UPDATE that touches an unrelated column on the same row then rewrites the
 * deadline forward. Migration 2026_10_17_000001 removes that behaviour; this helper
 * guarantees the application also fails closed if it ever reappears.
 *
 * "Now" is always Carbon::now(), never PHP's native clock, so a frozen or shifted
 * application clock is honoured consistently with every other time comparison here.
 */
final class Deadline
{
    public static function active(mixed $deadline): bool
    {
        if ($deadline === null) return false;

        if ($deadline instanceof DateTimeInterface) {
            return Carbon::instance($deadline)->isFuture();
        }

        if (is_string($deadline) && trim($deadline) !== '') {
            try {
                return Carbon::parse($deadline)->isFuture();
            } catch (\Throwable) {
                return false; // Unparseable deadline authorises nobody.
            }
        }

        return false;
    }

    /**
     * True only when the deadline is no longer valid. A deadline that is missing,
     * unparseable or in the past is reported as expired so callers can distinguish
     * "never expires" from "no longer valid" without ever granting access.
     */
    public static function expired(mixed $deadline): bool
    {
        return !self::active($deadline);
    }
}