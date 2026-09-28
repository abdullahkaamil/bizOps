<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Actions;

use App\Domain\Inventory\Enums\MovementType;
use App\Domain\Inventory\Models\InventoryItem;
use App\Domain\Inventory\Models\Warehouse;
use App\Domain\Inventory\Models\WorkshopTicketPart;
use App\Domain\Inventory\StockLedger;
use App\Domain\Workshop\Models\WorkshopTicket;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Consume a part on a workshop ticket. Atomic: the part record, the stock
 * movement, and the balance update all commit together (or all roll back). Cost
 * and sale price are snapshotted so history stays stable.
 */
class AddPartToWorkshopTicketAction
{
    public function __construct(private StockLedger $ledger) {}

    public function handle(
        WorkshopTicket $ticket,
        InventoryItem $item,
        float $quantity,
        User $actor,
        ?Warehouse $warehouse = null,
    ): WorkshopTicketPart {
        $warehouse ??= Warehouse::default();

        return DB::transaction(function () use ($ticket, $item, $quantity, $actor, $warehouse): WorkshopTicketPart {
            // Records the movement (validates stock policy under a row lock).
            $this->ledger->record($item, $warehouse, MovementType::WorkshopConsumption, $quantity, $actor, [
                'reference' => $ticket,
            ]);

            return WorkshopTicketPart::create([
                'workshop_ticket_id' => $ticket->id,
                'inventory_item_id' => $item->id,
                'quantity' => abs($quantity),
                'unit_cost_snapshot' => $this->costSnapshot($item),
                'sale_price_snapshot' => $item->current_sale_price,
                'created_by' => $actor->id,
            ]);
        });
    }

    /**
     * Best-available cost at consumption time: the most recent purchase movement,
     * else the preferred supplier's last purchase price. Returned as an exact
     * decimal string (money is never handled as a float) and stored into the
     * decimal(12,2) snapshot column.
     */
    private function costSnapshot(InventoryItem $item): ?string
    {
        $lastPurchase = $item->movements()
            ->where('type', MovementType::Purchase->value)
            ->whereNotNull('unit_cost')
            ->latest('id')
            ->value('unit_cost');

        if ($lastPurchase !== null) {
            return (string) $lastPurchase;
        }

        $supplierPrice = $item->suppliers()
            ->orderByDesc('inventory_item_suppliers.is_preferred')
            ->value('inventory_item_suppliers.last_purchase_price');

        return $supplierPrice !== null ? (string) $supplierPrice : null;
    }
}
