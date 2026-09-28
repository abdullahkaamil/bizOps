<?php

declare(strict_types=1);

namespace App\Domain\Tasks\Policies;

use App\Domain\Tasks\Models\Board;
use App\Enums\Permission;
use App\Models\User;

/**
 * Authorizes board access. External representatives may only ever reach project
 * boards they are a member of; internal staff are gated by permission. All
 * checks are server-authoritative.
 */
class BoardPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ViewTasks->value);
    }

    public function view(User $user, Board $board): bool
    {
        if ($user->isExternal()) {
            return $board->isProject() && $board->hasMember($user);
        }

        return $user->can(Permission::ViewTasks->value);
    }

    public function create(User $user): bool
    {
        return $user->isInternal() && $user->can(Permission::CreateTasks->value);
    }

    public function update(User $user, Board $board): bool
    {
        return $user->isInternal() && $user->can(Permission::UpdateTasks->value);
    }

    public function delete(User $user, Board $board): bool
    {
        return $user->isInternal() && $user->can(Permission::UpdateTasks->value);
    }

    public function manageMembers(User $user, Board $board): bool
    {
        return $user->isInternal() && $user->can(Permission::AssignTasks->value);
    }

    /**
     * Add / rename / reorder / delete the board's steps. Internal-only — external
     * representatives may move tasks between columns but never reshape the board.
     */
    public function manageColumns(User $user, Board $board): bool
    {
        return $user->isInternal() && $user->can(Permission::UpdateTasks->value);
    }
}
