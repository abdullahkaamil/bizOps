<?php

declare(strict_types=1);

namespace App\Domain\Inventory;

use App\Domain\Inventory\Enums\MovementType;
use App\Domain\Inventory\Exceptions\InsufficientStock;
use App\Domain\Inventory\Models\InventoryItem;
use App\Domain\Inventory\Models\InventoryStockBalance;
use App\Domain\Inventory\Models\StockMovement;
use App\Domain\Inventory\Models\Warehouse;
use App\Domain\Settings\TenantSettings;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * The single writer for the stock ledger. Every movement is recorded atomically:
 * the per-(item, warehouse) balance row is locked FOR UPDATE, the negative-stock
 * policy is enforced against the *locked* balance (so concurrent consumers cannot
 * oversell), the immutable movement is appended, and the cached balance updated.
 */
class StockLedger
{
    public function __construct(private TenantSettings $settings) {}

    /**
     * @param  array{unit_cost?: int|string|null, reason?: string|null, reference?: Model|null, occurred_at?: \DateTimeInterface|null}  $options
     */
    public function record(
        InventoryItem $item,
        Warehouse $warehouse,
        MovementType $type,
        float $magnitude,
        ?User $actor = null,
        array $options = [],
    ): StockMovement {
        $delta = abs($magnitude) * $type->direction();

        return DB::transaction(function () use ($item, $warehouse, $type, $delta, $magnitude, $actor, $options): StockMovement {
            $balance = $this->lockedBalance($item->id, $warehouse->id);
            $newQuantity = (float) $balance->quantity + $delta;

            if ($delta < 0 && ! $this->settings->inventoryAllowsNegativeStock() && $newQuantity < 0) {
                throw InsufficientStock::for($item->sku, (float) $balance->quantity, abs($magnitude));
            }

            $reference = $options['reference'] ?? null;

            $movement = StockMovement::create([
                'inventory_item_id' => $item->id,
                'warehouse_id' => $warehouse->id,
                'type' => $type,
                'quantity' => $delta,
                'unit_cost' => $options['unit_cost'] ?? null,
                'reference_type' => $reference?->getMorphClass(),
                'reference_id' => $reference?->getKey(),
                'reason' => $options['reason'] ?? null,
                'actor_id' => $actor?->id,
                'occurred_at' => $options['occurred_at'] ?? now(),
                'created_at' => now(),
            ]);

            $balance->update(['quantity' => $newQuantity]);

            return $movement;
        });
    }

    /**
     * The cached balance for an item in a warehouse.
     */
    public function balance(int $itemId, int $warehouseId): float
    {
        return (float) (InventoryStockBalance::query()
            ->where('inventory_item_id', $itemId)
            ->where('warehouse_id', $warehouseId)
            ->value('quantity') ?? 0);
    }

    /**
     * The authoritative balance recomputed from the ledger (for reconciliation).
     */
    public function reconciledBalance(int $itemId, int $warehouseId): float
    {
        return (float) StockMovement::query()
            ->where('inventory_item_id', $itemId)
            ->where('warehouse_id', $warehouseId)
            ->sum('quantity');
    }

    private function lockedBalance(int $itemId, int $warehouseId): InventoryStockBalance
    {
        $balance = InventoryStockBalance::query()
            ->where('inventory_item_id', $itemId)
            ->where('warehouse_id', $warehouseId)
            ->lockForUpdate()
            ->first();

        if ($balance !== null) {
            return $balance;
        }

        $created = InventoryStockBalance::create([
            'inventory_item_id' => $itemId,
            'warehouse_id' => $warehouseId,
            'quantity' => 0,
        ]);

        // Re-select with the lock held for the remainder of the transaction.
        return InventoryStockBalance::query()->whereKey($created->id)->lockForUpdate()->firstOrFail();
    }
}
