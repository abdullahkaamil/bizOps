<?php

declare(strict_types=1);

namespace App\Domain\Quotations\Actions;

use App\Domain\Quotations\Actions\Concerns\RecordsQuotationTransition;
use App\Domain\Quotations\Enums\QuotationStatus;
use App\Domain\Quotations\Models\Quotation;
use App\Models\User;

/**
 * Voids a quotation. Allowed from draft or sent (see the canonical state machine
 * in docs/state-machines.md); the already-terminal states (accepted/rejected/
 * expired) cannot be cancelled.
 */
class CancelQuotationAction
{
    use RecordsQuotationTransition;

    public function handle(Quotation $quotation, User $actor, ?string $reason = null): Quotation
    {
        $this->guardStatus($quotation, QuotationStatus::Draft, QuotationStatus::Sent);

        return $this->transition($quotation, $actor, QuotationStatus::Canceled, [], $reason);
    }
}
