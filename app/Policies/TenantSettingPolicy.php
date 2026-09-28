<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\User;

/**
 * Authorizes access to tenant company settings. External users never qualify
 * (they hold no settings permissions and are also blocked by the `internal`
 * middleware).
 */
class TenantSettingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ViewSettings->value);
    }

    public function update(User $user): bool
    {
        return $user->can(Permission::UpdateSettings->value);
    }
}
