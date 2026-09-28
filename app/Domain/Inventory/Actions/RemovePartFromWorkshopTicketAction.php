<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Actions;

use App\Domain\Inventory\Enums\MovementType;
use App\Domain\Inventory\Models\Warehouse;
use App\Domain\Inventory\Models\WorkshopTicketPart;
use App\Domain\Inventory\StockLedger;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Remove/return a consumed part: records a reversing WorkshopReturn movement
 * (returning stock) and deletes the part record, atomically.
 */
class RemovePartFromWorkshopTicketAction
{
    public function __construct(private StockLedger $ledger) {}

    public function handle(WorkshopTicketPart $part, User $actor, ?Warehouse $warehouse = null): void
    {
        $warehouse ??= Warehouse::default();

        DB::transaction(function () use ($part, $actor, $warehouse): void {
            $this->ledger->record(
                $part->item,
                $warehouse,
                MovementType::WorkshopReturn,
                (float) $part->quantity,
                $actor,
                ['reference' => $part->ticket, 'reason' => 'Part removed from ticket'],
            );

            $part->delete();
        });
    }
}
