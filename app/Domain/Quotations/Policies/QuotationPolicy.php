<?php

declare(strict_types=1);

namespace App\Domain\Quotations\Policies;

use App\Domain\Quotations\Models\Quotation;
use App\Enums\Permission;
use App\Models\User;

/**
 * Authorizes quotations. Internal-only. Internal cost visibility on a line is
 * gated separately by `inventory.view_cost` (see serialization).
 */
class QuotationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isInternal() && $user->can(Permission::ViewQuotations->value);
    }

    public function view(User $user, Quotation $quotation): bool
    {
        return $user->isInternal() && $user->can(Permission::ViewQuotations->value);
    }

    public function create(User $user): bool
    {
        return $user->isInternal() && $user->can(Permission::CreateQuotations->value);
    }

    public function update(User $user, Quotation $quotation): bool
    {
        return $user->isInternal() && $user->can(Permission::UpdateQuotations->value);
    }

    public function send(User $user, Quotation $quotation): bool
    {
        return $user->isInternal() && $user->can(Permission::SendQuotations->value);
    }

    public function decide(User $user, Quotation $quotation): bool
    {
        // Accept / reject / expire on the customer's behalf.
        return $user->isInternal() && $user->can(Permission::AcceptQuotations->value);
    }

    public function cancel(User $user, Quotation $quotation): bool
    {
        // Voiding a draft/sent quotation is a management action.
        return $user->isInternal() && $user->can(Permission::UpdateQuotations->value);
    }

    public function viewCost(User $user): bool
    {
        return $user->isInternal() && $user->can(Permission::ViewInventoryCost->value);
    }
}
