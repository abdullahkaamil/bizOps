<?php

declare(strict_types=1);

namespace App\Domain\CRM\Models;

use App\Domain\CRM\Enums\CustomerStatus;
use App\Models\User;
use App\Support\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A customer of the tenant. The hub that jobs, workshop tickets, quotations and
 * project boards reference. Lives in the tenant database.
 *
 * @property int $id
 * @property string $public_id
 * @property string $company_name
 * @property string|null $email
 * @property string|null $phone
 * @property CustomerStatus $status
 * @property string|null $tax_number
 * @property string|null $notes
 */
class Customer extends Model
{
    use HasPublicId, LogsActivity, SoftDeletes;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => CustomerStatus::class,
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['company_name', 'email', 'phone', 'status', 'tax_number'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('customer');
    }

    /**
     * @return HasMany<CustomerContact, $this>
     */
    public function contacts(): HasMany
    {
        return $this->hasMany(CustomerContact::class);
    }

    /**
     * @return HasMany<CustomerAddress, $this>
     */
    public function addresses(): HasMany
    {
        return $this->hasMany(CustomerAddress::class);
    }

    /**
     * @return HasOne<CustomerContact, $this>
     */
    public function primaryContact(): HasOne
    {
        return $this->hasOne(CustomerContact::class)->where('is_primary', true);
    }

    /**
     * @return HasOne<CustomerAddress, $this>
     */
    public function primaryAddress(): HasOne
    {
        return $this->hasOne(CustomerAddress::class)->where('is_primary', true);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @param  Builder<Customer>  $query
     */
    public function scopeStatus(Builder $query, ?string $status): void
    {
        $query->when($status !== null, fn (Builder $q) => $q->where('status', $status));
    }
}
