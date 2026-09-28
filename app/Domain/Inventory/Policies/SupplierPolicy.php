<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Policies;

use App\Domain\Inventory\Models\Supplier;
use App\Enums\Permission;
use App\Models\User;

class SupplierPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isInternal() && $user->can(Permission::ViewInventory->value);
    }

    public function view(User $user, Supplier $supplier): bool
    {
        return $user->isInternal() && $user->can(Permission::ViewInventory->value);
    }

    public function create(User $user): bool
    {
        return $user->isInternal() && $user->can(Permission::ManageInventory->value);
    }

    public function update(User $user, Supplier $supplier): bool
    {
        return $user->isInternal() && $user->can(Permission::ManageInventory->value);
    }

    public function delete(User $user, Supplier $supplier): bool
    {
        return $user->isInternal() && $user->can(Permission::ManageInventory->value);
    }
}
