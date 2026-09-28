<?php

declare(strict_types=1);

namespace App\Domain\Tasks\Policies;

use App\Domain\Tasks\Enums\CommentVisibility;
use App\Domain\Tasks\Models\Board;
use App\Domain\Tasks\Models\Task;
use App\Enums\Permission;
use App\Models\User;

/**
 * Authorizes task viewing, mutation and movement. Boards use free movement:
 * tasks can be dragged to any column on their board (the old fixed state machine
 * is gone). The rules keep external isolation intact:
 *
 *  - External representatives act only on project boards they belong to; they may
 *    move tasks between columns (this generalizes the old approve/reject) and add
 *    customer-visible comments — nothing else.
 *  - Internal staff move, create, update and delete tasks, gated by permission and
 *    board reachability.
 */
class TaskPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ViewTasks->value);
    }

    public function view(User $user, Task $task): bool
    {
        return $this->canReach($user, $task->board);
    }

    public function create(User $user, Board $board): bool
    {
        // Task creation is an internal action; external reps never create tasks.
        return $user->isInternal()
            && $user->can(Permission::CreateTasks->value)
            && $this->canReach($user, $board);
    }

    public function update(User $user, Task $task): bool
    {
        return $user->isInternal()
            && $user->can(Permission::UpdateTasks->value)
            && ! $task->isCompleted()
            && $this->canReach($user, $task->board);
    }

    public function delete(User $user, Task $task): bool
    {
        return $user->isInternal()
            && $user->can(Permission::UpdateTasks->value)
            && $this->canReach($user, $task->board);
    }

    public function assign(User $user, Task $task): bool
    {
        return $user->isInternal()
            && $user->can(Permission::AssignTasks->value)
            && $this->canReach($user, $task->board);
    }

    /**
     * Move a task to any column on its board. Internal staff move freely on any
     * board they can reach; external reps move only on their own project boards
     * (generalizing the retired approve/reject workflow).
     */
    public function move(User $user, Task $task): bool
    {
        if ($user->isExternal()) {
            return $task->board->isProject()
                && $task->board->hasMember($user)
                && $user->can(Permission::ApproveTasks->value);
        }

        return $user->can(Permission::UpdateTasks->value)
            && $this->canReach($user, $task->board);
    }

    public function comment(User $user, Task $task, CommentVisibility $visibility): bool
    {
        if ($task->isCompleted()) {
            return false;
        }

        if ($user->isExternal()) {
            // External reps may only add customer-visible comments on their boards.
            return $visibility === CommentVisibility::Customer
                && $task->board->isProject()
                && $task->board->hasMember($user);
        }

        return $user->can(Permission::ViewTasks->value) && $this->canReach($user, $task->board);
    }

    public function attach(User $user, Task $task, CommentVisibility $visibility): bool
    {
        return $this->comment($user, $task, $visibility);
    }

    /**
     * Base reachability: external reps see only project boards they belong to;
     * internal staff with the view permission see any board in the tenant.
     */
    protected function canReach(User $user, Board $board): bool
    {
        if ($user->isExternal()) {
            return $board->isProject() && $board->hasMember($user);
        }

        return $user->can(Permission::ViewTasks->value);
    }
}
