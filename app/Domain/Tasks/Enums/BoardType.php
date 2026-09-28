<?php

declare(strict_types=1);

namespace App\Domain\Tasks\Enums;

enum BoardType: string
{
    case Internal = 'internal';
    case Project = 'project';

    public function label(): string
    {
        return match ($this) {
            self::Internal => 'Internal board',
            self::Project => 'Project board',
        };
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $t): string => $t->value, self::cases());
    }
}
