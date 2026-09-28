<?php

declare(strict_types=1);

namespace App\Domain\Tasks\Models;

use App\Domain\Tasks\Enums\CommentVisibility;
use App\Models\User;
use App\Support\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A comment on a task. Visibility governs whether external representatives can
 * see it: internal comments are hidden from customers.
 *
 * @property int $id
 * @property string $public_id
 * @property int $task_id
 * @property int|null $user_id
 * @property string $body
 * @property CommentVisibility $visibility
 */
class TaskComment extends Model
{
    use HasPublicId, SoftDeletes;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'visibility' => CommentVisibility::class,
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
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
