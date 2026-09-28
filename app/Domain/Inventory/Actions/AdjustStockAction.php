<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Actions;

use App\Domain\Inventory\Enums\MovementType;
use App\Domain\Inventory\Models\InventoryItem;
use App\Domain\Inventory\Models\StockMovement;
use App\Domain\Inventory\Models\Warehouse;
use App\Domain\Inventory\StockLedger;
use App\Models\User;

/**
 * A manual stock adjustment (opening / purchase / adjustment in|out).
 */
class AdjustStockAction
{
    public function __construct(private StockLedger $ledger) {}

    public function handle(
        InventoryItem $item,
        Warehouse $warehouse,
        MovementType $type,
        float $quantity,
        User $actor,
        int|string|null $unitCost = null,
        ?string $reason = null,
    ): StockMovement {
        return $this->ledger->record($item, $warehouse, $type, $quantity, $actor, [
            'unit_cost' => $unitCost,
            'reason' => $reason,
        ]);
    }
}
