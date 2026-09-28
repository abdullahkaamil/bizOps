<?php

declare(strict_types=1);

namespace App\Domain\Tasks\Models;

use App\Domain\Tasks\Enums\TaskStatus;
use App\Models\User;
use App\Support\Concerns\HasPublicId;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An immutable audit row recording one task state transition: who moved it,
 * from where to where, and why. Append-only.
 *
 * @property int $id
 * @property string $public_id
 * @property int $task_id
 * @property TaskStatus|null $from_status
 * @property TaskStatus $to_status
 * @property int|null $actor_id
 * @property string|null $reason
 * @property array<string, mixed>|null $metadata
 * @property CarbonImmutable|null $created_at
 */
class TaskStatusHistory extends Model
{
    use HasPublicId;

    protected $table = 'task_status_history';

    public $timestamps = false;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'from_status' => TaskStatus::class,
            'to_status' => TaskStatus::class,
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Task, $this>
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
