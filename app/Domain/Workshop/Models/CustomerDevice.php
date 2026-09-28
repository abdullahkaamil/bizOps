<?php

declare(strict_types=1);

namespace App\Domain\Workshop\Models;

use App\Domain\CRM\Models\Customer;
use App\Support\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A physical device belonging to a customer. Serial numbers are unique per tenant
 * (partial unique index) but may be null when unavailable.
 *
 * @property int $id
 * @property string $public_id
 * @property int $customer_id
 * @property string|null $serial_number
 * @property bool $serial_number_unavailable
 * @property string $brand
 * @property string $model
 * @property string|null $notes
 */
class CustomerDevice extends Model
{
    use HasPublicId, LogsActivity, SoftDeletes;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'serial_number_unavailable' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['serial_number', 'brand', 'model', 'customer_id'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('device');
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return HasMany<WorkshopTicket, $this>
     */
    public function tickets(): HasMany
    {
        return $this->hasMany(WorkshopTicket::class, 'device_id')->latest('id');
    }
}
