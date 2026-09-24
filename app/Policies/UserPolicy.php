<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\User;

/**
 * Authorizes tenant-local user management. All checks are enforced server-side;
 * the frontend only mirrors these permissions for presentation.
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ViewUsers->value);
    }

    public function view(User $user, User $model): bool
    {
        return $user->can(Permission::ViewUsers->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::CreateUsers->value);
    }

    public function update(User $user, User $model): bool
    {
        return $user->can(Permission::UpdateUsers->value);
    }

    public function delete(User $user, User $model): bool
    {
        // A user may never delete their own account from the team screen.
        return $user->can(Permission::DeleteUsers->value) && $user->id !== $model->id;
    }

    public function suspend(User $user, User $model): bool
    {
        // Self-suspension is allowed except for the last active owner, which the
        // controller guards; here we only check the permission.
        return $user->can(Permission::SuspendUsers->value);
    }

    public function manageRoles(User $user): bool
    {
        return $user->can(Permission::ManageRoles->value);
    }
}
