<?php

declare(strict_types=1);

namespace App\Domain\Tasks\Actions;

use App\Domain\Tasks\Enums\TaskStatus;
use App\Domain\Tasks\Events\TaskTransitioned;
use App\Domain\Tasks\Exceptions\InvalidTaskTransition;
use App\Domain\Tasks\Models\BoardColumn;
use App\Domain\Tasks\Models\Task;
use App\Domain\Tasks\Models\TaskStatusHistory;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * Move a task to any column on its board and place it at a given position. This
 * replaces the old fixed state machine: movement is free, and a task's status /
 * completion are *derived* from the destination column's category so dashboards,
 * search and notifications stay coherent no matter how columns are labelled.
 *
 * A column change is recorded on the immutable history and dispatched as a domain
 * event (so review/approval notifications still fire); a pure reorder within the
 * same column is not.
 */
class MoveTaskAction
{
    public function handle(Task $task, User $actor, BoardColumn $target, ?int $position = null): Task
    {
        if ($target->board_id !== $task->board_id) {
            throw InvalidTaskTransition::differentBoard();
        }

        // Per-column authority: the actor must be allowed to pull a card out of
        // its current step and to drop it into the destination step. A reorder
        // within the same column is exempt (it is neither an in nor an out move).
        if ($task->board_column_id !== $target->id) {
            $source = $task->column;

            if ($source !== null && ! $source->allowsMoveOut($actor->user_type)) {
                throw new AuthorizationException(__('You are not allowed to move a task out of this step.'));
            }

            if (! $target->allowsMoveIn($actor->user_type)) {
                throw new AuthorizationException(__('You are not allowed to move a task into this step.'));
            }
        }

        $fromColumnId = $task->board_column_id;
        $fromStatus = $task->status;
        $toStatus = $target->category;
        $columnChanged = $fromColumnId !== $target->id;

        $index = $this->resolvePosition($task, $target, $position);

        DB::transaction(function () use ($task, $actor, $target, $index, $fromColumnId, $fromStatus, $toStatus, $columnChanged): void {
            // Make room at the destination index within the target column.
            Task::where('board_column_id', $target->id)
                ->where('id', '!=', $task->id)
                ->where('position', '>=', $index)
                ->increment('position');

            $task->board_column_id = $target->id;
            $task->position = $index;
            $task->status = $toStatus;

            if ($toStatus === TaskStatus::Completed) {
                $task->completed_at = now();
            } elseif ($fromStatus === TaskStatus::Completed) {
                $task->completed_at = null;
            }

            $task->save();

            if (! $columnChanged) {
                return;
            }

            TaskStatusHistory::create([
                'task_id' => $task->id,
                'from_status' => $fromStatus,
                'to_status' => $toStatus,
                'actor_id' => $actor->id,
                'reason' => null,
                'metadata' => [
                    'from_column_id' => $fromColumnId,
                    'to_column_id' => $target->id,
                    'to_column' => $target->name,
                ],
                'created_at' => now(),
            ]);

            activity('task')
                ->performedOn($task)
                ->causedBy($actor)
                ->withProperties([
                    'from' => $fromStatus->value,
                    'to' => $toStatus->value,
                    'column' => $target->name,
                ])
                ->event('moved')
                ->log("task.moved_to_{$toStatus->value}");

            DB::afterCommit(function () use ($task, $fromStatus, $toStatus, $actor): void {
                TaskTransitioned::dispatch($task->id, $fromStatus, $toStatus, $actor->id, null);
            });
        });

        return $task->refresh();
    }

    /**
     * Clamp the requested index into the target column's task count (excluding the
     * moving task). A null request appends to the bottom.
     */
    private function resolvePosition(Task $task, BoardColumn $target, ?int $position): int
    {
        $count = Task::where('board_column_id', $target->id)
            ->where('id', '!=', $task->id)
            ->count();

        if ($position === null) {
            return $count;
        }

        return max(0, min($position, $count));
    }
}
