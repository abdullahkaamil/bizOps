<?php

declare(strict_types=1);

namespace App\Domain\Tasks\Models;

use App\Domain\Tasks\Enums\Priority;
use App\Domain\Tasks\Enums\TaskStatus;
use App\Models\User;
use App\Support\Concerns\HasPublicId;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A unit of work on a board. Its lifecycle is driven exclusively through the
 * transition action classes — never by arbitrary status writes.
 *
 * @property int $id
 * @property string $public_id
 * @property int $board_id
 * @property int|null $board_column_id
 * @property string $title
 * @property string|null $description
 * @property TaskStatus $status
 * @property int $position
 * @property Priority|null $priority
 * @property CarbonImmutable|null $due_at
 * @property int|null $created_by
 * @property CarbonImmutable|null $completed_at
 * @property-read Board $board
 * @property-read BoardColumn|null $column
 */
class Task extends Model
{
    use HasPublicId, LogsActivity, SoftDeletes;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => TaskStatus::class,
            'position' => 'integer',
            'priority' => Priority::class,
            'due_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // A task is always born inside a column. When the caller does not name one
        // explicitly, drop it into the board column whose category matches its
        // status (or the first column), appended to the bottom.
        static::creating(function (Task $task): void {
            if ($task->board_column_id !== null) {
                return;
            }

            $status = $task->getAttributeValue('status');
            $category = $status instanceof TaskStatus ? $status->value : TaskStatus::Todo->value;

            $column = BoardColumn::where('board_id', $task->board_id)
                ->orderByRaw('CASE WHEN category = ? THEN 0 ELSE 1 END', [$category])
                ->orderBy('position')
                ->first();

            if ($column !== null) {
                $task->board_column_id = $column->id;
                $task->position = (int) Task::where('board_column_id', $column->id)->max('position') + 1;
            }
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['title', 'status', 'priority', 'due_at'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('task');
    }

    public function isCompleted(): bool
    {
        return $this->status === TaskStatus::Completed;
    }

    public function isAssigned(User $user): bool
    {
        return $this->assignees()->whereKey($user->getKey())->exists();
    }

    /**
     * @return BelongsTo<Board, $this>
     */
    public function board(): BelongsTo
    {
        return $this->belongsTo(Board::class);
    }

    /**
     * @return BelongsTo<BoardColumn, $this>
     */
    public function column(): BelongsTo
    {
        return $this->belongsTo(BoardColumn::class, 'board_column_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function assignees(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'task_assignees')
            ->withTimestamps();
    }

    /**
     * @return HasMany<TaskComment, $this>
     */
    public function comments(): HasMany
    {
        return $this->hasMany(TaskComment::class);
    }

    /**
     * @return HasMany<TaskAttachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(TaskAttachment::class);
    }

    /**
     * @return HasMany<TaskStatusHistory, $this>
     */
    public function statusHistory(): HasMany
    {
        return $this->hasMany(TaskStatusHistory::class)->latest('id');
    }
}
