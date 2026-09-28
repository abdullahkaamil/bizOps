<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A cached per-warehouse balance. Maintained inside stock transactions and must
 * reconcile with SUM(stock_movements.quantity).
 *
 * @property int $id
 * @property int $inventory_item_id
 * @property int $warehouse_id
 * @property string $quantity
 */
class InventoryStockBalance extends Model
{
    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
        ];
    }

    /**
     * @return BelongsTo<Warehouse, $this>
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }
}
