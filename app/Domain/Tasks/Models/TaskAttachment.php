<?php

declare(strict_types=1);

namespace App\Domain\Tasks\Models;

use App\Domain\Tasks\Enums\CommentVisibility;
use App\Models\User;
use App\Support\Concerns\HasPublicId;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A file attached to a task. Visibility mirrors comments: internal attachments
 * are never served to external representatives.
 *
 * @property int $id
 * @property string $public_id
 * @property int $task_id
 * @property int|null $uploaded_by
 * @property string $disk
 * @property string $path
 * @property string $original_name
 * @property string|null $mime_type
 * @property int $size_bytes
 * @property CommentVisibility $visibility
 * @property CarbonImmutable|null $created_at
 */
class TaskAttachment extends Model
{
    use HasPublicId;

    public $timestamps = false;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'visibility' => CommentVisibility::class,
            'created_at' => 'datetime',
            'size_bytes' => 'integer',
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
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
