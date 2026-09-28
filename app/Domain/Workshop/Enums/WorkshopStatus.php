<?php

declare(strict_types=1);

namespace App\Domain\Workshop\Enums;

enum WorkshopStatus: string
{
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Delivered = 'delivered';

    public function label(): string
    {
        return match ($this) {
            self::InProgress => __('In progress'),
            self::Completed => __('Completed'),
            self::Delivered => __('Delivered'),
        };
    }

    /**
     * Statuses that satisfy a linked job's completion constraint.
     */
    public function isResolved(): bool
    {
        return $this === self::Completed || $this === self::Delivered;
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $s): string => $s->value, self::cases());
    }
}
