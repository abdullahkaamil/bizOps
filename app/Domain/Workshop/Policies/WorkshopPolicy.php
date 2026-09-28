<?php

declare(strict_types=1);

namespace App\Domain\Workshop\Policies;

use App\Domain\Workshop\Enums\WorkshopStatus;
use App\Domain\Workshop\Models\WorkshopTicket;
use App\Enums\Permission;
use App\Models\User;

/**
 * Authorizes the workshop module. Internal-only — external representatives never
 * reach it (also enforced by the `internal` route middleware).
 */
class WorkshopPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isInternal() && $user->can(Permission::ViewWorkshop->value);
    }

    public function view(User $user, WorkshopTicket $ticket): bool
    {
        return $user->isInternal() && $user->can(Permission::ViewWorkshop->value);
    }

    public function create(User $user): bool
    {
        return $user->isInternal() && $user->can(Permission::CreateWorkshop->value);
    }

    public function update(User $user, WorkshopTicket $ticket): bool
    {
        return $ticket->status === WorkshopStatus::InProgress
            && $user->isInternal()
            && $user->can(Permission::UpdateWorkshop->value);
    }

    public function complete(User $user, WorkshopTicket $ticket): bool
    {
        return $ticket->status === WorkshopStatus::InProgress
            && $user->isInternal()
            && $user->can(Permission::CompleteWorkshop->value);
    }

    public function deliver(User $user, WorkshopTicket $ticket): bool
    {
        return $ticket->status === WorkshopStatus::Completed
            && $user->isInternal()
            && $user->can(Permission::DeliverWorkshop->value);
    }
}
