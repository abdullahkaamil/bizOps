<?php

declare(strict_types=1);

namespace App\Domain\Quotations\Models;

use App\Domain\Inventory\Models\InventoryItem;
use App\Domain\Quotations\Enums\DiscountType;
use App\Domain\Quotations\Enums\LineType;
use App\Support\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A quotation line. Retains the linked inventory item AND snapshots of the
 * internal name, cost, price and tax at quotation time, so later inventory
 * changes never alter historical quotations. Money fields are minor units.
 *
 * @property int $id
 * @property string $public_id
 * @property int $quotation_id
 * @property int|null $inventory_item_id
 * @property LineType $line_type
 * @property string|null $internal_name_snapshot
 * @property string $customer_alias
 * @property string|null $description
 * @property string $quantity
 * @property string $unit
 * @property int|null $unit_cost_snapshot
 * @property int $unit_price
 * @property DiscountType|null $discount_type
 * @property string|null $discount_value
 * @property string $tax_rate
 * @property int $line_subtotal
 * @property int $line_discount
 * @property int $line_tax
 * @property int $line_total
 * @property int $sort_order
 */
class QuotationLine extends Model
{
    use HasPublicId;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'line_type' => LineType::class,
            'discount_type' => DiscountType::class,
            'quantity' => 'decimal:3',
            'tax_rate' => 'decimal:3',
            'discount_value' => 'decimal:3',
            'unit_cost_snapshot' => 'integer',
            'unit_price' => 'integer',
            'line_subtotal' => 'integer',
            'line_discount' => 'integer',
            'line_tax' => 'integer',
            'line_total' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<InventoryItem, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }
}
