<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Currencies the tenant panel supports. Stored as the ISO 4217 code; the symbol
 * is used for display. Money arithmetic is code-agnostic (see App\Support\Money).
 */
enum Currency: string
{
    case TRY = 'TRY';
    case USD = 'USD';
    case EUR = 'EUR';
    case GBP = 'GBP';

    public function symbol(): string
    {
        return match ($this) {
            self::TRY => '₺',
            self::USD => '$',
            self::EUR => '€',
            self::GBP => '£',
        };
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $c): string => $c->value, self::cases());
    }

    /**
     * Options for a select input: e.g. ['value' => 'TRY', 'label' => 'TRY (₺)'].
     *
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            static fn (self $c): array => ['value' => $c->value, 'label' => "{$c->value} ({$c->symbol()})"],
            self::cases(),
        );
    }
}
