<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Models;

use App\Domain\Inventory\Enums\InventoryItemStatus;
use App\Support\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A stock-managed inventory item. Quantities are never stored directly on the
 * item — they are derived from the stock ledger (StockMovement).
 *
 * @property int $id
 * @property string $public_id
 * @property string $sku
 * @property string $name
 * @property string|null $description
 * @property string $unit
 * @property InventoryItemStatus $status
 * @property string|null $current_sale_price
 */
class InventoryItem extends Model
{
    use HasPublicId, LogsActivity, SoftDeletes;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => InventoryItemStatus::class,
            'current_sale_price' => 'decimal:2',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['sku', 'name', 'status', 'current_sale_price'])
            ->logOnlyDirty()->dontLogEmptyChanges()->useLogName('inventory');
    }

    /**
     * @return HasMany<StockMovement, $this>
     */
    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    /**
     * @return HasMany<InventoryStockBalance, $this>
     */
    public function balances(): HasMany
    {
        return $this->hasMany(InventoryStockBalance::class);
    }

    /**
     * @return HasMany<InventoryPriceHistory, $this>
     */
    public function priceHistory(): HasMany
    {
        return $this->hasMany(InventoryPriceHistory::class)->latest('id');
    }

    /**
     * @return BelongsToMany<Supplier, $this>
     */
    public function suppliers(): BelongsToMany
    {
        return $this->belongsToMany(Supplier::class, 'inventory_item_suppliers')
            ->withPivot(['supplier_sku', 'last_purchase_price', 'lead_time_days', 'is_preferred'])
            ->withTimestamps();
    }
}
