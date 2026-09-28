<?php

declare(strict_types=1);

namespace App\Domain\Workshop\Models;

use App\Domain\Jobs\Models\Job;
use App\Domain\Workshop\Enums\WorkshopStatus;
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
 * A repair ticket for a device. Lifecycle is driven exclusively by the action
 * classes; timestamps are recorded from the server clock.
 *
 * @property int $id
 * @property string $public_id
 * @property string $number
 * @property int $device_id
 * @property int|null $job_id
 * @property int|null $assigned_user_id
 * @property string $issue_description
 * @property string|null $repair_notes
 * @property WorkshopStatus $status
 * @property CarbonImmutable|null $received_at
 * @property CarbonImmutable|null $completed_at
 * @property CarbonImmutable|null $delivered_at
 * @property int|null $created_by
 * @property int|null $completed_by
 * @property int|null $delivered_by
 */
class WorkshopTicket extends Model
{
    use HasPublicId, LogsActivity, SoftDeletes;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => WorkshopStatus::class,
            'received_at' => 'datetime',
            'completed_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['number', 'status', 'assigned_user_id', 'job_id'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('workshop');
    }

    /**
     * @return BelongsTo<CustomerDevice, $this>
     */
    public function device(): BelongsTo
    {
        return $this->belongsTo(CustomerDevice::class, 'device_id');
    }

    /**
     * @return BelongsTo<Job, $this>
     */
    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class, 'job_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    /**
     * @return HasMany<WorkshopAttachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(WorkshopAttachment::class);
    }

    /**
     * @return HasMany<WorkshopStatusHistory, $this>
     */
    public function statusHistory(): HasMany
    {
        return $this->hasMany(WorkshopStatusHistory::class)->latest('id');
    }
}
