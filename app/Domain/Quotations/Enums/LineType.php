<?php

declare(strict_types=1);

namespace App\Domain\Quotations\Enums;

enum LineType: string
{
    case Item = 'item';
    case Custom = 'custom';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $t): string => $t->value, self::cases());
    }
}
