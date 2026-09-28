<?php

declare(strict_types=1);

namespace App\Domain\CRM\Enums;

enum AddressType: string
{
    case Billing = 'billing';
    case Shipping = 'shipping';
    case Service = 'service';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $type): string => $type->value, self::cases());
    }
}
