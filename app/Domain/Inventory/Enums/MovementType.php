<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Enums;

enum MovementType: string
{
    case Opening = 'opening';
    case Purchase = 'purchase';
    case AdjustmentIn = 'adjustment_in';
    case AdjustmentOut = 'adjustment_out';
    case WorkshopConsumption = 'workshop_consumption';
    case WorkshopReturn = 'workshop_return';
    case Sale = 'sale';
    case Return = 'return';
    case TransferIn = 'transfer_in';
    case TransferOut = 'transfer_out';

    /**
     * The direction this movement applies to a signed quantity: +1 into stock,
     * -1 out of stock.
     */
    public function direction(): int
    {
        return match ($this) {
            self::Opening, self::Purchase, self::AdjustmentIn,
            self::WorkshopReturn, self::Return, self::TransferIn => 1,
            self::AdjustmentOut, self::WorkshopConsumption,
            self::Sale, self::TransferOut => -1,
        };
    }

    public function label(): string
    {
        return ucfirst(str_replace('_', ' ', $this->value));
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $t): string => $t->value, self::cases());
    }

    /**
     * Movement types a user may pick for a manual stock adjustment.
     *
     * @return array<int, string>
     */
    public static function adjustableValues(): array
    {
        return [
            self::Opening->value, self::Purchase->value,
            self::AdjustmentIn->value, self::AdjustmentOut->value,
        ];
    }
}
