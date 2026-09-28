<?php

declare(strict_types=1);

namespace App\Domain\CRM\Policies;

use App\Domain\CRM\Models\Customer;
use App\Enums\Permission;
use App\Models\User;

/**
 * Authorizes CRM access. External customer representatives never hold these
 * permissions (and are also blocked by the `internal` middleware).
 */
class CustomerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ViewCustomers->value);
    }

    public function view(User $user, Customer $customer): bool
    {
        return $user->can(Permission::ViewCustomers->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::CreateCustomers->value);
    }

    public function update(User $user, Customer $customer): bool
    {
        return $user->can(Permission::UpdateCustomers->value);
    }

    public function delete(User $user, Customer $customer): bool
    {
        return $user->can(Permission::DeleteCustomers->value);
    }
}
