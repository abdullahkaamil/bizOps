<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Minimal money helper. All arithmetic on quotation totals is done in integer
 * minor units (e.g. cents); floats are only used at the input boundary to parse
 * a human-entered major-unit amount, immediately rounded to an integer.
 */
final class Money
{
    /**
     * Parse a major-unit amount (e.g. "12.50") into integer minor units (1250).
     */
    public static function toMinor(int|float|string|null $amount): int
    {
        if ($amount === null || $amount === '') {
            return 0;
        }

        return (int) round(((float) $amount) * 100);
    }

    /**
     * Format integer minor units as a major-unit string (1250 -> "12.50").
     */
    public static function toMajor(int $minor): string
    {
        return number_format($minor / 100, 2, '.', '');
    }

    public static function format(int $minor, string $currency): string
    {
        return $currency.' '.self::toMajor($minor);
    }
}
