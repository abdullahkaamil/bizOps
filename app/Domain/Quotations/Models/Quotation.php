<?php

declare(strict_types=1);

namespace App\Domain\Quotations\Models;

use App\Domain\CRM\Models\Customer;
use App\Domain\CRM\Models\CustomerContact;
use App\Domain\Quotations\Enums\QuotationStatus;
use App\Models\User;
use App\Support\Concerns\HasPublicId;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A quotation. Money fields are integer minor units. Lifecycle is driven by the
 * action classes; a sent/terminal quotation is locked against line edits.
 *
 * @property int $id
 * @property string $public_id
 * @property string $number
 * @property int $customer_id
 * @property int|null $customer_contact_id
 * @property QuotationStatus $status
 * @property CarbonImmutable $issue_date
 * @property CarbonImmutable|null $valid_until
 * @property string $currency
 * @property int $subtotal
 * @property int $discount_total
 * @property int $tax_total
 * @property int $grand_total
 * @property string|null $notes
 * @property string|null $terms
 * @property int|null $created_by
 * @property CarbonImmutable|null $sent_at
 * @property CarbonImmutable|null $accepted_at
 * @property CarbonImmutable|null $rejected_at
 * @property string|null $decided_by_name
 * @property string|null $decided_ip
 */
class Quotation extends Model
{
    use HasPublicId, LogsActivity, SoftDeletes;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => QuotationStatus::class,
            'issue_date' => 'date',
            'valid_until' => 'date',
            'subtotal' => 'integer',
            'discount_total' => 'integer',
            'tax_total' => 'integer',
            'grand_total' => 'integer',
            'sent_at' => 'datetime',
            'accepted_at' => 'datetime',
            'rejected_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['number', 'status', 'grand_total'])
            ->logOnlyDirty()->dontLogEmptyChanges()->useLogName('quotation');
    }

    public function isExpired(): bool
    {
        return $this->status === QuotationStatus::Sent
            && $this->valid_until !== null
            && $this->valid_until->isPast();
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return BelongsTo<CustomerContact, $this>
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(CustomerContact::class, 'customer_contact_id');
    }

    /**
     * @return HasMany<QuotationLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(QuotationLine::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @return HasMany<QuotationStatusHistory, $this>
     */
    public function statusHistory(): HasMany
    {
        return $this->hasMany(QuotationStatusHistory::class)->latest('id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
