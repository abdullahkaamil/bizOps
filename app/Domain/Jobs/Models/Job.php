<?php

declare(strict_types=1);

namespace App\Domain\Jobs\Models;

use App\Domain\CRM\Models\Customer;
use App\Domain\CRM\Models\CustomerAddress;
use App\Domain\CRM\Models\CustomerContact;
use App\Domain\Jobs\Enums\JobStatus;
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
 * A field-service job. Its lifecycle is driven exclusively through the action
 * classes; timestamps (start/end) are always recorded from the server clock.
 *
 * @property int $id
 * @property string $public_id
 * @property string $number
 * @property int $customer_id
 * @property int|null $customer_contact_id
 * @property int|null $service_address_id
 * @property int|null $assigned_user_id
 * @property string $title
 * @property string|null $description
 * @property JobStatus $status
 * @property CarbonImmutable|null $planned_at
 * @property CarbonImmutable|null $actual_start_at
 * @property CarbonImmutable|null $actual_end_at
 * @property string|null $service_notes
 * @property string|null $internal_notes
 * @property int|null $created_by
 * @property int|null $completed_by
 * @property int|null $canceled_by
 */
class Job extends Model
{
    use HasPublicId, LogsActivity, SoftDeletes;

    protected $table = 'service_jobs';

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => JobStatus::class,
            'planned_at' => 'datetime',
            'actual_start_at' => 'datetime',
            'actual_end_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['number', 'title', 'status', 'assigned_user_id', 'planned_at'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('job');
    }

    public function isInProgress(): bool
    {
        return $this->status === JobStatus::InProgress;
    }

    /**
     * Server-computed duration in whole minutes, or null until the job is done.
     */
    public function durationMinutes(): ?int
    {
        if ($this->actual_start_at === null || $this->actual_end_at === null) {
            return null;
        }

        return (int) $this->actual_start_at->diffInMinutes($this->actual_end_at);
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
     * @return BelongsTo<CustomerAddress, $this>
     */
    public function serviceAddress(): BelongsTo
    {
        return $this->belongsTo(CustomerAddress::class, 'service_address_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    /**
     * @return HasMany<JobImage, $this>
     */
    public function images(): HasMany
    {
        return $this->hasMany(JobImage::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @return HasMany<JobSignature, $this>
     */
    public function signatures(): HasMany
    {
        return $this->hasMany(JobSignature::class);
    }

    /**
     * @return HasMany<JobStatusHistory, $this>
     */
    public function statusHistory(): HasMany
    {
        return $this->hasMany(JobStatusHistory::class)->latest('id');
    }
}
