<?php

declare(strict_types=1);

namespace App\Domain\Tasks\Enums;

use App\Enums\UserType;

/**
 * Which side may move a task across a column boundary. Each board column stores
 * two of these — one guarding cards moving *into* the column, one guarding cards
 * moving *out* — so a step can be handed to a specific party (e.g. a "waiting on
 * customer" column only the external representative can pull a card out of).
 */
enum ColumnAccess: string
{
    case Internal = 'internal';
    case External = 'external';
    case Both = 'both';

    public function allows(UserType $type): bool
    {
        return match ($this) {
            self::Both => true,
            self::Internal => $type === UserType::Internal,
            self::External => $type === UserType::External,
        };
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $a): string => $a->value, self::cases());
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            static fn (self $a): array => ['value' => $a->value, 'label' => $a->value],
            self::cases(),
        );
    }
}
