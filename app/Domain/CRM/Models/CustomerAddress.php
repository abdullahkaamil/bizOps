<?php

declare(strict_types=1);

namespace App\Domain\CRM\Models;

use App\Domain\CRM\Concerns\ManagesPrimaryFlag;
use App\Domain\CRM\Enums\AddressType;
use App\Support\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $public_id
 * @property int $customer_id
 * @property AddressType $type
 * @property string|null $label
 * @property string $address_line_1
 * @property string|null $address_line_2
 * @property string $city
 * @property string|null $state
 * @property string|null $postal_code
 * @property string $country_code
 * @property bool $is_primary
 */
class CustomerAddress extends Model
{
    use HasPublicId, ManagesPrimaryFlag, SoftDeletes;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => AddressType::class,
            'is_primary' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
