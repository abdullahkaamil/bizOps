<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Models;

use App\Domain\Inventory\Enums\PriceType;
use App\Support\Concerns\HasPublicId;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $public_id
 * @property int $inventory_item_id
 * @property PriceType $price_type
 * @property string $amount
 * @property string $currency
 * @property CarbonImmutable|null $effective_at
 * @property int|null $created_by
 */
class InventoryPriceHistory extends Model
{
    use HasPublicId;

    protected $table = 'inventory_price_history';

    public $timestamps = false;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price_type' => PriceType::class,
            'amount' => 'decimal:2',
            'effective_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }
}
