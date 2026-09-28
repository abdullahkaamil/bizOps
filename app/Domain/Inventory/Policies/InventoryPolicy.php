<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Policies;

use App\Domain\Inventory\Models\InventoryItem;
use App\Enums\Permission;
use App\Models\User;

/**
 * Authorizes inventory. Internal-only. Purchase cost and price history are gated
 * separately by `inventory.view_cost` (see viewCost / serialization).
 */
class InventoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isInternal() && $user->can(Permission::ViewInventory->value);
    }

    public function view(User $user, InventoryItem $item): bool
    {
        return $user->isInternal() && $user->can(Permission::ViewInventory->value);
    }

    public function create(User $user): bool
    {
        return $user->isInternal() && $user->can(Permission::ManageInventory->value);
    }

    public function update(User $user, InventoryItem $item): bool
    {
        return $user->isInternal() && $user->can(Permission::ManageInventory->value);
    }

    public function adjust(User $user, InventoryItem $item): bool
    {
        return $user->isInternal() && $user->can(Permission::ManageInventory->value);
    }

    public function viewCost(User $user): bool
    {
        return $user->isInternal() && $user->can(Permission::ViewInventoryCost->value);
    }
}
