<?php

declare(strict_types=1);

namespace App\Domain\Quotations\Actions;

use App\Domain\Documents\DocumentService;
use App\Domain\Documents\Enums\DocumentType;
use App\Domain\Quotations\Actions\Concerns\RecordsQuotationTransition;
use App\Domain\Quotations\Enums\QuotationStatus;
use App\Domain\Quotations\Models\Quotation;
use App\Models\User;

/**
 * Send a draft quotation: lock it, stamp sent_at, and generate the customer-facing
 * PDF (aliases only). Recomputes totals first so what is sent is authoritative.
 */
class SendQuotationAction
{
    use RecordsQuotationTransition;

    public function __construct(
        private RecalculateQuotationAction $recalculate,
        private DocumentService $documents,
    ) {}

    public function handle(Quotation $quotation, User $actor): Quotation
    {
        $this->guardStatus($quotation, QuotationStatus::Draft);

        $this->recalculate->handle($quotation);

        $quotation = $this->transition($quotation, $actor, QuotationStatus::Sent, [
            'sent_at' => now(),
        ]);

        $this->documents->generate(DocumentType::Quotation, $quotation, $actor);

        return $quotation->refresh();
    }
}
