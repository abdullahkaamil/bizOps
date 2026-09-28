<?php

declare(strict_types=1);

namespace App\Domain\Jobs\Models;

use App\Domain\Jobs\Enums\JobStatus;
use App\Models\User;
use App\Support\Concerns\HasPublicId;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Immutable audit row for one job state transition.
 *
 * @property int $id
 * @property string $public_id
 * @property int $job_id
 * @property JobStatus|null $from_status
 * @property JobStatus $to_status
 * @property int|null $actor_id
 * @property array<string, mixed>|null $metadata
 * @property CarbonImmutable|null $created_at
 */
class JobStatusHistory extends Model
{
    use HasPublicId;

    protected $table = 'job_status_history';

    public $timestamps = false;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'from_status' => JobStatus::class,
            'to_status' => JobStatus::class,
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Job, $this>
     */
    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
