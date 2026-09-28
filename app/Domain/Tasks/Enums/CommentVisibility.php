<?php

declare(strict_types=1);

namespace App\Domain\Tasks\Enums;

enum CommentVisibility: string
{
    /** Visible only to internal employees. */
    case Internal = 'internal';

    /** Visible to the customer's external representatives as well. */
    case Customer = 'customer';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $v): string => $v->value, self::cases());
    }
}
