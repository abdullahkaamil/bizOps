<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Models;

use App\Domain\Workshop\Models\WorkshopTicket;
use App\Support\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A part consumed on a workshop ticket, with cost/price snapshots captured at
 * consumption time so history stays stable when catalogue prices change.
 *
 * @property int $id
 * @property string $public_id
 * @property int $workshop_ticket_id
 * @property int $inventory_item_id
 * @property string $quantity
 * @property string|null $unit_cost_snapshot
 * @property string|null $sale_price_snapshot
 * @property int|null $created_by
 */
class WorkshopTicketPart extends Model
{
    use HasPublicId;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'unit_cost_snapshot' => 'decimal:2',
            'sale_price_snapshot' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<InventoryItem, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    /**
     * @return BelongsTo<WorkshopTicket, $this>
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(WorkshopTicket::class, 'workshop_ticket_id');
    }
}
