<?php

declare(strict_types=1);

namespace App\Domain\Quotations\Actions;

use App\Domain\Quotations\Actions\Concerns\RecordsQuotationTransition;
use App\Domain\Quotations\Enums\QuotationStatus;
use App\Domain\Quotations\Models\Quotation;
use App\Models\User;

class AcceptQuotationAction
{
    use RecordsQuotationTransition;

    public function handle(Quotation $quotation, User $actor): Quotation
    {
        $this->guardStatus($quotation, QuotationStatus::Sent);

        return $this->transition($quotation, $actor, QuotationStatus::Accepted, ['accepted_at' => now()]);
    }
}
