<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Models;

use App\Support\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $public_id
 * @property string $name
 * @property string|null $contact_name
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $address
 * @property string|null $notes
 */
class Supplier extends Model
{
    use HasPublicId, SoftDeletes;

    protected $guarded = [];

    /**
     * @return BelongsToMany<InventoryItem, $this>
     */
    public function items(): BelongsToMany
    {
        return $this->belongsToMany(InventoryItem::class, 'inventory_item_suppliers')
            ->withPivot(['supplier_sku', 'last_purchase_price', 'lead_time_days', 'is_preferred'])
            ->withTimestamps();
    }
}
