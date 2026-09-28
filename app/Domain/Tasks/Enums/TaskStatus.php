<?php

declare(strict_types=1);

namespace App\Domain\Tasks\Enums;

enum TaskStatus: string
{
    case Todo = 'todo';
    case InProgress = 'in_progress';
    case Review = 'review';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::Todo => __('To do'),
            self::InProgress => __('In progress'),
            self::Review => __('In review'),
            self::Completed => __('Completed'),
        };
    }

    public function isTerminal(): bool
    {
        return $this === self::Completed;
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $s): string => $s->value, self::cases());
    }
}
