<?php

declare(strict_types=1);

namespace App\Domain\Tasks\Events;

use App\Domain\Tasks\Enums\TaskStatus;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired after a task transition has been committed. Downstream notification and
 * digest listeners (wired in the notifications phase) react to this.
 */
class TaskTransitioned
{
    use Dispatchable;

    public function __construct(
        public int $taskId,
        public TaskStatus $from,
        public TaskStatus $to,
        public int $actorId,
        public ?string $reason = null,
    ) {}
}
