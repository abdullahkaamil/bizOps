<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Listeners;

use App\Domain\Notifications\Notifications\TaskActionNeededNotification;
use App\Domain\Notifications\Notifications\TaskMovedNotification;
use App\Domain\Tasks\Enums\ColumnAccess;
use App\Domain\Tasks\Events\TaskTransitioned;
use App\Domain\Tasks\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\Notification;

/**
 * Turns a committed task move into board-wide notifications. Every active board
 * member (internal and external, minus the actor) is told the task moved; anyone
 * whose side is the one that must move the card *out* of the destination step
 * gets the stronger "your action is needed" alert instead (e.g. the customer when
 * work reaches their review step). Runs synchronously inside the tenant context.
 */
class SendTaskTransitionNotifications
{
    public function handle(TaskTransitioned $event): void
    {
        $task = Task::with(['board.members', 'column'])->find($event->taskId);

        if ($task === null || $task->column === null) {
            return;
        }

        $column = $task->column;
        $board = $task->board;

        $recipients = $board->members
            ->filter(fn (User $u): bool => $u->isActive() && $u->id !== $event->actorId)
            ->values();

        // The party responsible for the destination step is whoever may pull a
        // card back out of it. "Both" means no single owner — everyone just gets
        // the broad move notice.
        [$targeted, $broad] = $recipients->partition(
            fn (User $u): bool => $column->move_out !== ColumnAccess::Both && $column->move_out->allows($u->user_type),
        );

        if ($targeted->isNotEmpty()) {
            Notification::send($targeted, new TaskActionNeededNotification(
                $task->id, $task->title, $task->public_id, $board->public_id, $column->name,
            ));
        }

        if ($broad->isNotEmpty()) {
            Notification::send($broad, new TaskMovedNotification(
                $task->id, $task->title, $task->public_id, $board->public_id, $column->name,
            ));
        }
    }
}
