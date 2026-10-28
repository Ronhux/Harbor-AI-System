<?php

namespace App\Support;

use DateTimeInterface;

/**
 * Workflow timestamp formatting.
 *
 * Workflow responses are compared as a set (opens_at <= closes_at <= starts_at < ends_at),
 * so every value in one response must carry the same explicit offset or the same absence of
 * one. This project emits offset-free local wall times, which keeps a whole event payload
 * self-consistent and never invents a display timezone that the record does not carry.
 */
final class WorkflowTimestamp
{
    public const FORMAT = 'Y-m-d\TH:i:s';

    public static function format(mixed $value): ?string
    {
        if ($value === null) return null;
        if ($value instanceof DateTimeInterface) return $value->format(self::FORMAT);

        return is_scalar($value) && (string) $value !== ''
            ? \Carbon\Carbon::parse((string) $value)->format(self::FORMAT)
            : null;
    }
}