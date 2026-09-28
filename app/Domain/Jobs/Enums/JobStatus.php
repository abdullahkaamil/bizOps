<?php

declare(strict_types=1);

namespace App\Domain\Jobs\Enums;

enum JobStatus: string
{
    case Pending = 'pending';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Canceled = 'canceled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('Pending'),
            self::InProgress => __('In progress'),
            self::Completed => __('Completed'),
            self::Canceled => __('Canceled'),
        };
    }

    public function isTerminal(): bool
    {
        return $this === self::Completed || $this === self::Canceled;
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $s): string => $s->value, self::cases());
    }
}
